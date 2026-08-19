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
 * OpenAPI operation parameter interface.
 */
interface DocsOpenApiParameterInterface extends JsonSerializable, Stringable
{
    /**
     * Get the name of the parameter.
     *
     * @return string
     */
    public function name(): string;

    /**
     * Get where the parameter is sent ("query", "path", "header",
     * "cookie", as declared in the source spec).
     *
     * @return string
     */
    public function in(): string;

    /**
     * Get whether the parameter is required.
     *
     * @return bool
     */
    public function required(): bool;

    /**
     * Get the description of the parameter, if any.
     *
     * @return string|null
     */
    public function description(): ?string;

    /**
     * Get the type of the parameter's schema, if any (e.g. "string",
     * "integer"). Not resolved through a "$ref".
     *
     * @return string|null
     */
    public function type(): ?string;

    /**
     * Get the parameter as an array.
     *
     * @return array
     */
    public function toArray(): array;

    /**
     * Get the parameter as a JSON serializable array.
     *
     * @return array
     */
    public function jsonSerialize(): array;

    /**
     * Get the parameter name as a string.
     *
     * @return string
     */
    public function __toString(): string;
}
