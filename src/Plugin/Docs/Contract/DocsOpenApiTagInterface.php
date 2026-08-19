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
 * OpenAPI top-level tag interface (used to group and describe endpoints,
 * the same "tags" Swagger UI groups its own operation list by).
 */
interface DocsOpenApiTagInterface extends JsonSerializable, Stringable
{
    /**
     * Get the name of the tag.
     *
     * @return string
     */
    public function name(): string;

    /**
     * Get the description of the tag, if any.
     *
     * @return string|null
     */
    public function description(): ?string;

    /**
     * Get the tag as an array.
     *
     * @return array
     */
    public function toArray(): array;

    /**
     * Get the tag as a JSON serializable array.
     *
     * @return array
     */
    public function jsonSerialize(): array;

    /**
     * Get the tag name as a string.
     *
     * @return string
     */
    public function __toString(): string;
}
