<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Contract;

use JsonSerializable;
use Stringable;

/**
 * Content month interface.
 */
interface ContentMonthInterface extends JsonSerializable, Stringable
{
    /**
     * Get the name of the month.
     *
     * @return string
     */
    public function name(): string;

    /**
     * Get the year of the month.
     *
     * @return int
     */
    public function year(): int;

    /**
     * Get the month of the month.
     *
     * @return int
     */
    public function month(): int;

    /**
     * Get the slug of the month.
     *
     * @return string
     */
    public function slug(): string;

    /**
     * Get the count of posts with this month.
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
     * Get the month as an array.
     *
     * @return array
     */
    public function toArray(): array;

    /**
     * Get the month as an array.
     *
     * @return array
     */
    public function jsonSerialize(): array;

    /**
     * Get the month as a string.
     *
     * @return string
     */
    public function __toString(): string;
}
