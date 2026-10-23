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

use Derafu\Content\Abstract\AbstractContentItem;
use Derafu\Content\ContentSplFileInfo;
use Derafu\Content\Plugin\Academy\AcademyLesson;
use Derafu\Content\Plugin\Academy\AcademyModule;
use Derafu\Translation\Contract\TranslatableInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

/**
 * The route of a lesson has its module and its course, and the one of a module
 * has its course: a content that is not in them has no route, and it is an
 * error that says which content it is.
 */
#[CoversClass(AcademyLesson::class)]
#[CoversClass(AcademyModule::class)]
#[UsesClass(AbstractContentItem::class)]
#[UsesClass(ContentSplFileInfo::class)]
final class AcademyRouteWithoutParentTest extends TestCase
{
    private const FILE = __DIR__ . '/../../../fixtures/standalone/academy-lesson-unreachable-test.md';

    private function failureOf(callable $call): Throwable
    {
        try {
            $call();
        } catch (Throwable $e) {
            return $e;
        }

        $this->fail('The route was built.');
    }

    public function testALessonWithoutModuleHasNoRoute(): void
    {
        $exception = $this->failureOf(fn () => (new AcademyLesson(self::FILE))->route());

        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertInstanceOf(TranslatableInterface::class, $exception);
        $this->assertSame(
            'The lesson "academy-lesson-unreachable-test" must be in a module of a course to have a route.',
            $exception->getMessage()
        );
    }

    public function testAModuleWithoutCourseHasNoRoute(): void
    {
        $exception = $this->failureOf(fn () => (new AcademyModule(self::FILE))->route());

        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertInstanceOf(TranslatableInterface::class, $exception);
        $this->assertSame(
            'The module "academy-lesson-unreachable-test" must be in a course to have a route.',
            $exception->getMessage()
        );
    }
}
