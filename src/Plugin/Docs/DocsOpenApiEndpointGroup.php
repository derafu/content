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

use Derafu\Content\Plugin\Docs\Contract\DocsOpenApiEndpointGroupInterface;
use Derafu\Content\Plugin\Docs\Contract\DocsOpenApiEndpointInterface;

/**
 * Class that represents a group of OpenAPI endpoints sharing the same
 * primary tag.
 */
class DocsOpenApiEndpointGroup implements DocsOpenApiEndpointGroupInterface
{
    /**
     * Constructor.
     *
     * @param string|null $tagName Name of the tag this group is for, or
     * null for the untagged group.
     * @param string|null $tagDescription Description of the tag.
     * @param array<DocsOpenApiEndpointInterface> $endpoints Endpoints of
     * the group.
     */
    public function __construct(
        private readonly ?string $tagName,
        private readonly ?string $tagDescription,
        private readonly array $endpoints
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function tagName(): ?string
    {
        return $this->tagName;
    }

    /**
     * {@inheritDoc}
     */
    public function tagDescription(): ?string
    {
        return $this->tagDescription;
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
    public function toArray(): array
    {
        return [
            'tag_name' => $this->tagName(),
            'tag_description' => $this->tagDescription(),
            'endpoints' => array_map(
                fn ($endpoint) => $endpoint->toArray(),
                $this->endpoints()
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
        return $this->tagName() ?? 'Other';
    }
}
