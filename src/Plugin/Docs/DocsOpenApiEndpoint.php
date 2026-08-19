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

use Derafu\Content\Plugin\Docs\Contract\DocsOpenApiEndpointInterface;
use Derafu\Content\Plugin\Docs\Contract\DocsOpenApiParameterInterface;
use Derafu\Content\Plugin\Docs\Contract\DocsOpenApiResponseInterface;

/**
 * Class that represents an OpenAPI operation (an HTTP method on a path).
 */
class DocsOpenApiEndpoint implements DocsOpenApiEndpointInterface
{
    /**
     * Constructor.
     *
     * @param string $method HTTP method of the endpoint.
     * @param string $path Path of the endpoint.
     * @param string|null $summary Short summary of the endpoint.
     * @param string|null $description Longer description of the endpoint.
     * @param array<string> $tags Tags of the endpoint.
     * @param array<DocsOpenApiParameterInterface> $parameters Parameters
     * of the endpoint.
     * @param string|null $requestBodyExample Printable example of the
     * request body.
     * @param array<DocsOpenApiResponseInterface> $responses Responses of
     * the endpoint.
     */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly ?string $summary,
        private readonly ?string $description,
        private readonly array $tags,
        private readonly array $parameters,
        private readonly ?string $requestBodyExample,
        private readonly array $responses
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function method(): string
    {
        return $this->method;
    }

    /**
     * {@inheritDoc}
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * {@inheritDoc}
     */
    public function summary(): ?string
    {
        return $this->summary;
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
    public function tags(): array
    {
        return $this->tags;
    }

    /**
     * {@inheritDoc}
     */
    public function parameters(): array
    {
        return $this->parameters;
    }

    /**
     * {@inheritDoc}
     */
    public function requestBodyExample(): ?string
    {
        return $this->requestBodyExample;
    }

    /**
     * {@inheritDoc}
     */
    public function responses(): array
    {
        return $this->responses;
    }

    /**
     * Builds an endpoint from its raw OpenAPI operation array shape.
     *
     * Not resolved through a "$ref": only the initial, no-external-
     * dependency version of the OpenAPI support, meant for specs that
     * (like the real ones this was built against) declare everything
     * inline instead of reusing shared components.
     *
     * @param string $method HTTP method of the endpoint.
     * @param string $path Path of the endpoint.
     * @param array $operation Raw operation data.
     * @return self
     */
    public static function fromArray(string $method, string $path, array $operation): self
    {
        $parameters = array_values(array_map(
            fn (array $parameter) => DocsOpenApiParameter::fromArray($parameter),
            array_filter($operation['parameters'] ?? [], 'is_array')
        ));

        $responses = [];
        foreach ($operation['responses'] ?? [] as $statusCode => $response) {
            if (is_array($response)) {
                $responses[] = DocsOpenApiResponse::fromArray((string) $statusCode, $response);
            }
        }

        $tags = array_values(array_filter(
            $operation['tags'] ?? [],
            'is_string'
        ));

        return new self(
            method: strtoupper($method),
            path: $path,
            summary: $operation['summary'] ?? null,
            description: $operation['description'] ?? null,
            tags: $tags,
            parameters: $parameters,
            requestBodyExample: self::requestBodyExampleFromArray($operation['requestBody'] ?? null),
            responses: $responses
        );
    }

    /**
     * Extracts a printable request body example: the first "example" (or
     * first "examples" entry) found across the request body's declared
     * content types.
     *
     * @param array|null $requestBody Raw requestBody data.
     * @return string|null
     */
    private static function requestBodyExampleFromArray(?array $requestBody): ?string
    {
        foreach ($requestBody['content'] ?? [] as $mediaType) {
            if (!is_array($mediaType)) {
                continue;
            }

            if (isset($mediaType['example'])) {
                return self::stringifyExample($mediaType['example']);
            }

            foreach ($mediaType['examples'] ?? [] as $example) {
                if (isset($example['value'])) {
                    return self::stringifyExample($example['value']);
                }
            }
        }

        return null;
    }

    /**
     * Renders an example value as a printable string: as-is if it is
     * already a string, pretty-printed JSON otherwise.
     *
     * @param mixed $example Raw example value.
     * @return string
     */
    private static function stringifyExample(mixed $example): string
    {
        if (is_string($example)) {
            return $example;
        }

        return json_encode(
            $example,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
            'method' => $this->method(),
            'path' => $this->path(),
            'summary' => $this->summary(),
            'description' => $this->description(),
            'tags' => $this->tags(),
            'parameters' => array_map(
                fn ($parameter) => $parameter->toArray(),
                $this->parameters()
            ),
            'request_body_example' => $this->requestBodyExample(),
            'responses' => array_map(
                fn ($response) => $response->toArray(),
                $this->responses()
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
        return $this->method() . ' ' . $this->path();
    }
}
