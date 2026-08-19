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

use Derafu\Content\Plugin\Docs\Contract\DocsOpenApiTagInterface;

/**
 * Class that represents an OpenAPI top-level tag.
 */
class DocsOpenApiTag implements DocsOpenApiTagInterface
{
    /**
     * Constructor.
     *
     * @param string $name Name of the tag.
     * @param string|null $description Description of the tag.
     */
    public function __construct(
        private readonly string $name,
        private readonly ?string $description
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * {@inheritDoc}
     */
    public function description(): ?string
    {
        return $this->description;
    }

    /**
     * Builds a tag from its raw OpenAPI array shape.
     *
     * @param array $data Raw tag data.
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? '',
            description: $data['description'] ?? null
        );
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name(),
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
        return $this->name();
    }
}
