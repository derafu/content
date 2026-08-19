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

use Derafu\Content\Plugin\Docs\Contract\DocsOpenApiParameterInterface;

/**
 * Class that represents an OpenAPI operation parameter.
 */
class DocsOpenApiParameter implements DocsOpenApiParameterInterface
{
    /**
     * Constructor.
     *
     * @param string $name Name of the parameter.
     * @param string $in Where the parameter is sent ("query", "path", ...).
     * @param bool $required Whether the parameter is required.
     * @param string|null $description Description of the parameter.
     * @param string|null $type Type of the parameter's schema.
     */
    public function __construct(
        private readonly string $name,
        private readonly string $in,
        private readonly bool $required,
        private readonly ?string $description,
        private readonly ?string $type
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
    public function in(): string
    {
        return $this->in;
    }

    /**
     * {@inheritDoc}
     */
    public function required(): bool
    {
        return $this->required;
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
    public function type(): ?string
    {
        return $this->type;
    }

    /**
     * Builds a parameter from its raw OpenAPI array shape.
     *
     * @param array $data Raw parameter data.
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? '',
            in: $data['in'] ?? '',
            required: (bool) ($data['required'] ?? false),
            description: $data['description'] ?? null,
            type: $data['schema']['type'] ?? null
        );
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name(),
            'in' => $this->in(),
            'required' => $this->required(),
            'description' => $this->description(),
            'type' => $this->type(),
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
