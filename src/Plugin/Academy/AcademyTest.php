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

use Derafu\Content\Plugin\Academy\Contract\AcademyTestInterface;
use JsonException;
use RuntimeException;

/**
 * Class that represents an academy lesson's self-assessment test ("quiz"),
 * parsed from the JSON attachment referenced by its "test" metadata.
 */
class AcademyTest implements AcademyTestInterface
{
    /**
     * Constructor.
     *
     * @param string|null $id ID of the test.
     * @param string $title Title of the test.
     * @param string|null $description Description of the test.
     * @param array<AcademyTestQuestion> $questions Questions of the test.
     */
    public function __construct(
        private readonly ?string $id,
        private readonly string $title,
        private readonly ?string $description,
        private readonly array $questions
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function id(): ?string
    {
        return $this->id;
    }

    /**
     * {@inheritDoc}
     */
    public function title(): string
    {
        return $this->title;
    }

    /**
     * {@inheritDoc}
     */
    public function description(): ?string
    {
        return $this->description;
    }

    /**
     * {@inheritDoc}
     */
    public function questions(): array
    {
        return $this->questions;
    }

    /**
     * {@inheritDoc}
     */
    public static function fromJson(string $json): self
    {
        try {
            $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException(sprintf(
                'Invalid academy test JSON: %s',
                $e->getMessage()
            ), previous: $e);
        }

        $questions = array_map(
            fn (array $question) => self::questionFromArray($question),
            $data['questions'] ?? []
        );

        return new self(
            id: $data['id'] ?? null,
            title: $data['title'] ?? '',
            description: $data['description'] ?? null,
            questions: $questions
        );
    }

    /**
     * Builds a single AcademyTestQuestion from its raw array shape.
     *
     * @param array $question Raw question data.
     * @return AcademyTestQuestion
     */
    private static function questionFromArray(array $question): AcademyTestQuestion
    {
        $type = $question['type'] ?? 'multiple_choice';

        return new AcademyTestQuestion(
            id: $question['id'] ?? '',
            type: $type,
            text: $question['text'] ?? '',
            options: self::optionsFromArray($type, $question),
            allowMultiple: (bool) ($question['allow_multiple'] ?? false),
            explanation: $question['explanation'] ?? null
        );
    }

    /**
     * Builds the options of a question from its raw array shape.
     *
     * "true_false" questions have no "options" list in the source JSON,
     * just a boolean "answer" — normalized here into two options
     * ("True"/"False") so every question can be rendered the same way
     * regardless of its type.
     *
     * @param string $type Type of the question.
     * @param array $question Raw question data.
     * @return array<AcademyTestOption>
     */
    private static function optionsFromArray(string $type, array $question): array
    {
        if ($type === 'true_false') {
            $answer = $question['answer'] ?? null;

            return [
                new AcademyTestOption('true', 'True', $answer === true),
                new AcademyTestOption('false', 'False', $answer === false),
            ];
        }

        return array_map(
            fn (array $option) => new AcademyTestOption(
                $option['id'] ?? '',
                $option['text'] ?? '',
                (bool) ($option['is_correct'] ?? false)
            ),
            $question['options'] ?? []
        );
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id(),
            'title' => $this->title(),
            'description' => $this->description(),
            'questions' => array_map(
                fn ($question) => $question->toArray(),
                $this->questions()
            ),
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
        return $this->title();
    }
}
