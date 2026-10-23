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

use Derafu\Translation\Exception\Core\TranslatableRuntimeException as RuntimeException;
use JsonException;
use Utopia\OpenAPI\Model\ArraySchema;
use Utopia\OpenAPI\Model\BooleanSchema;
use Utopia\OpenAPI\Model\CompositeSchema;
use Utopia\OpenAPI\Model\IntegerSchema;
use Utopia\OpenAPI\Model\MediaType;
use Utopia\OpenAPI\Model\NumberSchema;
use Utopia\OpenAPI\Model\ObjectSchema;
use Utopia\OpenAPI\Model\Operation;
use Utopia\OpenAPI\Model\Parameter;
use Utopia\OpenAPI\Model\ReferenceSchema;
use Utopia\OpenAPI\Model\Schema;
use Utopia\OpenAPI\Model\StringSchema;
use Utopia\OpenAPI\Specification;

/**
 * What the Markdown and the PDF of an OpenAPI document show, read from its
 * model: the operations by tag, the type of each schema, the properties of an
 * object, and the examples.
 *
 * An example is what the author of the document wrote: the `example` and the
 * `examples` of a media type or of a parameter, or the `example` and the
 * `default` of the properties of a schema, put together. Nothing is invented
 * from the type of a property that has no value.
 */
final class DocsOpenApiView
{
    /**
     * Depth to which the properties of nested objects are listed.
     */
    private const PROPERTIES_DEPTH = 3;

    /**
     * Depth to which an example is put together from a schema.
     */
    private const EXAMPLE_DEPTH = 8;

    /**
     * @param Specification $spec Model of the document.
     * @param array<array-key, mixed> $raw The document as it was read, for what
     * the model does not have (the examples of the parameters).
     * @param string $content Content that has the document, for the errors.
     */
    public function __construct(
        private readonly Specification $spec,
        private readonly array $raw,
        private readonly string $content
    ) {
    }

    /**
     * The operations grouped by their first tag, in the order of the tags of the
     * document and, after them, the operations without tag (name null).
     *
     * @return list<array{name: string|null, description: string, operations: list<Operation>}>
     */
    public function groups(): array
    {
        $groups = [];
        $descriptions = [];
        foreach ($this->spec->tags as $tag) {
            $groups[$tag->name] = [];
            $descriptions[$tag->name] = $tag->description;
        }

        $untagged = [];
        foreach ($this->spec->operations() as $operation) {
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
     * The name of the type of a schema, to show it next to a parameter or a
     * property: `string`, `string (date)`, `array<integer>`, the name of a
     * component (`Pet`) or `oneOf`.
     *
     * @param Schema|null $schema Schema.
     * @return string
     */
    public function typeLabel(?Schema $schema): string
    {
        if ($schema === null) {
            return '';
        }

        $label = match (true) {
            $schema instanceof StringSchema => 'string',
            $schema instanceof IntegerSchema => 'integer',
            $schema instanceof NumberSchema => 'number',
            $schema instanceof BooleanSchema => 'boolean',
            $schema instanceof ObjectSchema => 'object',
            $schema instanceof ArraySchema => 'array<' . ($this->typeLabel($schema->items) ?: 'mixed') . '>',
            $schema instanceof ReferenceSchema => basename($schema->reference),
            $schema instanceof CompositeSchema => $schema->composition->value ?? 'object',
            default => '',
        };

        if ($label === '') {
            return '';
        }

        if ($schema->format !== null && !$schema instanceof ReferenceSchema) {
            $label .= ' (' . $schema->format . ')';
        }

        return $schema->nullable ? $label . '|null' : $label;
    }

    /**
     * What a parameter has besides its type: the allowed values, the default
     * and the examples.
     *
     * @param Operation $operation Operation of the parameter.
     * @param Parameter $parameter Parameter.
     * @return array{enum: list<string>, default: string|null, examples: list<array{summary: string, text: string}>}
     */
    public function parameterNotes(Operation $operation, Parameter $parameter): array
    {
        $schema = $parameter->schema === null ? null : $this->spec->resolveSchema($parameter->schema);

        $examples = $this->rawParameterExamples($operation, $parameter);
        if ($schema === null) {
            return ['enum' => [], 'default' => null, 'examples' => $examples];
        }

        if ($examples === [] && $schema->example !== null) {
            $examples[] = ['summary' => '', 'text' => $this->inline($schema->example)];
        }

        return [
            'enum' => array_map(fn ($value) => $this->inline($value), $schema->enum),
            'default' => $schema->default === null ? null : $this->inline($schema->default),
            'examples' => $examples,
        ];
    }

    /**
     * The examples of the content of a request or of a response, one for each
     * different example: the media types that have the same one are together.
     *
     * @param array<string, MediaType> $content Content of the body.
     * @return list<array{types: list<string>, summary: string, text: string}>
     */
    public function examples(array $content): array
    {
        $examples = [];

        foreach ($content as $type => $media) {
            foreach ($this->mediaExamples($media) as $example) {
                $key = $example['summary'] . "\n" . $example['text'];
                $examples[$key] ??= ['types' => [], 'summary' => $example['summary'], 'text' => $example['text']];
                $examples[$key]['types'][] = (string) $type;
            }
        }

        return array_values($examples);
    }

    /**
     * The properties of a schema, as a list with the depth of each one: the
     * ones of an object, the ones of the objects of an array and the ones of
     * the parts of a composition.
     *
     * @param Schema|null $schema Schema of a body.
     * @return list<array{depth: int, name: string, type: string, required: bool, description: string, enum: list<string>, default: string|null, example: string|null}>
     */
    public function properties(?Schema $schema): array
    {
        $rows = [];

        if ($schema !== null) {
            $this->collect($schema, 0, $rows, []);
        }

        return $rows;
    }

    /**
     * The first schema of the content of a body that has properties.
     *
     * @param array<string, MediaType> $content Content of the body.
     * @return Schema|null
     */
    public function schemaOf(array $content): ?Schema
    {
        foreach ($content as $media) {
            if ($media->schema !== null) {
                return $media->schema;
            }
        }

        return null;
    }

    /**
     * Examples of a media type: its `example`, its `examples` or, if it has
     * none, the one that its schema gives.
     *
     * @return list<array{summary: string, text: string}>
     */
    private function mediaExamples(MediaType $media): array
    {
        $examples = [];

        if ($media->example !== null) {
            $examples[] = ['summary' => '', 'text' => $this->block($media->example)];
        }

        foreach ($media->examples as $name => $example) {
            if ($example->value !== null) {
                $examples[] = [
                    'summary' => $example->summary !== '' ? $example->summary : (string) $name,
                    'text' => $this->block($example->value),
                ];
            }
        }

        if ($examples === [] && $media->schema !== null) {
            $value = $this->valueOf($media->schema, 0, []);
            if ($value !== null) {
                $examples[] = ['summary' => '', 'text' => $this->block($value)];
            }
        }

        return $examples;
    }

    /**
     * The value that a schema says: its `example`, or its `default`, or the
     * object or the list that the values of its parts make. It is null when
     * the schema does not say any.
     *
     * @param Schema $schema Schema.
     * @param int $depth Depth of the schema.
     * @param list<string> $references References that are being resolved.
     * @return mixed
     */
    private function valueOf(Schema $schema, int $depth, array $references): mixed
    {
        if ($depth > self::EXAMPLE_DEPTH) {
            return null;
        }

        if ($schema instanceof ReferenceSchema) {
            if (in_array($schema->reference, $references, true)) {
                return null;
            }
            $references[] = $schema->reference;
            $resolved = $this->spec->resolveSchema($schema);

            return $resolved instanceof ReferenceSchema ? null : $this->valueOf($resolved, $depth + 1, $references);
        }

        if ($schema->example !== null) {
            return $schema->example;
        }

        if ($schema instanceof ObjectSchema) {
            $object = [];
            foreach ($schema->properties as $name => $property) {
                $value = $this->valueOf($property, $depth + 1, $references);
                if ($value !== null) {
                    $object[$name] = $value;
                }
            }

            return $object === [] ? ($schema->default ?? null) : $object;
        }

        if ($schema instanceof ArraySchema) {
            $item = $this->valueOf($schema->items, $depth + 1, $references);

            return $item === null ? ($schema->default ?? null) : [$item];
        }

        if ($schema instanceof CompositeSchema) {
            return $this->compositionValue($schema, $depth, $references);
        }

        return $schema->default;
    }

    /**
     * The value of a composition: the parts of `allOf` put together, or the
     * first part of the others that has a value.
     *
     * @param list<string> $references References that are being resolved.
     */
    private function compositionValue(CompositeSchema $schema, int $depth, array $references): mixed
    {
        $values = [];
        foreach ($schema->schemas as $part) {
            $value = $this->valueOf($part, $depth + 1, $references);
            if ($value !== null) {
                $values[] = $value;
            }
        }

        if ($values === []) {
            return $schema->default;
        }

        if ($schema->composition?->value !== 'allOf') {
            return $values[0];
        }

        $objects = array_filter($values, fn ($value) => is_array($value) && !array_is_list($value));

        return $objects === [] ? $values[0] : array_merge(...array_values($objects));
    }

    /**
     * Adds the properties of a schema to the list.
     *
     * @param list<array{depth: int, name: string, type: string, required: bool, description: string, enum: list<string>, default: string|null, example: string|null}> $rows List that is built.
     * @param list<string> $references References that are being resolved.
     */
    private function collect(Schema $schema, int $depth, array &$rows, array $references): void
    {
        if ($schema instanceof ReferenceSchema) {
            if (in_array($schema->reference, $references, true)) {
                return;
            }
            $references[] = $schema->reference;
            $schema = $this->spec->resolveSchema($schema);
            if ($schema instanceof ReferenceSchema) {
                return;
            }
        }

        if ($schema instanceof ArraySchema) {
            $this->collect($schema->items, $depth, $rows, $references);

            return;
        }

        if ($schema instanceof CompositeSchema) {
            foreach ($schema->schemas as $part) {
                $this->collect($part, $depth, $rows, $references);
            }

            return;
        }

        if (!$schema instanceof ObjectSchema) {
            return;
        }

        foreach ($schema->properties as $name => $property) {
            $resolved = $property instanceof ReferenceSchema ? $this->spec->resolveSchema($property) : $property;

            $rows[] = [
                'depth' => $depth,
                'name' => (string) $name,
                'type' => $this->typeLabel($property),
                'required' => in_array($name, $schema->required, true),
                'description' => $resolved->description,
                'enum' => array_map(fn ($value) => $this->inline($value), $resolved->enum),
                'default' => $resolved->default === null || !is_scalar($resolved->default) ? null : $this->inline($resolved->default),
                'example' => $resolved->example === null || !is_scalar($resolved->example) ? null : $this->inline($resolved->example),
            ];

            if ($depth + 1 < self::PROPERTIES_DEPTH) {
                $this->collect($property, $depth + 1, $rows, $references);
            }
        }
    }

    /**
     * The examples of a parameter that the document has and the model does not:
     * its `example` and its `examples`.
     *
     * @return list<array{summary: string, text: string}>
     */
    private function rawParameterExamples(Operation $operation, Parameter $parameter): array
    {
        $paths = $this->raw['paths'] ?? null;
        $item = is_array($paths) ? ($paths[$operation->path] ?? null) : null;
        if (!is_array($item)) {
            return [];
        }

        $operationRaw = $item[$operation->method->value] ?? null;
        $candidates = [
            ...(is_array($operationRaw) && is_array($operationRaw['parameters'] ?? null) ? $operationRaw['parameters'] : []),
            ...(is_array($item['parameters'] ?? null) ? $item['parameters'] : []),
        ];

        foreach ($candidates as $candidate) {
            $candidate = $this->dereference($candidate);
            if (
                is_array($candidate)
                && ($candidate['name'] ?? null) === $parameter->name
                && ($candidate['in'] ?? null) === $parameter->location->value
            ) {
                $examples = [];
                if (array_key_exists('example', $candidate) && $candidate['example'] !== null) {
                    $examples[] = ['summary' => '', 'text' => $this->inline($candidate['example'])];
                }
                if (is_array($candidate['examples'] ?? null)) {
                    foreach ($candidate['examples'] as $name => $example) {
                        $example = $this->dereference($example);
                        if (is_array($example) && ($example['value'] ?? null) !== null) {
                            $summary = $example['summary'] ?? $name;
                            $examples[] = [
                                'summary' => is_string($summary) ? $summary : (string) $name,
                                'text' => $this->inline($example['value']),
                            ];
                        }
                    }
                }

                return $examples;
            }
        }

        return [];
    }

    /**
     * Follows a reference to the same document (`#/components/parameters/Id`).
     */
    private function dereference(mixed $value): mixed
    {
        for ($i = 0; $i < 10 && is_array($value) && isset($value['$ref']) && is_string($value['$ref']); $i++) {
            if (!str_starts_with($value['$ref'], '#/')) {
                return null;
            }

            $target = $this->raw;
            foreach (explode('/', substr($value['$ref'], 2)) as $key) {
                $key = str_replace(['~1', '~0'], ['/', '~'], rawurldecode($key));
                if (!is_array($target) || !array_key_exists($key, $target)) {
                    return null;
                }
                $target = $target[$key];
            }
            $value = $target;
        }

        return $value;
    }

    /**
     * A value in a line: a text as it is and the others as JSON.
     */
    private function inline(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '';
    }

    /**
     * A value in a block: a text as it is and the others as pretty JSON.
     *
     * @throws RuntimeException If the value can not be encoded as JSON.
     */
    private function block(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        try {
            return json_encode(
                $value,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );
        } catch (JsonException $e) {
            throw new RuntimeException([
                'An example of the OpenAPI document of "{content}" could not be encoded as JSON: {error}',
                'content' => $this->content,
                'error' => $e->getMessage(),
            ], previous: $e);
        }
    }
}
