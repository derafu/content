<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent\Plugin\Academy;

use Derafu\Content\Plugin\Academy\AcademyTest;
use Derafu\Content\Plugin\Academy\AcademyTestOption;
use Derafu\Content\Plugin\Academy\AcademyTestQuestion;
use Derafu\Translation\Contract\TranslatableInterface;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * A test (quiz) is written by the author of the content in the JSON format of
 * derafu/content: what is missing takes its default, and what has the wrong
 * type is an error that says which key it is.
 */
#[CoversClass(AcademyTest::class)]
#[UsesClass(AcademyTestOption::class)]
#[UsesClass(AcademyTestQuestion::class)]
final class AcademyTestParsingTest extends TestCase
{
    public function testWhatIsMissingTakesItsDefault(): void
    {
        $test = AcademyTest::fromJson('{"questions": [{"options": [{}]}, {"type": "true_false"}]}');

        $this->assertNull($test->id());
        $this->assertSame('', $test->title());
        $this->assertNull($test->description());

        [$choice, $trueFalse] = $test->questions();

        $this->assertSame('', $choice->id());
        $this->assertSame('multiple_choice', $choice->type());
        $this->assertSame('', $choice->text());
        $this->assertFalse($choice->allowMultiple());
        $this->assertNull($choice->explanation());
        $this->assertSame('', $choice->options()[0]->id());
        $this->assertSame('', $choice->options()[0]->text());
        $this->assertFalse($choice->options()[0]->isCorrect());

        $this->assertSame([false, false], [
            $trueFalse->options()[0]->isCorrect(),
            $trueFalse->options()[1]->isCorrect(),
        ]);
    }

    public function testATestWithoutQuestionsIsEmpty(): void
    {
        $this->assertSame([], AcademyTest::fromJson('{}')->questions());
        $this->assertSame([], AcademyTest::fromJson('[]')->questions());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function provideTestsThatAreNotValid(): array
    {
        return [
            'a number instead of an object' => [
                '5',
                'The academy test must be a JSON object, int given.',
            ],
            'a text instead of an object' => [
                '"quiz"',
                'The academy test must be a JSON object, string given.',
            ],
            'null instead of an object' => [
                'null',
                'The academy test must be a JSON object, null given.',
            ],
            'a list instead of an object' => [
                '[{"id": "q1"}]',
                'The academy test must be a JSON object, list given.',
            ],
            'an id that is not a text' => [
                '{"id": 5}',
                'The key "id" must be of type string, int given.',
            ],
            'a title that is not a text' => [
                '{"title": ["Quiz"]}',
                'The key "title" must be of type string, array given.',
            ],
            'a description that is not a text' => [
                '{"description": true}',
                'The key "description" must be of type string, bool given.',
            ],
            'questions that are not a list' => [
                '{"questions": "q1"}',
                'The key "questions" must be of type list<object>, string given.',
            ],
            'a question that is not an object' => [
                '{"questions": [{"id": "q1"}, "q2"]}',
                'The key "questions.1" must be of type object, string given.',
            ],
            'a question id that is not a text' => [
                '{"questions": [{"id": 1}]}',
                'The key "questions.0.id" must be of type string, int given.',
            ],
            'a question type that is not a text' => [
                '{"questions": [{"type": 2}]}',
                'The key "questions.0.type" must be of type string, int given.',
            ],
            'a question text that is not a text' => [
                '{"questions": [{"text": null, "id": "q"}, {"text": {}}]}',
                'The key "questions.1.text" must be of type string, array given.',
            ],
            'allow_multiple that is not true or false' => [
                '{"questions": [{"allow_multiple": "false"}]}',
                'The key "questions.0.allow_multiple" must be of type bool, string given.',
            ],
            'an explanation that is not a text' => [
                '{"questions": [{"explanation": 3}]}',
                'The key "questions.0.explanation" must be of type string, int given.',
            ],
            'an answer that is not true or false' => [
                '{"questions": [{"type": "true_false", "answer": "true"}]}',
                'The key "questions.0.answer" must be of type bool, string given.',
            ],
            'options that are not a list' => [
                '{"questions": [{"options": "a"}]}',
                'The key "questions.0.options" must be of type list<object>, string given.',
            ],
            'an option that is not an object' => [
                '{"questions": [{"options": [{"id": "a"}, "b"]}]}',
                'The key "questions.0.options.1" must be of type object, string given.',
            ],
            'an option id that is not a text' => [
                '{"questions": [{"options": [{"id": 1}]}]}',
                'The key "questions.0.options.0.id" must be of type string, int given.',
            ],
            'an option text that is not a text' => [
                '{"questions": [{"options": [{"text": false}]}]}',
                'The key "questions.0.options.0.text" must be of type string, bool given.',
            ],
            'is_correct that is not true or false' => [
                '{"questions": [{"options": [{"is_correct": 1}]}]}',
                'The key "questions.0.options.0.is_correct" must be of type bool, int given.',
            ],
        ];
    }

    #[DataProvider('provideTestsThatAreNotValid')]
    public function testATestThatIsNotValidIsATranslatableErrorThatSaysWhy(string $json, string $message): void
    {
        $exception = null;
        try {
            AcademyTest::fromJson($json);
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(InvalidArgumentException::class, $exception);
        $this->assertInstanceOf(TranslatableInterface::class, $exception);
        $this->assertSame($message, $exception->getMessage());
    }

    public function testItIsExportedWithItsQuestionsAndTheTrueOrFalseOnesHaveTwoOptions(): void
    {
        $json = file_get_contents(dirname(__DIR__, 3) . '/fixtures/content/academy/demo-course/module-one/lesson-one/_attachments/quiz.json');
        $this->assertIsString($json);

        $test = AcademyTest::fromJson($json);
        $data = $test->toArray();
        [$choice, $trueFalse] = $test->questions();

        $this->assertSame('quiz-fixture', $data['id']);
        $this->assertSame('Test quiz', $data['title']);
        $this->assertCount(2, $test->questions());
        $this->assertSame('multiple_choice', $choice->type());
        $this->assertSame(['a'], array_map(fn ($option) => $option->id(), $choice->correctOptions()));
        $this->assertSame(
            [['id' => 'true', 'text' => 'True', 'is_correct' => false], ['id' => 'false', 'text' => 'False', 'is_correct' => true]],
            array_map(fn ($option) => $option->toArray(), $trueFalse->options())
        );
        $this->assertSame(json_encode($data), json_encode($test));
        $this->assertSame('Test quiz', (string) $test);
    }
}
