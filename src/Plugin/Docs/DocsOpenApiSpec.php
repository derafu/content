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

use Derafu\Content\Plugin\Docs\Contract\DocsOpenApiSpecInterface;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;
use Throwable;

/**
 * Class that represents an OpenAPI document, parsed from a doc's
 * "openapi" frontmatter reference (a local attachment or a remote URL,
 * resolved by AbstractContentItem::resolveReferenceContent()).
 *
 * Parses the raw YAML/JSON into a plain array by hand (no OpenAPI-
 * specific library), and does not resolve "$ref"/"allOf" composition:
 * the two real specs this was built against declare everything inline.
 * If a spec that actually needs that ever shows up, fromArray() is the
 * one seam to change — feed it an already "flattened" array (e.g. from
 * a library that resolves references) instead of the raw parse below,
 * and every other class here stays the same.
 */
class DocsOpenApiSpec implements DocsOpenApiSpecInterface
{
    /**
     * HTTP methods recognized as an operation under a path.
     *
     * @var array<string>
     */
    private const HTTP_METHODS = [
        'get', 'post', 'put', 'patch', 'delete', 'options', 'head', 'trace',
    ];

    /**
     * Constructor.
     *
     * @param string $title Title of the API.
     * @param string|null $description Description of the API.
     * @param string|null $version Version of the API.
     * @param array<string> $servers Server URLs of the API.
     * @param array<DocsOpenApiEndpoint> $endpoints Endpoints of the API.
     * @param array<DocsOpenApiTag> $tags Top-level tags of the API.
     */
    public function __construct(
        private readonly string $title,
        private readonly ?string $description,
        private readonly ?string $version,
        private readonly array $servers,
        private readonly array $endpoints,
        private readonly array $tags
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function title(): string
    {
        return $this->title;
    }

    /**
     * {@inheritDoc}
     */
    public function description(): ?string
    {
        return $this->description;
    }

    /**
     * {@inheritDoc}
     */
    public function version(): ?string
    {
        return $this->version;
    }

    /**
     * {@inheritDoc}
     */
    public function servers(): array
    {
        return $this->servers;
    }

    /**
     * {@inheritDoc}
     */
    public function endpoints(): array
    {
        return $this->endpoints;
    }

    /**
     * {@inheritDoc}
     */
    public function tags(): array
    {
        return $this->tags;
    }

    /**
     * {@inheritDoc}
     */
    public function endpointGroups(): array
    {
        $groups = [];
        foreach ($this->tags() as $tag) {
            $groups[$tag->name()] = [];
        }

        $untagged = [];
        foreach ($this->endpoints() as $endpoint) {
            $primaryTag = $endpoint->tags()[0] ?? null;

            if ($primaryTag === null) {
                $untagged[] = $endpoint;
            } else {
                $groups[$primaryTag][] = $endpoint;
            }
        }

        $descriptions = [];
        foreach ($this->tags() as $tag) {
            $descriptions[$tag->name()] = $tag->description();
        }

        $result = [];
        foreach ($groups as $name => $endpoints) {
            if ($endpoints === []) {
                continue;
            }

            $result[] = new DocsOpenApiEndpointGroup($name, $descriptions[$name] ?? null, $endpoints);
        }

        if ($untagged !== []) {
            $result[] = new DocsOpenApiEndpointGroup(null, null, $untagged);
        }

        return $result;
    }

    /**
     * Parses a raw OpenAPI document (YAML or JSON) into a spec.
     *
     * @param string $raw Raw OpenAPI document content.
     * @return self
     * @throws RuntimeException If the document cannot be parsed.
     */
    public static function fromRaw(string $raw): self
    {
        return self::fromArray(self::parseRaw($raw));
    }

    /**
     * Parses the raw document into a plain array, deciding between JSON
     * and YAML from its first non-whitespace character.
     *
     * @param string $raw Raw OpenAPI document content.
     * @return array
     */
    private static function parseRaw(string $raw): array
    {
        $trimmed = ltrim($raw);

        try {
            if ($trimmed !== '' && $trimmed[0] === '{') {
                return json_decode($raw, true, flags: JSON_THROW_ON_ERROR) ?? [];
            }

            return Yaml::parse($raw) ?? [];
        } catch (Throwable $e) {
            throw new RuntimeException(sprintf(
                'Invalid OpenAPI document: %s',
                $e->getMessage()
            ), previous: $e);
        }
    }

    /**
     * Builds a spec from its raw OpenAPI array shape.
     *
     * @param array $data Raw OpenAPI document data.
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $info = $data['info'] ?? [];

        $servers = array_values(array_filter(array_map(
            fn ($server) => is_array($server) ? ($server['url'] ?? null) : null,
            $data['servers'] ?? []
        )));

        $endpoints = [];
        foreach ($data['paths'] ?? [] as $path => $operations) {
            if (!is_array($operations)) {
                continue;
            }

            foreach ($operations as $method => $operation) {
                if (!is_array($operation) || !in_array(strtolower($method), self::HTTP_METHODS, true)) {
                    continue;
                }

                $endpoints[] = DocsOpenApiEndpoint::fromArray($method, $path, $operation);
            }
        }

        $tags = array_values(array_map(
            fn (array $tag) => DocsOpenApiTag::fromArray($tag),
            array_filter($data['tags'] ?? [], 'is_array')
        ));

        return new self(
            title: $info['title'] ?? '',
            description: $info['description'] ?? null,
            version: $info['version'] ?? null,
            servers: $servers,
            endpoints: $endpoints,
            tags: $tags
        );
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title(),
            'description' => $this->description(),
            'version' => $this->version(),
            'servers' => $this->servers(),
            'endpoints' => array_map(
                fn ($endpoint) => $endpoint->toArray(),
                $this->endpoints()
            ),
            'tags' => array_map(
                fn ($tag) => $tag->toArray(),
                $this->tags()
            ),
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * {@inheritDoc}
     */
    public function __toString(): string
    {
        return $this->title();
    }
}
