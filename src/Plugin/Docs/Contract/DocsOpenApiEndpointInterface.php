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
 * OpenAPI operation (an HTTP method on a path) interface.
 */
interface DocsOpenApiEndpointInterface extends JsonSerializable, Stringable
{
    /**
     * Get the HTTP method of the endpoint ("GET", "POST", ...).
     *
     * @return string
     */
    public function method(): string;

    /**
     * Get the path of the endpoint (e.g. "/api/dte/documentos/emitir").
     *
     * @return string
     */
    public function path(): string;

    /**
     * Get the short summary of the endpoint, if any.
     *
     * @return string|null
     */
    public function summary(): ?string;

    /**
     * Get the longer description of the endpoint, if any.
     *
     * @return string|null
     */
    public function description(): ?string;

    /**
     * Get the tags of the endpoint, used to group it (the same tags
     * Swagger UI groups its own operation list by).
     *
     * @return array<string>
     */
    public function tags(): array;

    /**
     * Get every parameter of the endpoint.
     *
     * @return array<DocsOpenApiParameterInterface>
     */
    public function parameters(): array;

    /**
     * Get a printable example of the request body, if the spec declares
     * one (either directly or as the first named example).
     *
     * @return string|null
     */
    public function requestBodyExample(): ?string;

    /**
     * Get every declared response of the endpoint.
     *
     * @return array<DocsOpenApiResponseInterface>
     */
    public function responses(): array;

    /**
     * Get the endpoint as an array.
     *
     * @return array
     */
    public function toArray(): array;

    /**
     * Get the endpoint as a JSON serializable array.
     *
     * @return array
     */
    public function jsonSerialize(): array;

    /**
     * Get the endpoint as "METHOD /path".
     *
     * @return string
     */
    public function __toString(): string;
}
