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
 * Academy test question interface.
 */
interface AcademyTestQuestionInterface extends JsonSerializable, Stringable
{
    /**
     * Get the ID of the question.
     *
     * @return string
     */
    public function id(): string;

    /**
     * Get the type of the question ("multiple_choice", "true_false", ...
     * as declared in the source JSON).
     *
     * @return string
     */
    public function type(): string;

    /**
     * Get the text of the question.
     *
     * @return string
     */
    public function text(): string;

    /**
     * Get every option of the question.
     *
     * "true_false" questions have no "options" in the source JSON (just a
     * boolean "answer"); those are normalized into two options here
     * ("True"/"False"), so every question can be rendered the same
     * way regardless of its type.
     *
     * @return array<AcademyTestOptionInterface>
     */
    public function options(): array;

    /**
     * Get only the correct option(s) of the question.
     *
     * @return array<AcademyTestOptionInterface>
     */
    public function correctOptions(): array;

    /**
     * Whether more than one option can be selected as correct.
     *
     * @return bool
     */
    public function allowMultiple(): bool;

    /**
     * Get the explanation of the correct answer, if any.
     *
     * @return string|null
     */
    public function explanation(): ?string;

    /**
     * Get the question as an array.
     *
     * @return array
     */
    public function toArray(): array;

    /**
     * Get the question as a JSON serializable array.
     *
     * @return array
     */
    public function jsonSerialize(): array;

    /**
     * Get the question text as a string.
     *
     * @return string
     */
    public function __toString(): string;
}
