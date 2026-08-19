<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Plugin\Docs\Contract;

use JsonSerializable;
use Stringable;

/**
 * A group of OpenAPI endpoints sharing the same primary tag interface
 * (DocsOpenApiSpecInterface::endpointGroups()), mirroring how Swagger UI
 * groups its own operation list.
 */
interface DocsOpenApiEndpointGroupInterface extends JsonSerializable, Stringable
{
    /**
     * Get the name of the tag this group is for, or null for the group
     * of endpoints that declare no tag at all.
     *
     * @return string|null
     */
    public function tagName(): ?string;

    /**
     * Get the description of the tag this group is for, if the spec
     * declared one at the top level.
     *
     * @return string|null
     */
    public function tagDescription(): ?string;

    /**
     * Get every endpoint of the group.
     *
     * @return array<DocsOpenApiEndpointInterface>
     */
    public function endpoints(): array;

    /**
     * Get the group as an array.
     *
     * @return array
     */
    public function toArray(): array;

    /**
     * Get the group as a JSON serializable array.
     *
     * @return array
     */
    public function jsonSerialize(): array;

    /**
     * Get the tag name (or "Other" if none) as a string.
     *
     * @return string
     */
    public function __toString(): string;
}
