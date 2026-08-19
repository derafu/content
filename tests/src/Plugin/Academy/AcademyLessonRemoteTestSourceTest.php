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
use Derafu\Content\Plugin\Academy\AcademyLesson;
use Derafu\Content\Plugin\Academy\AcademyTest;
use Derafu\Content\Plugin\Academy\AcademyTestOption;
use Derafu\Content\Plugin\Academy\AcademyTestQuestion;
use Derafu\Content\RemoteContentFetcher;
use Derafu\TestsContent\Support\FixtureHttpServer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Exercises AcademyLesson::test() when "test" is an absolute URL instead
 * of a local attachment (e.g. a quiz hosted on another site): the
 * fallback wiring in AcademyLesson itself, not RemoteContentFetcher's own
 * error handling (covered separately by RemoteContentFetcherTest).
 *
 * The fixture lesson is built directly from a standalone file (not the
 * shared tests/fixtures/content/academy/ tree walked by AcademyPlugin's
 * registry), so unrelated tests that render a full course/module listing
 * never touch this lesson and never trigger its HTTP fetch.
 */
#[CoversClass(AcademyLesson::class)]
#[UsesClass(AbstractContentItem::class)]
#[UsesClass(AcademyTest::class)]
#[UsesClass(AcademyTestOption::class)]
#[UsesClass(AcademyTestQuestion::class)]
#[UsesClass(RemoteContentFetcher::class)]
final class AcademyLessonRemoteTestSourceTest extends TestCase
{
    private static FixtureHttpServer $server;

    public static function setUpBeforeClass(): void
    {
        self::$server = FixtureHttpServer::start(19802);
    }

    public static function tearDownAfterClass(): void
    {
        self::$server->stop();
    }

    private function lesson(): AcademyLesson
    {
        return new AcademyLesson(
            dirname(__DIR__, 3) . '/fixtures/standalone/academy-lesson-remote-test.md'
        );
    }

    private function lessonWithUnreachableTest(): AcademyLesson
    {
        return new AcademyLesson(
            dirname(__DIR__, 3) . '/fixtures/standalone/academy-lesson-unreachable-test.md'
        );
    }

    public function testTestFieldAsARemoteUrlHasNoLocalAttachment(): void
    {
        $lesson = $this->lesson();

        $this->assertNull($lesson->testAttachment());
    }

    public function testTestFieldAsARemoteUrlIsFetchedAndParsed(): void
    {
        $lesson = $this->lesson();

        $test = $lesson->test();

        $this->assertNotNull($test);
        $this->assertSame('Cuestionario remoto', $test->title());
        $this->assertCount(1, $test->questions());
        $this->assertSame(
            'Sí, fue servido por el fixture HTTP de los tests.',
            $test->questions()[0]->explanation()
        );
    }

    public function testIconDoesNotFetchAnUnreachableRemoteTestUrl(): void
    {
        // icon() must decide from metadata('test') alone, never test():
        // resolving the full test would fetch this URL over HTTP just to
        // pick an icon, and this one has nothing listening on it.
        $lesson = $this->lessonWithUnreachableTest();

        $this->assertSame('fa-solid fa-question-circle fa-fw', $lesson->icon());
    }
}
