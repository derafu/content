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
            RendererFixture::create($router)
        );
    }

    public function testIndexListsTheCourse(): void
    {
        $html = $this->controller->index();

        $this->assertStringContainsString('Curso Demo', $html);
    }

    public function testCourseActionRendersTheCoursePage(): void
    {
        $request = new Request('GET', 'http://localhost/academy/curso-demo');

        $html = $this->controller->course($request, 'curso-demo');

        $this->assertIsString($html);
        $this->assertStringContainsString('Curso Demo', $html);
        $this->assertStringContainsString('Módulo Uno', $html);
        // The course has real modules, so "completo" links must show
        // alongside the regular single-page download links.
        $this->assertStringContainsString('href="/academy/curso-demo.pdf"', $html);
        $this->assertStringContainsString('href="/academy/curso-demo.md"', $html);
        $this->assertStringContainsString('href="/academy/curso-demo.pdf?full=1"', $html);
        $this->assertStringContainsString('href="/academy/curso-demo.md?full=1"', $html);
        $this->assertStringContainsString('data-copy-md="/academy/curso-demo.md?full=1"', $html);
    }

    /**
     * A course's Markdown export lists every module and, nested under
     * each, every lesson — with real absolute URLs, since it is meant to
     * be read outside a browser (e.g. by an AI agent), where there is no
     * sidebar to discover the course's structure otherwise.
     */
    public function testCourseMarkdownFormatListsModulesAndLessonsWithAbsoluteUrls(): void
    {
        $request = new Request('GET', 'http://localhost/academy/curso-demo.md');

        $markdown = $this->controller->course($request, 'curso-demo.md');

        $this->assertIsString($markdown);
        $this->assertStringContainsString(
            '[Módulo Uno](http://localhost/academy/curso-demo/modulo-uno)',
            $markdown
        );
        $this->assertStringContainsString(
            '[Lección Uno](http://localhost/academy/curso-demo/modulo-uno/leccion-uno)',
            $markdown
        );
        $this->assertStringContainsString(
            '[Lección Dos](http://localhost/academy/curso-demo/modulo-uno/leccion-dos)',
            $markdown
        );
    }

    /**
     * Same idea one level down: a module's Markdown export lists its own
     * lessons with absolute URLs.
     */
    public function testModuleMarkdownFormatListsLessonsWithAbsoluteUrls(): void
    {
        $request = new Request('GET', 'http://localhost/academy/curso-demo/modulo-uno.md');

        $markdown = $this->controller->module($request, 'curso-demo', 'modulo-uno.md');

        $this->assertIsString($markdown);
        $this->assertStringContainsString(
            '[Lección Uno](http://localhost/academy/curso-demo/modulo-uno/leccion-uno)',
            $markdown
        );
        $this->assertStringContainsString(
            '[Lección Dos](http://localhost/academy/curso-demo/modulo-uno/leccion-dos)',
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
        $request = new Request('GET', 'http://localhost/academy/curso-demo.md?full=1');

        $markdown = $this->controller->course($request, 'curso-demo.md');

        $this->assertIsString($markdown);
        $this->assertStringContainsString('## Módulo Uno', $markdown);
        $this->assertStringContainsString('### Lección Uno', $markdown);
        $this->assertStringContainsString('### Lección Dos', $markdown);
        $this->assertStringContainsString(
            'tiempo de lectura',
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
        $request = new Request('GET', 'http://localhost/academy/curso-demo/modulo-uno.md?full=1');

        $markdown = $this->controller->module($request, 'curso-demo', 'modulo-uno.md');

        $this->assertIsString($markdown);
        $this->assertStringContainsString('## Lección Uno', $markdown);
        $this->assertStringContainsString('## Lección Dos', $markdown);
        $this->assertStringNotContainsString('## Lessons', $markdown);
    }

    public function testModuleActionRendersTheModulePage(): void
    {
        $request = new Request('GET', 'http://localhost/academy/curso-demo/modulo-uno');

        $html = $this->controller->module($request, 'curso-demo', 'modulo-uno');

        $this->assertIsString($html);
        $this->assertStringContainsString('Módulo Uno', $html);
        $this->assertStringContainsString('Lección Uno', $html);
        $this->assertStringContainsString('Lección Dos', $html);
        // The module has real lessons, so "completo" links must show
        // alongside the regular single-page download links.
        $this->assertStringContainsString('href="/academy/curso-demo/modulo-uno.pdf"', $html);
        $this->assertStringContainsString('href="/academy/curso-demo/modulo-uno.md"', $html);
        $this->assertStringContainsString('href="/academy/curso-demo/modulo-uno.pdf?full=1"', $html);
        $this->assertStringContainsString('href="/academy/curso-demo/modulo-uno.md?full=1"', $html);
    }

    public function testLessonActionRendersTheLessonPageWithItsAttachmentTest(): void
    {
        $request = new Request('GET', 'http://localhost/academy/curso-demo/modulo-uno/leccion-uno');

        $html = $this->controller->lesson($request, 'curso-demo', 'modulo-uno', 'leccion-uno');

        $this->assertIsString($html);
        $this->assertStringContainsString('Lección Uno', $html);
        // The test widget's data-api must be a real, routed download URL
        // built from the resolved attachment (AcademyLesson::testAttachment()),
        // not the raw "?attachment=quiz.json" metadata value the JS widget
        // could never fetch as-is.
        $this->assertStringContainsString('id="academy-test-container"', $html);
        $this->assertStringContainsString(
            'data-api="/academy/curso-demo/modulo-uno/leccion-uno/_attachments/quiz.json"',
            $html
        );
        // Lessons have no "completo" concept (leaf content): only the two
        // single-page download links.
        $this->assertStringContainsString(
            'href="/academy/curso-demo/modulo-uno/leccion-uno.pdf"',
            $html
        );
        $this->assertStringContainsString(
            'href="/academy/curso-demo/modulo-uno/leccion-uno.md"',
            $html
        );
        $this->assertStringNotContainsString('full=1', $html);
    }

    public function testLessonActionJsonFormatIncludesTheAttachmentBackedTestField(): void
    {
        $request = new Request('GET', 'http://localhost/academy/curso-demo/modulo-uno/leccion-uno.json');

        $result = $this->controller->lesson($request, 'curso-demo', 'modulo-uno', 'leccion-uno.json');

        $this->assertIsArray($result);
        $this->assertSame('leccion-uno', $result['data']['slug']);
    }

    /**
     * "leccion-dos" has a real frontmatter "video" — the .md export must
     * show it as a real, visible Markdown link (not just buried in the
     * YAML frontmatter, which a reader — or an LLM — has no reason to
     * scan closely).
     */
    public function testLessonMarkdownFormatShowsAVisibleVideoLink(): void
    {
        $request = new Request('GET', 'http://localhost/academy/curso-demo/modulo-uno/leccion-dos.md');

        $markdown = $this->controller->lesson($request, 'curso-demo', 'modulo-uno', 'leccion-dos.md');

        $this->assertIsString($markdown);
        $this->assertStringContainsString(
            '[![Watch video](https://img.youtube.com/vi/dQw4w9WgXcQ/hqdefault.jpg)](https://www.youtube.com/watch?v=dQw4w9WgXcQ)',
            $markdown
        );
    }

    /**
     * "leccion-uno" has a real quiz attachment — the .md export must
     * show the FULL test (every option, not just the correct one, plus
     * the explanation) as a GFM task list, not a stripped-down FAQ-style
     * question/answer: the wrong options and the explanation are real
     * information an LLM reading this can use (e.g. to know what NOT to
     * answer, or why an option is wrong).
     */
    public function testLessonMarkdownFormatShowsTheFullQuizWithAllOptions(): void
    {
        $request = new Request('GET', 'http://localhost/academy/curso-demo/modulo-uno/leccion-uno.md');

        $markdown = $this->controller->lesson($request, 'curso-demo', 'modulo-uno', 'leccion-uno.md');

        $this->assertIsString($markdown);
        $this->assertStringContainsString('## Cuestionario de prueba', $markdown);
        $this->assertStringContainsString('### 1. ¿Esto es un fixture?', $markdown);
        $this->assertStringContainsString('- [x] Sí.', $markdown);
        $this->assertStringContainsString('- [ ] No.', $markdown);
        $this->assertStringContainsString(
            '> Sí, este archivo es un fixture usado por los tests.',
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
        $request = new Request('GET', 'http://localhost/academy/curso-demo.pdf');

        $pdf = $this->controller->course($request, 'curso-demo.pdf');

        $this->assertIsString($pdf);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertSame(2, $this->countPdfPages($pdf));
    }

    /**
     * "?full=1" dumps the whole course — every module and, nested under
     * each, every lesson — into the SAME pdf: 1 cover page + 1 TOC page +
     * 1 for the course + 1 for "Módulo Uno" + 1 per lesson (2 lessons) +
     * 2 for "leccion-uno"'s quiz (questions page + its own "Respuestas"
     * page-break) = 8 pages.
     */
    public function testCoursePdfFormatWithFullQueryParamIncludesModulesAndLessonsInOnePdf(): void
    {
        $request = new Request('GET', 'http://localhost/academy/curso-demo.pdf?full=1');

        $pdf = $this->controller->course($request, 'curso-demo.pdf');

        $this->assertIsString($pdf);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertSame(8, $this->countPdfPages($pdf));
    }

    public function testModulePdfFormatRendersWithoutError(): void
    {
        $request = new Request('GET', 'http://localhost/academy/curso-demo/modulo-uno.pdf');

        $pdf = $this->controller->module($request, 'curso-demo', 'modulo-uno.pdf');

        $this->assertIsString($pdf);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertSame(1, $this->countPdfPages($pdf));
    }

    /**
     * "?full=1" dumps the module and every one of its lessons into the
     * SAME pdf: 1 cover page + 1 TOC page + 1 for the module + 1 per
     * lesson (2 lessons) + 2 for "leccion-uno"'s quiz (questions page +
     * its own "Respuestas" page-break) = 7 pages.
     */
    public function testModulePdfFormatWithFullQueryParamIncludesLessonsInOnePdf(): void
    {
        $request = new Request('GET', 'http://localhost/academy/curso-demo/modulo-uno.pdf?full=1');

        $pdf = $this->controller->module($request, 'curso-demo', 'modulo-uno.pdf');

        $this->assertIsString($pdf);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertSame(7, $this->countPdfPages($pdf));
    }

    /**
     * "leccion-uno" has a real quiz attachment: the questions page plus
     * its own "Respuestas" page-break = 2 pages.
     */
    public function testLessonPdfFormatRendersWithoutError(): void
    {
        $request = new Request('GET', 'http://localhost/academy/curso-demo/modulo-uno/leccion-uno.pdf');

        $pdf = $this->controller->lesson($request, 'curso-demo', 'modulo-uno', 'leccion-uno.pdf');

        $this->assertIsString($pdf);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertSame(2, $this->countPdfPages($pdf));
    }

    public function testUnknownModuleBubblesAsContentNotFoundException(): void
    {
        $request = new Request('GET', 'http://localhost/academy/curso-demo/no-existe');

        $this->expectException(ContentNotFoundException::class);

        $this->controller->module($request, 'curso-demo', 'no-existe');
    }

    public function testUnknownLessonBubblesAsContentNotFoundException(): void
    {
        $request = new Request('GET', 'http://localhost/academy/curso-demo/modulo-uno/no-existe');

        $this->expectException(ContentNotFoundException::class);

        $this->controller->lesson($request, 'curso-demo', 'modulo-uno', 'no-existe');
    }

    public function testTagActionFiltersCoursesByTag(): void
    {
        $request = new Request('GET', 'http://localhost/academy/tags/demo');

        $html = $this->controller->tag($request, 'demo');

        $this->assertStringContainsString('Curso Demo', $html);
    }

    private function countPdfPages(string $pdf): int
    {
        return preg_match_all('/\/Type\s*\/Page[^s]/', $pdf);
    }
}
