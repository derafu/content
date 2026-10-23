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
use Derafu\Content\Plugin\Academy\AcademyTest;
use Derafu\Content\Plugin\Academy\AcademyTestOption;
use Derafu\Content\Plugin\Academy\AcademyTestQuestion;
use Derafu\Content\Plugin\Academy\Contract\AcademyTestInterface;
use Derafu\TestsContent\Support\ContentFixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the Academy plugin's real three-level hierarchy (course →
 * module → lesson) against real fixture content on disk, no mocks.
 */
#[CoversClass(AcademyPlugin::class)]
#[CoversClass(AcademyRegistry::class)]
#[CoversClass(AcademyCourse::class)]
#[CoversClass(AcademyModule::class)]
#[CoversClass(AcademyLesson::class)]
#[UsesClass(AcademyTest::class)]
#[UsesClass(AcademyTestQuestion::class)]
#[UsesClass(AcademyTestOption::class)]
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
        $course = $this->plugin->registry()->get('demo-course');

        $this->assertInstanceOf(AcademyCourse::class, $course);
        $this->assertSame('Demo Course', $course->title());
        $this->assertSame('academy', $course->type());
        $this->assertSame('course', $course->category());
        $this->assertCount(1, $course->modules());

        $module = $course->modules()['module-one'];
        $this->assertInstanceOf(AcademyModule::class, $module);
        $this->assertSame($course, $module->course());
        $this->assertCount(2, $module->lessons());
    }

    /**
     * AcademyRegistry::get() used to be typed to always return
     * AcademyCourseInterface, asserting on it — so any real MCP/CLI
     * caller passing a full module or lesson URI (not just a bare course
     * slug) crashed with an AssertionError/TypeError instead of getting
     * the item, since AbstractContentRegistry::get() walks the full URI
     * and returns whatever is actually at that depth. Only the course-
     * level lookup was ever exercised before this, since AcademyController
     * always fetches the course first and navigates modules()/lessons()
     * manually — this is exactly the path a generic MCP get_content(uri)
     * call takes instead.
     */
    public function testGetResolvesAModuleOrLessonUriNotJustACourse(): void
    {
        $module = $this->plugin->registry()->get('demo-course/module-one');
        $this->assertInstanceOf(AcademyModule::class, $module);
        $this->assertSame('module-one', $module->slug());

        $lesson = $this->plugin->registry()->get('demo-course/module-one/lesson-one');
        $this->assertInstanceOf(AcademyLesson::class, $lesson);
        $this->assertSame('lesson-one', $lesson->slug());
    }

    public function testLessonWithExplicitTimeIsNotEstimated(): void
    {
        $course = $this->plugin->registry()->get('demo-course');
        $this->assertInstanceOf(AcademyCourse::class, $course);
        $lesson = $course->modules()['module-one']->lessons()['lesson-one'];

        $this->assertSame(7, $lesson->time());
    }

    public function testLessonWithoutExplicitTimeIsEstimatedFromWordCount(): void
    {
        $course = $this->plugin->registry()->get('demo-course');
        $this->assertInstanceOf(AcademyCourse::class, $course);
        $lesson = $course->modules()['module-one']->lessons()['lesson-two'];

        // No explicit "time" in the frontmatter: must be a positive estimate,
        // not zero and not the sibling lesson's explicit value.
        $this->assertGreaterThan(0, $lesson->time());
    }

    public function testModuleAndCourseTimeAreAggregatedFromLessons(): void
    {
        $course = $this->plugin->registry()->get('demo-course');
        $this->assertInstanceOf(AcademyCourse::class, $course);
        $module = $course->modules()['module-one'];

        $lessonsTime = array_sum(array_map(
            fn ($lesson) => $lesson->time(),
            $module->lessons()
        ));

        $this->assertSame($lessonsTime, $module->time());
        $this->assertSame($lessonsTime, $course->time());
    }

    public function testYoutubeVideoUrlIsRewrittenToEmbedUrl(): void
    {
        $course = $this->plugin->registry()->get('demo-course');
        $this->assertInstanceOf(AcademyCourse::class, $course);
        $lesson = $course->modules()['module-one']->lessons()['lesson-two'];

        $this->assertSame(
            'https://www.youtube.com/embed/dQw4w9WgXcQ',
            $lesson->video()
        );
        $this->assertCount(1, $course->videos());
    }

    public function testLessonTestFieldResolvesToARealAttachment(): void
    {
        $course = $this->plugin->registry()->get('demo-course');
        $this->assertInstanceOf(AcademyCourse::class, $course);
        $lesson = $course->modules()['module-one']->lessons()['lesson-one'];

        $attachment = $lesson->testAttachment();

        $this->assertInstanceOf(ContentAttachmentInterface::class, $attachment);
        $this->assertSame('quiz.json', $attachment->name() . '.' . $attachment->extension());
        $this->assertStringContainsString('"questions"', $attachment->raw());
        $this->assertCount(1, $course->attachments());
    }

    /**
     * lesson->test() must parse the attachment's real JSON (both a
     * "multiple_choice" and a "true_false" question) into a real
     * AcademyTestInterface tree, not just resolve the raw attachment.
     */
    public function testLessonTestParsesTheAttachmentIntoARealTestObject(): void
    {
        $course = $this->plugin->registry()->get('demo-course');
        $this->assertInstanceOf(AcademyCourse::class, $course);
        $lesson = $course->modules()['module-one']->lessons()['lesson-one'];

        $test = $lesson->test();

        $this->assertInstanceOf(AcademyTestInterface::class, $test);
        $this->assertSame('Test quiz', $test->title());
        $this->assertCount(2, $test->questions());

        $multipleChoice = $test->questions()[0];
        $this->assertSame('multiple_choice', $multipleChoice->type());
        $this->assertCount(2, $multipleChoice->options());
        $this->assertCount(1, $multipleChoice->correctOptions());
        $this->assertSame('Yes.', $multipleChoice->correctOptions()[0]->text());

        $trueFalse = $test->questions()[1];
        $this->assertSame('true_false', $trueFalse->type());
        $this->assertCount(2, $trueFalse->options());
        $this->assertSame('False', $trueFalse->correctOptions()[0]->text());
        $this->assertNotNull($trueFalse->explanation());
    }

    public function testLessonWithoutTestUsesTheDefaultIcon(): void
    {
        $course = $this->plugin->registry()->get('demo-course');
        $this->assertInstanceOf(AcademyCourse::class, $course);
        $lessonWithVideo = $course->modules()['module-one']->lessons()['lesson-two'];
        $lessonWithTest = $course->modules()['module-one']->lessons()['lesson-one'];

        $this->assertSame('fa-solid fa-play fa-fw', $lessonWithVideo->icon());
        $this->assertSame('fa-solid fa-question-circle fa-fw', $lessonWithTest->icon());
    }

    public function testFilterByTagFindsOnlyMatchingItems(): void
    {
        $onlyQuiz = $this->plugin->registry()->filter(['tag' => 'quiz']);

        $this->assertCount(1, $onlyQuiz);
        $this->assertSame('lesson-one', $onlyQuiz[0]->slug());
    }

    public function testUnknownUriThrowsContentNotFoundException(): void
    {
        $this->expectException(ContentNotFoundException::class);

        $this->plugin->registry()->get('does-not-exist');
    }

    /**
     * "course-with-draft-module" is not draft, but its "draft-module"
     * module is, and that module's "visible-lesson" lesson is not.
     * allowed() must cascade: the lesson is not allowed either, and
     * modules()/lessons() (the methods every template and aggregate
     * relies on) must exclude the draft module, while keeping its
     * non-draft sibling "visible-module".
     */
    public function testModulesExcludesADraftModuleButKeepsItsVisibleSibling(): void
    {
        $course = $this->plugin->registry()->get('course-with-draft-module');
        $this->assertInstanceOf(AcademyCourse::class, $course);

        $modules = $course->modules();

        $this->assertArrayHasKey('visible-module', $modules);
        $this->assertArrayNotHasKey('draft-module', $modules);
    }

    public function testDraftModuleMakesItsNonDraftLessonNotAllowedToo(): void
    {
        $course = $this->plugin->registry()->get('course-with-draft-module');
        $this->assertInstanceOf(AcademyCourse::class, $course);
        $draftModule = $course->children()['draft-module'];
        $lesson = $draftModule->children()['visible-lesson'];

        $this->assertTrue($draftModule->draft());
        $this->assertFalse($lesson->draft());
        $this->assertFalse($lesson->allowed());

        $this->expectException(ContentNotFoundException::class);
        $this->plugin->registry()->get(
            'course-with-draft-module/draft-module/visible-lesson'
        );
    }

    /**
     * The lesson under the draft module declares a video and an explicit
     * time, precisely so this test can prove they stop being counted in
     * the course's aggregates once the module is excluded — not just that
     * modules()/lessons() drop the key.
     */
    public function testCourseAggregatesExcludeEverythingUnderADraftModule(): void
    {
        $course = $this->plugin->registry()->get('course-with-draft-module');
        $this->assertInstanceOf(AcademyCourse::class, $course);

        $this->assertArrayNotHasKey(
            'course-with-draft-module/draft-module/visible-lesson',
            $course->lessons()
        );
        $this->assertSame(3, $course->time());
        $this->assertCount(0, $course->videos());
    }
}
