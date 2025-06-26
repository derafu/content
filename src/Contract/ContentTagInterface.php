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
 * Content tag interface.
 */
interface ContentTagInterface extends JsonSerializable, Stringable
{
    /**
     * Get the ID of the tag.
     *
     * @return string
     */
    public function id(): string;

    /**
     * Get the name of the tag.
     *
     * @return string
     */
    public function name(): string;

    /**
     * Get the slug of the tag.
     *
     * @return string
     */
    public function slug(): string;

    /**
     * Get the count of posts with this tag.
     *
     * @return int
     */
    public function count(): int;

    /**
     * Increment the count of posts.
     *
     * @return void
     */
    public function increment(): void;

    /**
     * Get the tag as an array.
     *
     * @return array
     */
    public function toArray(): array;

    /**
     * Get the tag as an array.
     *
     * @return array
     */
    public function jsonSerialize(): array;

    /**
     * Get the tag as a string.
     *
     * @return string
     */
    public function __toString(): string;
}
