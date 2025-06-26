<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Contract;

use JsonSerializable;
use Stringable;

/**
 * Content author interface.
 */
interface ContentAuthorInterface extends JsonSerializable, Stringable
{
    /**
     * Get the ID of the author.
     *
     * @return string
     */
    public function id(): string;

    /**
     * Get the name of the author.
     *
     * @return string
     */
    public function name(): string;

    /**
     * Get the slug of the author.
     *
     * If the slug is not set, it will be generated from the name.
     *
     * @return string
     */
    public function slug(): string;

    /**
     * Get the author as an array.
     *
     * @return array
     */
    public function toArray(): array;

    /**
     * Get the author as a JSON serializable array.
     *
     * @return array
     */
    public function jsonSerialize(): array;

    /**
     * Get the author as a string.
     *
     * @return string
     */
    public function __toString(): string;
}
