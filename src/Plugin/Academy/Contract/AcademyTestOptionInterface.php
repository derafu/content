<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Plugin\Academy\Contract;

use JsonSerializable;
use Stringable;

/**
 * Academy test question option interface.
 */
interface AcademyTestOptionInterface extends JsonSerializable, Stringable
{
    /**
     * Get the ID of the option.
     *
     * @return string
     */
    public function id(): string;

    /**
     * Get the text of the option.
     *
     * @return string
     */
    public function text(): string;

    /**
     * Whether this option is a correct answer to its question.
     *
     * @return bool
     */
    public function isCorrect(): bool;

    /**
     * Get the option as an array.
     *
     * @return array
     */
    public function toArray(): array;

    /**
     * Get the option as a JSON serializable array.
     *
     * @return array
     */
    public function jsonSerialize(): array;

    /**
     * Get the option text as a string.
     *
     * @return string
     */
    public function __toString(): string;
}
