<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Plugin\Blog\Contract;

use JsonSerializable;
use Stringable;

/**
 * Content archive interface.
 */
interface BlogArchiveInterface extends JsonSerializable, Stringable
{
    /**
     * Get the ID of the archive.
     *
     * @return string
     */
    public function id(): string;

    /**
     * Get the name of the archive.
     *
     * @return string
     */
    public function name(): string;

    /**
     * Get the year of the archive.
     *
     * @return int
     */
    public function year(): int;

    /**
     * Get the month of the archive.
     *
     * @return int
     */
    public function month(): int;

    /**
     * Get the slug of the archive.
     *
     * @return string
     */
    public function slug(): string;

    /**
     * Get the count of items in the archive.
     *
     * @return int
     */
    public function count(): int;

    /**
     * Increment the count of items.
     *
     * @return void
     */
    public function increment(): void;

    /**
     * Get the archive as an array.
     *
     * @return array
     */
    public function toArray(): array;

    /**
     * Get the archive as an array.
     *
     * @return array
     */
    public function jsonSerialize(): array;

    /**
     * Get the archive as a string.
     *
     * @return string
     */
    public function __toString(): string;
}
