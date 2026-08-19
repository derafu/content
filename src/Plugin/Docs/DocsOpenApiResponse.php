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

use Derafu\Content\Plugin\Docs\Contract\DocsOpenApiResponseInterface;

/**
 * Class that represents an OpenAPI operation response.
 */
class DocsOpenApiResponse implements DocsOpenApiResponseInterface
{
    /**
     * Constructor.
     *
     * @param string $statusCode HTTP status code of the response.
     * @param string|null $description Description of the response.
     */
    public function __construct(
        private readonly string $statusCode,
        private readonly ?string $description
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function statusCode(): string
    {
        return $this->statusCode;
    }

    /**
     * {@inheritDoc}
     */
    public function description(): ?string
    {
        return $this->description;
    }

    /**
     * Builds a response from its raw OpenAPI array shape.
     *
     * @param string $statusCode HTTP status code of the response.
     * @param array $data Raw response data.
     * @return self
     */
    public static function fromArray(string $statusCode, array $data): self
    {
        return new self(
            statusCode: $statusCode,
            description: $data['description'] ?? null
        );
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
            'status_code' => $this->statusCode(),
            'description' => $this->description(),
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
        return $this->statusCode();
    }
}
