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
 * Academy test interface.
 *
 * Represents a lesson's self-assessment test ("quiz"), parsed from its
 * JSON attachment (see AcademyLessonInterface::test()).
 */
interface AcademyTestInterface extends JsonSerializable, Stringable
{
    /**
     * Get the ID of the test.
     *
     * @return string|null
     */
    public function id(): ?string;

    /**
     * Get the title of the test.
     *
     * @return string
     */
    public function title(): string;

    /**
     * Get the description of the test.
     *
     * @return string|null
     */
    public function description(): ?string;

    /**
     * Get every question of the test.
     *
     * @return array<AcademyTestQuestionInterface>
     */
    public function questions(): array;

    /**
     * Parse a test from its raw JSON representation.
     *
     * @param string $json Raw JSON content of the test attachment.
     * @return self
     */
    public static function fromJson(string $json): self;

    /**
     * Get the test as an array.
     *
     * @return array
     */
    public function toArray(): array;

    /**
     * Get the test as a JSON serializable array.
     *
     * @return array
     */
    public function jsonSerialize(): array;

    /**
     * Get the test title as a string.
     *
     * @return string
     */
    public function __toString(): string;
}
