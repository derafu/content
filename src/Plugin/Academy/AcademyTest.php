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
use Derafu\Support\ArrayReader;
use Derafu\Translation\Exception\Core\TranslatableRuntimeException as RuntimeException;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException as InvalidArgumentException;
use JsonException;

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
            throw new RuntimeException([
                'Invalid academy test JSON: {error}',
                'error' => $e->getMessage(),
            ], previous: $e);
        }

        if (!is_array($data) || ($data !== [] && array_is_list($data))) {
            throw new InvalidArgumentException([
                'The academy test must be a JSON object, {type} given.',
                'type' => is_array($data) ? 'list' : get_debug_type($data),
            ]);
        }

        $reader = ArrayReader::from($data);

        $questions = array_map(
            fn (ArrayReader $question) => self::questionFromReader($question),
            $reader->readers('questions') ?? []
        );

        return new self(
            id: $reader->string('id'),
            title: $reader->string('title', ''),
            description: $reader->string('description'),
            questions: $questions
        );
    }

    /**
     * Builds a single AcademyTestQuestion from its reader.
     *
     * @param ArrayReader $question Reader of the raw question data.
     * @return AcademyTestQuestion
     */
    private static function questionFromReader(ArrayReader $question): AcademyTestQuestion
    {
        $type = $question->string('type', 'multiple_choice');

        return new AcademyTestQuestion(
            id: $question->string('id', ''),
            type: $type,
            text: $question->string('text', ''),
            options: self::optionsFromReader($type, $question),
            allowMultiple: $question->bool('allow_multiple', false),
            explanation: $question->string('explanation')
        );
    }

    /**
     * Builds the options of a question from its reader.
     *
     * "true_false" questions have no "options" list in the source JSON,
     * just a boolean "answer" — normalized here into two options
     * ("True"/"False") so every question can be rendered the same way
     * regardless of its type.
     *
     * @param string $type Type of the question.
     * @param ArrayReader $question Reader of the raw question data.
     * @return array<AcademyTestOption>
     */
    private static function optionsFromReader(string $type, ArrayReader $question): array
    {
        if ($type === 'true_false') {
            $answer = $question->bool('answer');

            return [
                new AcademyTestOption('true', 'True', $answer === true),
                new AcademyTestOption('false', 'False', $answer === false),
            ];
        }

        return array_map(
            fn (ArrayReader $option) => new AcademyTestOption(
                $option->string('id', ''),
                $option->string('text', ''),
                $option->bool('is_correct', false)
            ),
            $question->readers('options') ?? []
        );
    }

    /**
     * {@inheritDoc}
     *
     * @return array<string, mixed>
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
     *
     * @return array<string, mixed>
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
