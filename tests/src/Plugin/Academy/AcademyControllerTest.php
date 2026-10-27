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

use Derafu\Content\Abstract\AbstractContentController;
use Derafu\Content\ContentAttachment;
use Derafu\Content\ContentAuthor;
use Derafu\Content\ContentBag;
use Derafu\Content\ContentConfig;
use Derafu\Content\ContentContext;
use Derafu\Content\ContentFilters;
use Derafu\Content\ContentHtmlTag;
use Derafu\Content\ContentHtmlTags;
use Derafu\Content\ContentLoader;
use Derafu\Content\ContentService;
use Derafu\Content\ContentSplFileInfo;
use Derafu\Content\ContentTag;
use Derafu\Content\Exception\ContentNotFoundException;
use Derafu\Content\Plugin\Academy\AcademyController;
use Derafu\Content\Plugin\Academy\AcademyCourse;
use Derafu\Content\Plugin\Academy\AcademyLesson;
use Derafu\Content\Plugin\Academy\AcademyModule;
use Derafu\Content\Plugin\Academy\AcademyPlugin;
use Derafu\Content\Plugin\Academy\AcademyRegistry;
use Derafu\Content\Plugin\Academy\AcademyTest;
use Derafu\Content\Plugin\Academy\AcademyTestOption;
use Derafu\Content\Plugin\Academy\AcademyTestQuestion;
use Derafu\Http\Request;
use Derafu\Routing\ValueObject\RequestContext;
use Derafu\TestsContent\Support\ContentFixtures;
use Derafu\TestsContent\Support\RendererFixture;
use Derafu\TestsContent\Support\RouterFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Exercises every AcademyController action against real fixture content
 * with a real Twig render, through the real course/module/lesson layout —
 * none of the 5 actions (index, course, module, lesson, tag) had ever been
 * rendered end to end before this test, only AcademyRegistry directly.
 */
#[\PHPUnit\Framework\Attributes\UsesClass(\Derafu\Content\Provider\ContentTemplatePathProvider::class)]
#[CoversClass(AcademyController::class)]
#[CoversClass(AbstractContentController::class)]
#[UsesClass(AcademyPlugin::class)]
#[UsesClass(AcademyRegistry::class)]
#[UsesClass(AcademyCourse::class)]
#[UsesClass(AcademyModule::class)]
#[UsesClass(AcademyLesson::class)]
#[UsesClass(AcademyTest::class)]
#[UsesClass(AcademyTestQuestion::class)]
#[UsesClass(AcademyTestOption::class)]
#[UsesClass(ContentService::class)]
#[UsesClass(ContentAttachment::class)]
#[UsesClass(ContentAuthor::class)]
#[UsesClass(ContentBag::class)]
#[UsesClass(ContentConfig::class)]
#[UsesClass(ContentContext::class)]
#[UsesClass(ContentHtmlTag::class)]
#[UsesClass(ContentHtmlTags::class)]
#[UsesClass(ContentLoader::class)]
#[UsesClass(ContentSplFileInfo::class)]
#[UsesClass(ContentTag::class)]
#[UsesClass(ContentNotFoundException::class)]
#[UsesClass(ContentFilters::class)]
final class AcademyControllerTest extends TestCase
{
    private AcademyController $controller;

    protected function setUp(): void
    {
        $plugin = new AcademyPlugin(
            ContentFixtures::contentContext(),
            ['path' => 'academy']
        );
        $plugin->loadContent(new ContentLoader(ContentFixtures::contentPath()));

        $router = RouterFixture::create();
        $router->setContext(new RequestContext(pathInfo: '/academy'));

        $this->controller = new AcademyController(
            ContentFixtures::contentService(['academy' => $plugin]),
            RendererFixture::create($router),
            $router
        );
    }

    public function testIndexListsTheCourse(): void
    {
        $html = $this->controller->index();

        $this->assertStringContainsString('Demo Course', $html);
    }

    public function testCourseActionRendersTheCoursePage(): void
    {
        $request = new Request('GET', 'http://localhost/academy/demo-course');

        $html = $this->controller->course($request, 'demo-course');

        $this->assertIsString($html);
        $this->assertStringContainsString('Demo Course', $html);
        $this->assertStringContainsString('Module One', $html);
        // The course has real modules, so "full" links must show
        // alongside the regular single-page download links.
        $this->assertStringContainsString('href="/academy/demo-course.pdf"', $html);
        $this->assertStringContainsString('href="/academy/demo-course.md"', $html);
        $this->assertStringContainsString('href="/academy/demo-course.pdf?full=1"', $html);
        $this->assertStringContainsString('href="/academy/demo-course.md?full=1"', $html);
        $this->assertStringContainsString('data-copy-md="/academy/demo-course.md?full=1"', $html);
    }

    /**
     * A course's Markdown export lists every module and, nested under
     * each, every lesson — with real absolute URLs, since it is meant to
     * be read outside a browser (e.g. by an AI agent), where there is no
     * sidebar to discover the course's structure otherwise.
     */
    public function testCourseMarkdownFormatListsModulesAndLessonsWithAbsoluteUrls(): void
    {
        $request = new Request('GET', 'http://localhost/academy/demo-course.md');

        $markdown = $this->controller->course($request, 'demo-course.md');

        $this->assertIsString($markdown);
        $this->assertStringContainsString(
            '[Module One](http://localhost/academy/demo-course/module-one)',
            $markdown
        );
        $this->assertStringContainsString(
            '[Lesson One](http://localhost/academy/demo-course/module-one/lesson-one)',
            $markdown
        );
        $this->assertStringContainsString(
            '[Lesson Two](http://localhost/academy/demo-course/module-one/lesson-two)',
            $markdown
        );
    }

    /**
     * Same idea one level down: a module's Markdown export lists its own
     * lessons with absolute URLs.
     */
    public function testModuleMarkdownFormatListsLessonsWithAbsoluteUrls(): void
    {
        $request = new Request('GET', 'http://localhost/academy/demo-course/module-one.md');

        $markdown = $this->controller->module($request, 'demo-course', 'module-one.md');

        $this->assertIsString($markdown);
        $this->assertStringContainsString(
            '[Lesson One](http://localhost/academy/demo-course/module-one/lesson-one)',
            $markdown
        );
        $this->assertStringContainsString(
            '[Lesson Two](http://localhost/academy/demo-course/module-one/lesson-two)',
            $markdown
        );
    }

    /**
     * "?full=1" on the course's .md export embeds every module's and
     * every lesson's own title and full body inline, instead of just
     * linking to them.
     */
    public function testCourseMarkdownFormatWithFullQueryParamEmbedsModulesAndLessonsBodyInline(): void
    {
        $request = new Request('GET', 'http://localhost/academy/demo-course.md?full=1');

        $markdown = $this->controller->course($request, 'demo-course.md');

        $this->assertIsString($markdown);
        $this->assertStringContainsString('## Module One', $markdown);
        $this->assertStringContainsString('### Lesson One', $markdown);
        $this->assertStringContainsString('### Lesson Two', $markdown);
        $this->assertStringContainsString(
            'reading time',
            $markdown
        );
        $this->assertStringNotContainsString('## Modules', $markdown);
    }

    /**
     * "?full=1" on the module's .md export embeds every lesson's own
     * title and full body inline.
     */
    public function testModuleMarkdownFormatWithFullQueryParamEmbedsLessonsBodyInline(): void
    {
        $request = new Request('GET', 'http://localhost/academy/demo-course/module-one.md?full=1');

        $markdown = $this->controller->module($request, 'demo-course', 'module-one.md');

        $this->assertIsString($markdown);
        $this->assertStringContainsString('## Lesson One', $markdown);
        $this->assertStringContainsString('## Lesson Two', $markdown);
        $this->assertStringNotContainsString('## Lessons', $markdown);
    }

    public function testModuleActionRendersTheModulePage(): void
    {
        $request = new Request('GET', 'http://localhost/academy/demo-course/module-one');

        $html = $this->controller->module($request, 'demo-course', 'module-one');

        $this->assertIsString($html);
        $this->assertStringContainsString('Module One', $html);
        $this->assertStringContainsString('Lesson One', $html);
        $this->assertStringContainsString('Lesson Two', $html);
        // The module has real lessons, so "full" links must show
        // alongside the regular single-page download links.
        $this->assertStringContainsString('href="/academy/demo-course/module-one.pdf"', $html);
        $this->assertStringContainsString('href="/academy/demo-course/module-one.md"', $html);
        $this->assertStringContainsString('href="/academy/demo-course/module-one.pdf?full=1"', $html);
        $this->assertStringContainsString('href="/academy/demo-course/module-one.md?full=1"', $html);
    }

    public function testLessonActionRendersTheLessonPageWithItsAttachmentTest(): void
    {
        $request = new Request('GET', 'http://localhost/academy/demo-course/module-one/lesson-one');

        $html = $this->controller->lesson($request, 'demo-course', 'module-one', 'lesson-one');

        $this->assertIsString($html);
        $this->assertStringContainsString('Lesson One', $html);
        // The test widget's data-api must be a real, routed download URL
        // built from the resolved attachment (AcademyLesson::testAttachment()),
        // not the raw "?attachment=quiz.json" metadata value the JS widget
        // could never fetch as-is.
        $this->assertStringContainsString('id="academy-test-container"', $html);
        $this->assertStringContainsString(
            'data-api="/academy/demo-course/module-one/lesson-one/_attachments/quiz.json"',
            $html
        );
        // Lessons have no "full" concept (leaf content): only the two
        // single-page download links.
        $this->assertStringContainsString(
            'href="/academy/demo-course/module-one/lesson-one.pdf"',
            $html
        );
        $this->assertStringContainsString(
            'href="/academy/demo-course/module-one/lesson-one.md"',
            $html
        );
        $this->assertStringNotContainsString('full=1', $html);
    }

    public function testLessonActionJsonFormatIncludesTheAttachmentBackedTestField(): void
    {
        $request = new Request('GET', 'http://localhost/academy/demo-course/module-one/lesson-one.json');

        $result = $this->controller->lesson($request, 'demo-course', 'module-one', 'lesson-one.json');

        $this->assertIsArray($result);
        $this->assertIsArray($result['data']);
        $this->assertSame('lesson-one', $result['data']['slug']);
    }

    /**
     * "lesson-two" has a real frontmatter "video" — the .md export must
     * show it as a real, visible Markdown link (not just buried in the
     * YAML frontmatter, which a reader — or an LLM — has no reason to
     * scan closely).
     */
    public function testLessonMarkdownFormatShowsAVisibleVideoLink(): void
    {
        $request = new Request('GET', 'http://localhost/academy/demo-course/module-one/lesson-two.md');

        $markdown = $this->controller->lesson($request, 'demo-course', 'module-one', 'lesson-two.md');

        $this->assertIsString($markdown);
        $this->assertStringContainsString(
            '[![Watch video](https://img.youtube.com/vi/dQw4w9WgXcQ/hqdefault.jpg)](https://www.youtube.com/watch?v=dQw4w9WgXcQ)',
            $markdown
        );
    }

    /**
     * "lesson-one" has a real quiz attachment — the .md export must
     * show the FULL test (every option, not just the correct one, plus
     * the explanation) as a GFM task list, not a stripped-down FAQ-style
     * question/answer: the wrong options and the explanation are real
     * information an LLM reading this can use (e.g. to know what NOT to
     * answer, or why an option is wrong).
     */
    public function testLessonMarkdownFormatShowsTheFullQuizWithAllOptions(): void
    {
        $request = new Request('GET', 'http://localhost/academy/demo-course/module-one/lesson-one.md');

        $markdown = $this->controller->lesson($request, 'demo-course', 'module-one', 'lesson-one.md');

        $this->assertIsString($markdown);
        $this->assertStringContainsString('## Test quiz', $markdown);
        $this->assertStringContainsString('### 1. Is this a fixture?', $markdown);
        $this->assertStringContainsString('- [x] Yes.', $markdown);
        $this->assertStringContainsString('- [ ] No.', $markdown);
        $this->assertStringContainsString(
            '> Yes, this file is a fixture used by the tests.',
            $markdown
        );
        // true_false question: both options present, not just the
        // correct one.
        $this->assertStringContainsString('- [ ] True', $markdown);
        $this->assertStringContainsString('- [x] False', $markdown);
    }

    /**
     * Smoke tests for the redesigned PDF templates (shared pdf/_layout
     * with a real repeating header/footer, page numbering, cover and
     * module/lesson hierarchy) — never exercised by any test before.
     *
     * The course's own content is 1 page, plus its "Modules"
     * modules/lessons summary on its own page (page-break-before) = 2.
     */
    public function testCoursePdfFormatRendersWithoutError(): void
    {
        $request = new Request('GET', 'http://localhost/academy/demo-course.pdf');

        $pdf = $this->controller->course($request, 'demo-course.pdf');

        $this->assertIsString($pdf);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertSame(2, $this->countPdfPages($pdf));
    }

    /**
     * "?full=1" dumps the whole course — every module and, nested under
     * each, every lesson — into the SAME pdf: 1 cover page + 1 TOC page +
     * 1 for the course + 1 for "Module One" + 1 per lesson (2 lessons) +
     * 2 for "lesson-one"'s quiz (questions page + its own "Answers"
     * page-break) = 8 pages.
     */
    public function testCoursePdfFormatWithFullQueryParamIncludesModulesAndLessonsInOnePdf(): void
    {
        $request = new Request('GET', 'http://localhost/academy/demo-course.pdf?full=1');

        $pdf = $this->controller->course($request, 'demo-course.pdf');

        $this->assertIsString($pdf);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertSame(8, $this->countPdfPages($pdf));
    }

    public function testModulePdfFormatRendersWithoutError(): void
    {
        $request = new Request('GET', 'http://localhost/academy/demo-course/module-one.pdf');

        $pdf = $this->controller->module($request, 'demo-course', 'module-one.pdf');

        $this->assertIsString($pdf);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertSame(1, $this->countPdfPages($pdf));
    }

    /**
     * "?full=1" dumps the module and every one of its lessons into the
     * SAME pdf: 1 cover page + 1 TOC page + 1 for the module + 1 per
     * lesson (2 lessons) + 2 for "lesson-one"'s quiz (questions page +
     * its own "Answers" page-break) = 7 pages.
     */
    public function testModulePdfFormatWithFullQueryParamIncludesLessonsInOnePdf(): void
    {
        $request = new Request('GET', 'http://localhost/academy/demo-course/module-one.pdf?full=1');

        $pdf = $this->controller->module($request, 'demo-course', 'module-one.pdf');

        $this->assertIsString($pdf);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertSame(7, $this->countPdfPages($pdf));
    }

    /**
     * "lesson-one" has a real quiz attachment: the questions page plus
     * its own "Answers" page-break = 2 pages.
     */
    public function testLessonPdfFormatRendersWithoutError(): void
    {
        $request = new Request('GET', 'http://localhost/academy/demo-course/module-one/lesson-one.pdf');

        $pdf = $this->controller->lesson($request, 'demo-course', 'module-one', 'lesson-one.pdf');

        $this->assertIsString($pdf);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertSame(2, $this->countPdfPages($pdf));
    }

    public function testUnknownModuleBubblesAsContentNotFoundException(): void
    {
        $request = new Request('GET', 'http://localhost/academy/demo-course/does-not-exist');

        $this->expectException(ContentNotFoundException::class);

        $this->controller->module($request, 'demo-course', 'does-not-exist');
    }

    public function testUnknownLessonBubblesAsContentNotFoundException(): void
    {
        $request = new Request('GET', 'http://localhost/academy/demo-course/module-one/does-not-exist');

        $this->expectException(ContentNotFoundException::class);

        $this->controller->lesson($request, 'demo-course', 'module-one', 'does-not-exist');
    }

    public function testTagActionFiltersCoursesByTag(): void
    {
        $request = new Request('GET', 'http://localhost/academy/tags/demo');

        $html = $this->controller->tag($request, 'demo');

        $this->assertStringContainsString('Demo Course', $html);
    }

    /**
     * "draft-course" is a draft top-level course. The index page feeds
     * its own template plugin->registry()->all(), which returns every
     * loaded course regardless of allowed() — the same real bug reported
     * for this package, applied to Academy's course grid.
     */
    public function testIndexDoesNotListADraftCourse(): void
    {
        $html = $this->controller->index();

        $this->assertStringNotContainsString('Draft Course', $html);
    }

    /**
     * "course-with-draft-module" has a draft module ("draft-module")
     * alongside a non-draft one ("visible-module"). The course page's own
     * sidebar/tabs list modules via course.modules directly (not through
     * the registry), so this exercises AcademyCourse::modules() itself,
     * not the controller.
     */
    public function testCourseActionDoesNotListADraftModule(): void
    {
        $request = new Request('GET', 'http://localhost/academy/course-with-draft-module');

        $html = $this->controller->course($request, 'course-with-draft-module');
        $this->assertIsString($html);

        $this->assertStringContainsString('Visible Module', $html);
        $this->assertStringNotContainsString('Draft Module', $html);
    }

    /**
     * Direct access to a draft module's own page must be refused, exactly
     * like a draft course or doc is refused — today it is not: `course->
     * modules()[$module]` is a plain array lookup that never checks
     * allowed(), so a draft module is fully reachable by URL regardless
     * of environment.
     */
    public function testModuleActionRejectsADraftModuleOutsideALocalEnvironment(): void
    {
        $request = new Request(
            'GET',
            'http://localhost/academy/course-with-draft-module/draft-module'
        );

        $this->expectException(ContentNotFoundException::class);

        $this->controller->module($request, 'course-with-draft-module', 'draft-module');
    }

    /**
     * Same bug, one level deeper: a lesson whose own draft flag is false
     * but whose module is draft must still be refused, via the same
     * allowed() cascade already covered at the registry level.
     */
    public function testLessonActionRejectsALessonNestedUnderADraftModule(): void
    {
        $request = new Request(
            'GET',
            'http://localhost/academy/course-with-draft-module/draft-module/visible-lesson'
        );

        $this->expectException(ContentNotFoundException::class);

        $this->controller->lesson(
            $request,
            'course-with-draft-module',
            'draft-module',
            'visible-lesson'
        );
    }

    private function countPdfPages(string $pdf): int
    {
        $pages = preg_match_all('/\/Type\s*\/Page[^s]/', $pdf);
        $this->assertNotFalse($pages);

        return $pages;
    }

    public function testCourseActionJsonFormatReturnsTheCourse(): void
    {
        $request = new Request('GET', 'http://localhost/academy/demo-course.json');

        $result = $this->controller->course($request, 'demo-course.json');

        $this->assertIsArray($result);
        $this->assertIsArray($result['data']);
        $this->assertSame('demo-course', $result['data']['slug']);
        $this->assertSame('Demo Course', $result['data']['title']);
    }

    public function testModuleActionJsonFormatReturnsTheModule(): void
    {
        $request = new Request('GET', 'http://localhost/academy/demo-course/module-one.json');

        $result = $this->controller->module($request, 'demo-course', 'module-one.json');

        $this->assertIsArray($result);
        $this->assertIsArray($result['data']);
        $this->assertSame('module-one', $result['data']['slug']);
        $this->assertSame('Module One', $result['data']['title']);
    }
}
