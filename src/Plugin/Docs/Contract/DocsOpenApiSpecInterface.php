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
 * OpenAPI document interface, parsed from a doc's "openapi" frontmatter
 * reference.
 */
interface DocsOpenApiSpecInterface extends JsonSerializable, Stringable
{
    /**
     * Get the title of the API.
     *
     * @return string
     */
    public function title(): string;

    /**
     * Get the description of the API, if any.
     *
     * @return string|null
     */
    public function description(): ?string;

    /**
     * Get the version of the API, if any.
     *
     * @return string|null
     */
    public function version(): ?string;

    /**
     * Get the server URLs of the API.
     *
     * @return array<string>
     */
    public function servers(): array;

    /**
     * Get every endpoint (one per HTTP method on a path) of the API.
     *
     * @return array<DocsOpenApiEndpointInterface>
     */
    public function endpoints(): array;

    /**
     * Get the top-level tags of the API (name + description), used to
     * group and describe endpoints.
     *
     * @return array<DocsOpenApiTagInterface>
     */
    public function tags(): array;

    /**
     * Get every endpoint grouped by its primary (first) tag, in the
     * order the top-level "tags" declared them — the same grouping
     * Swagger UI shows. Endpoints with no tag at all end up in one
     * final group with a null tag name.
     *
     * @return array<DocsOpenApiEndpointGroupInterface>
     */
    public function endpointGroups(): array;

    /**
     * Get the spec as an array.
     *
     * @return array
     */
    public function toArray(): array;

    /**
     * Get the spec as a JSON serializable array.
     *
     * @return array
     */
    public function jsonSerialize(): array;

    /**
     * Get the API title as a string.
     *
     * @return string
     */
    public function __toString(): string;
}
