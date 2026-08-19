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
 * OpenAPI operation response interface.
 */
interface DocsOpenApiResponseInterface extends JsonSerializable, Stringable
{
    /**
     * Get the HTTP status code of the response ("200", "404", "default",
     * as declared in the source spec).
     *
     * @return string
     */
    public function statusCode(): string;

    /**
     * Get the description of the response, if any.
     *
     * @return string|null
     */
    public function description(): ?string;

    /**
     * Get the response as an array.
     *
     * @return array
     */
    public function toArray(): array;

    /**
     * Get the response as a JSON serializable array.
     *
     * @return array
     */
    public function jsonSerialize(): array;

    /**
     * Get the status code as a string.
     *
     * @return string
     */
    public function __toString(): string;
}
