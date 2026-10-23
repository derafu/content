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

use DateTimeInterface;
use Derafu\Content\Abstract\AbstractContentItem;
use Derafu\Content\Plugin\Docs\Contract\DocsDocInterface;
use Derafu\Translation\Exception\Core\TranslatableRuntimeException as RuntimeException;
use JsonException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Utopia\OpenAPI\Exception\OpenAPIException;
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
     * The OpenAPI document as it was read, to give to the view what the
     * model does not have.
     *
     * @var array<array-key, mixed>
     */
    private array $openapiRaw = [];

    /**
     * View of the OpenAPI spec for the Markdown and the PDF.
     *
     * @var DocsOpenApiView|false
     */
    private DocsOpenApiView|false $openapiView;

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
                : self::withoutDates(Yaml::parse($raw, Yaml::PARSE_DATETIME));

            $this->openapiRaw = is_array($data) ? $data : [];

            return Parser::parse($this->openapiRaw);
        } catch (JsonException | ParseException | OpenAPIException $e) {
            throw new RuntimeException([
                'The OpenAPI document of "{content}" is not valid: {error}',
                'content' => $this->uri(),
                'error' => $e->getMessage(),
            ], previous: $e);
        }
    }

    /**
     * Gives back as text the dates that YAML reads as dates, so a date in an
     * example (`example: 2026-01-31`) is shown as it was written and not as a
     * number of seconds.
     *
     * @param mixed $value Value that was read.
     * @return mixed
     */
    private static function withoutDates(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('H:i:s') === '00:00:00' ? $value->format('Y-m-d') : $value->format(DATE_ATOM);
        }

        if (is_array($value)) {
            return array_map(self::withoutDates(...), $value);
        }

        return $value;
    }

    /**
     * {@inheritDoc}
     */
    public function openapiView(): ?DocsOpenApiView
    {
        if (!isset($this->openapiView)) {
            $spec = $this->openapiSpec();

            $this->openapiView = $spec === null
                ? false
                : new DocsOpenApiView($spec, $this->openapiRaw, $this->uri());
        }

        return $this->openapiView ?: null;
    }
}
