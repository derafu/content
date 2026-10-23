<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Plugin\Docs;

use Derafu\Content\Abstract\AbstractContentItem;
use Derafu\Content\Plugin\Docs\Contract\DocsDocInterface;
use Derafu\Translation\Exception\Core\TranslatableRuntimeException as RuntimeException;
use JsonException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Utopia\OpenAPI\Exception\OpenAPIException;
use Utopia\OpenAPI\Model\ArraySchema;
use Utopia\OpenAPI\Model\BooleanSchema;
use Utopia\OpenAPI\Model\IntegerSchema;
use Utopia\OpenAPI\Model\NumberSchema;
use Utopia\OpenAPI\Model\ObjectSchema;
use Utopia\OpenAPI\Model\Operation;
use Utopia\OpenAPI\Model\ReferenceSchema;
use Utopia\OpenAPI\Model\Schema;
use Utopia\OpenAPI\Model\StringSchema;
use Utopia\OpenAPI\Parser;
use Utopia\OpenAPI\Specification;

/**
 * Class that represents a doc.
 */
class DocsDoc extends AbstractContentItem implements DocsDocInterface
{
    /**
     * OpenAPI spec of the doc, parsed from its attachment/remote
     * reference. False is used (never null) as the "not resolved yet"
     * memoization sentinel, same reasoning as AcademyLesson::$test.
     *
     * @var Specification|false
     */
    private Specification|false $openapiSpec;

    /**
     * {@inheritDoc}
     */
    public function type(): string
    {
        return 'docs';
    }

    /**
     * {@inheritDoc}
     */
    public function category(): string
    {
        return 'doc';
    }

    /**
     * {@inheritDoc}
     */
    public function parent(): ?DocsDocInterface
    {
        $parent = parent::parent();

        if ($parent === null) {
            return null;
        }

        assert($parent instanceof DocsDocInterface);

        return $parent;
    }

    /**
     * {@inheritDoc}
     */
    public function openapiSpec(): ?Specification
    {
        if (!isset($this->openapiSpec)) {
            $this->openapiSpec = $this->metadata('openapi') !== null
                ? $this->readOpenapiSpec()
                : false;
        }

        return $this->openapiSpec ?: null;
    }

    /**
     * Reads and parses the document that the "openapi" frontmatter points to.
     *
     * @return Specification
     * @throws RuntimeException If the parser is not installed, if the
     * reference does not resolve to a document or if the document is not valid.
     */
    private function readOpenapiSpec(): Specification
    {
        if (!class_exists(Parser::class)) {
            throw new RuntimeException([
                'The doc "{content}" has "openapi" but "utopia-php/openapi" is not installed. Run: composer require utopia-php/openapi',
                'content' => $this->uri(),
            ]);
        }

        $raw = $this->resolveReferenceContent('openapi');

        if ($raw === null) {
            throw new RuntimeException([
                'The "openapi" reference "{reference}" of the doc "{content}" does not resolve to a document: it must be an attachment of the doc or an absolute URL.',
                'reference' => get_debug_type($this->metadata('openapi')) === 'string' ? $this->metadata('openapi') : '',
                'content' => $this->uri(),
            ]);
        }

        try {
            $trimmed = ltrim($raw);
            $data = $trimmed !== '' && $trimmed[0] === '{'
                ? json_decode($raw, true, flags: JSON_THROW_ON_ERROR)
                : Yaml::parse($raw);

            return Parser::parse(is_array($data) ? $data : []);
        } catch (JsonException | ParseException | OpenAPIException $e) {
            throw new RuntimeException([
                'The OpenAPI document of "{content}" is not valid: {error}',
                'content' => $this->uri(),
                'error' => $e->getMessage(),
            ], previous: $e);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function openapiGroups(): array
    {
        $spec = $this->openapiSpec();

        if ($spec === null) {
            return [];
        }

        $groups = [];
        $descriptions = [];
        foreach ($spec->tags as $tag) {
            $groups[$tag->name] = [];
            $descriptions[$tag->name] = $tag->description;
        }

        $untagged = [];
        foreach ($spec->operations() as $operation) {
            $primaryTag = $operation->tags[0] ?? null;

            if ($primaryTag === null) {
                $untagged[] = $operation;
            } else {
                $groups[$primaryTag][] = $operation;
            }
        }

        $result = [];
        foreach ($groups as $name => $operations) {
            if ($operations !== []) {
                $result[] = [
                    'name' => (string) $name,
                    'description' => $descriptions[$name] ?? '',
                    'operations' => $operations,
                ];
            }
        }

        if ($untagged !== []) {
            $result[] = ['name' => null, 'description' => '', 'operations' => $untagged];
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function openapiTypeLabel(?Schema $schema): string
    {
        return match (true) {
            $schema instanceof StringSchema => 'string',
            $schema instanceof IntegerSchema => 'integer',
            $schema instanceof NumberSchema => 'number',
            $schema instanceof BooleanSchema => 'boolean',
            $schema instanceof ObjectSchema => 'object',
            $schema instanceof ArraySchema => 'array',
            $schema instanceof ReferenceSchema => basename($schema->reference),
            default => '',
        };
    }

    /**
     * {@inheritDoc}
     */
    public function openapiRequestExample(Operation $operation): ?string
    {
        foreach ($operation->requestBody->content ?? [] as $mediaType) {
            if ($mediaType->example !== null) {
                return $this->openapiExample($operation, $mediaType->example);
            }

            foreach ($mediaType->examples as $example) {
                if ($example->value !== null) {
                    return $this->openapiExample($operation, $example->value);
                }
            }
        }

        return null;
    }

    /**
     * Renders an example value as a printable string: as-is if it is
     * already a string, pretty-printed JSON otherwise.
     *
     * @param Operation $operation Operation that has the example.
     * @param mixed $example Example value.
     * @return string
     * @throws RuntimeException If the example can not be encoded as JSON.
     */
    private function openapiExample(Operation $operation, mixed $example): string
    {
        if (is_string($example)) {
            return $example;
        }

        try {
            return json_encode(
                $example,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );
        } catch (JsonException $e) {
            throw new RuntimeException([
                'The request body example of "{operation}" in the doc "{content}" could not be encoded as JSON: {error}',
                'operation' => strtoupper($operation->method->value) . ' ' . $operation->path,
                'content' => $this->uri(),
                'error' => $e->getMessage(),
            ], previous: $e);
        }
    }
}
