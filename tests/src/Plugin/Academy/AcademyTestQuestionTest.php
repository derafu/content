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

use Derafu\Content\Plugin\Academy\AcademyTestOption;
use Derafu\Content\Plugin\Academy\AcademyTestQuestion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * A question of a test knows which of its options are the right ones, and is
 * exported with all its options.
 */
#[CoversClass(AcademyTestQuestion::class)]
#[UsesClass(AcademyTestOption::class)]
final class AcademyTestQuestionTest extends TestCase
{
    private function question(bool $allowMultiple = false, ?string $explanation = null): AcademyTestQuestion
    {
        return new AcademyTestQuestion(
            'q1',
            'multiple_choice',
            'Which are fruits?',
            [
                new AcademyTestOption('a', 'Apple', true),
                new AcademyTestOption('b', 'Table', false),
                new AcademyTestOption('c', 'Pear', true),
            ],
            $allowMultiple,
            $explanation
        );
    }

    public function testItKeepsWhatItWasGiven(): void
    {
        $question = $this->question(true, 'Apples and pears are fruits.');

        $this->assertSame('q1', $question->id());
        $this->assertSame('multiple_choice', $question->type());
        $this->assertSame('Which are fruits?', $question->text());
        $this->assertSame('Which are fruits?', (string) $question);
        $this->assertCount(3, $question->options());
        $this->assertTrue($question->allowMultiple());
        $this->assertSame('Apples and pears are fruits.', $question->explanation());
    }

    public function testWithoutExplanationItIsNull(): void
    {
        $this->assertNull($this->question()->explanation());
        $this->assertFalse($this->question()->allowMultiple());
    }

    public function testTheCorrectOptionsAreOnlyTheRightOnes(): void
    {
        $correct = $this->question()->correctOptions();

        $this->assertSame(['a', 'c'], array_map(fn ($option) => $option->id(), $correct));
        $this->assertSame([0, 1], array_keys($correct));
    }

    public function testItIsExportedWithItsOptions(): void
    {
        $expected = [
            'id' => 'q1',
            'type' => 'multiple_choice',
            'text' => 'Which are fruits?',
            'options' => [
                ['id' => 'a', 'text' => 'Apple', 'is_correct' => true],
                ['id' => 'b', 'text' => 'Table', 'is_correct' => false],
                ['id' => 'c', 'text' => 'Pear', 'is_correct' => true],
            ],
            'allow_multiple' => false,
            'explanation' => null,
        ];

        $this->assertSame($expected, $this->question()->toArray());
        $this->assertSame(json_encode($expected), json_encode($this->question()));
    }
}
