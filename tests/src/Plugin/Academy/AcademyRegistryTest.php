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

use Derafu\Content\ContentAttachment;
use Derafu\Content\ContentBag;
use Derafu\Content\ContentConfig;
use Derafu\Content\ContentContext;
use Derafu\Content\ContentLoader;
use Derafu\Content\ContentSplFileInfo;
use Derafu\Content\ContentTag;
use Derafu\Content\Contract\ContentAttachmentInterface;
use Derafu\Content\Exception\ContentNotFoundException;
use Derafu\Content\Plugin\Academy\AcademyCourse;
use Derafu\Content\Plugin\Academy\AcademyLesson;
use Derafu\Content\Plugin\Academy\AcademyModule;
use Derafu\Content\Plugin\Academy\AcademyPlugin;
use Derafu\Content\Plugin\Academy\AcademyRegistry;
use Derafu\TestsContent\Support\ContentFixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the Academy plugin's real three-level hierarchy (course →
 * module → lesson) against real fixture content on disk, no mocks: this
 * is exactly the shape of test that would have caught the Pages
 * registry/plugin bugs found this session, applied to Academy.
 */
#[CoversClass(AcademyPlugin::class)]
#[CoversClass(AcademyRegistry::class)]
#[CoversClass(AcademyCourse::class)]
#[CoversClass(AcademyModule::class)]
#[CoversClass(AcademyLesson::class)]
#[UsesClass(ContentAttachment::class)]
#[UsesClass(ContentBag::class)]
#[UsesClass(ContentConfig::class)]
#[UsesClass(ContentContext::class)]
#[UsesClass(ContentLoader::class)]
#[UsesClass(ContentSplFileInfo::class)]
#[UsesClass(ContentTag::class)]
#[UsesClass(ContentNotFoundException::class)]
final class AcademyRegistryTest extends TestCase
{
    private AcademyPlugin $plugin;

    protected function setUp(): void
    {
        $this->plugin = new AcademyPlugin(
            ContentFixtures::contentContext(),
            ['path' => 'academy']
        );
        $this->plugin->loadContent(new ContentLoader(ContentFixtures::contentPath()));
    }

    public function testCourseHierarchyIsBuiltFromTheFilesystem(): void
    {
        $course = $this->plugin->registry()->get('curso-demo');

        $this->assertInstanceOf(AcademyCourse::class, $course);
        $this->assertSame('Curso Demo', $course->title());
        $this->assertSame('academy', $course->type());
        $this->assertSame('course', $course->category());
        $this->assertCount(1, $course->modules());

        $module = $course->modules()['modulo-uno'];
        $this->assertInstanceOf(AcademyModule::class, $module);
        $this->assertSame($course, $module->course());
        $this->assertCount(2, $module->lessons());
    }

    public function testLessonWithExplicitTimeIsNotEstimated(): void
    {
        $course = $this->plugin->registry()->get('curso-demo');
        $lesson = $course->modules()['modulo-uno']->lessons()['leccion-uno'];

        $this->assertSame(7, $lesson->time());
    }

    public function testLessonWithoutExplicitTimeIsEstimatedFromWordCount(): void
    {
        $course = $this->plugin->registry()->get('curso-demo');
        $lesson = $course->modules()['modulo-uno']->lessons()['leccion-dos'];

        // No explicit "time" in the frontmatter: must be a positive estimate,
        // not zero and not the sibling lesson's explicit value.
        $this->assertGreaterThan(0, $lesson->time());
    }

    public function testModuleAndCourseTimeAreAggregatedFromLessons(): void
    {
        $course = $this->plugin->registry()->get('curso-demo');
        $module = $course->modules()['modulo-uno'];

        $lessonsTime = array_sum(array_map(
            fn ($lesson) => $lesson->time(),
            $module->lessons()
        ));

        $this->assertSame($lessonsTime, $module->time());
        $this->assertSame($lessonsTime, $course->time());
    }

    public function testYoutubeVideoUrlIsRewrittenToEmbedUrl(): void
    {
        $course = $this->plugin->registry()->get('curso-demo');
        $lesson = $course->modules()['modulo-uno']->lessons()['leccion-dos'];

        $this->assertSame(
            'https://www.youtube.com/embed/dQw4w9WgXcQ',
            $lesson->video()
        );
        $this->assertCount(1, $course->videos());
    }

    public function testLessonTestFieldResolvesToARealAttachment(): void
    {
        $course = $this->plugin->registry()->get('curso-demo');
        $lesson = $course->modules()['modulo-uno']->lessons()['leccion-uno'];

        $test = $lesson->test();

        $this->assertInstanceOf(ContentAttachmentInterface::class, $test);
        $this->assertSame('quiz.json', $test->name() . '.' . $test->extension());
        $this->assertStringContainsString('"questions"', $test->raw());
        $this->assertCount(1, $course->attachments());
    }

    public function testLessonWithoutTestUsesTheDefaultIcon(): void
    {
        $course = $this->plugin->registry()->get('curso-demo');
        $lessonWithVideo = $course->modules()['modulo-uno']->lessons()['leccion-dos'];
        $lessonWithTest = $course->modules()['modulo-uno']->lessons()['leccion-uno'];

        $this->assertSame('fa-solid fa-play fa-fw', $lessonWithVideo->icon());
        $this->assertSame('fa-solid fa-question-circle fa-fw', $lessonWithTest->icon());
    }

    public function testFilterByTagFindsOnlyMatchingItems(): void
    {
        $onlyQuiz = $this->plugin->registry()->filter(['tag' => 'quiz']);

        $this->assertCount(1, $onlyQuiz);
        $this->assertSame('leccion-uno', $onlyQuiz[0]->slug());
    }

    public function testUnknownUriThrowsContentNotFoundException(): void
    {
        $this->expectException(ContentNotFoundException::class);

        $this->plugin->registry()->get('no-existe');
    }
}
