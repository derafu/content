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
use Derafu\Content\Plugin\Academy\Contract\AcademyTestQuestionInterface;

/**
 * Class that represents a question of an academy test.
 */
class AcademyTestQuestion implements AcademyTestQuestionInterface
{
    /**
     * Constructor.
     *
     * @param string $id ID of the question.
     * @param string $type Type of the question ("multiple_choice",
     * "true_false", ...).
     * @param string $text Text of the question.
     * @param array<AcademyTestOptionInterface> $options Options of the
     * question.
     * @param bool $allowMultiple Whether more than one option can be
     * selected as correct.
     * @param string|null $explanation Explanation of the correct answer.
     */
    public function __construct(
        private readonly string $id,
        private readonly string $type,
        private readonly string $text,
        private readonly array $options,
        private readonly bool $allowMultiple,
        private readonly ?string $explanation
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
    public function type(): string
    {
        return $this->type;
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
    public function options(): array
    {
        return $this->options;
    }

    /**
     * {@inheritDoc}
     */
    public function correctOptions(): array
    {
        return array_values(
            array_filter($this->options, fn ($option) => $option->isCorrect())
        );
    }

    /**
     * {@inheritDoc}
     */
    public function allowMultiple(): bool
    {
        return $this->allowMultiple;
    }

    /**
     * {@inheritDoc}
     */
    public function explanation(): ?string
    {
        return $this->explanation;
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id(),
            'type' => $this->type(),
            'text' => $this->text(),
            'options' => array_map(
                fn ($option) => $option->toArray(),
                $this->options()
            ),
            'allow_multiple' => $this->allowMultiple(),
            'explanation' => $this->explanation(),
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
