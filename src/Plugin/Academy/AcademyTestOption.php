<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Plugin\Academy;

use Derafu\Content\Plugin\Academy\Contract\AcademyTestOptionInterface;

/**
 * Class that represents an option of an academy test question.
 *
 * For "true_false" questions, AcademyTest normalizes the single boolean
 * "answer" field into two of these ("True"/"False"), so consumers
 * (templates, the PDF export) can always iterate options() the same way
 * regardless of the question type.
 */
class AcademyTestOption implements AcademyTestOptionInterface
{
    /**
     * Constructor.
     *
     * @param string $id ID of the option.
     * @param string $text Text of the option.
     * @param bool $isCorrect Whether this option is a correct answer.
     */
    public function __construct(
        private readonly string $id,
        private readonly string $text,
        private readonly bool $isCorrect
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * {@inheritDoc}
     */
    public function text(): string
    {
        return $this->text;
    }

    /**
     * {@inheritDoc}
     */
    public function isCorrect(): bool
    {
        return $this->isCorrect;
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id(),
            'text' => $this->text(),
            'is_correct' => $this->isCorrect(),
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * {@inheritDoc}
     */
    public function __toString(): string
    {
        return $this->text();
    }
}
