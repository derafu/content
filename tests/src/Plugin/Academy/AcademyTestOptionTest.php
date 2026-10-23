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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * An option of a question says if it is a right answer.
 */
#[CoversClass(AcademyTestOption::class)]
final class AcademyTestOptionTest extends TestCase
{
    public function testItKeepsWhatItWasGiven(): void
    {
        $option = new AcademyTestOption('a', 'Apple', true);

        $this->assertSame('a', $option->id());
        $this->assertSame('Apple', $option->text());
        $this->assertTrue($option->isCorrect());
        $this->assertSame('Apple', (string) $option);
        $this->assertFalse((new AcademyTestOption('b', 'Table', false))->isCorrect());
    }

    public function testItIsExportedAsAnArrayAndAsJson(): void
    {
        $option = new AcademyTestOption('a', 'Apple', true);
        $expected = ['id' => 'a', 'text' => 'Apple', 'is_correct' => true];

        $this->assertSame($expected, $option->toArray());
        $this->assertSame(json_encode($expected), json_encode($option));
    }
}
