<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent\Plugin\Faq;

use Derafu\Content\Abstract\AbstractContentController;
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
use Derafu\Content\Plugin\Faq\FaqController;
use Derafu\Content\Plugin\Faq\FaqPlugin;
use Derafu\Content\Plugin\Faq\FaqQuestion;
use Derafu\Content\Plugin\Faq\FaqRegistry;
use Derafu\Http\Request;
use Derafu\Routing\ValueObject\RequestContext;
use Derafu\TestsContent\Support\ContentFixtures;
use Derafu\TestsContent\Support\RendererFixture;
use Derafu\TestsContent\Support\RouterFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Exercises FaqController end to end with a real Twig render.
 */
#[\PHPUnit\Framework\Attributes\UsesClass(\Derafu\Content\Provider\ContentTemplatePathProvider::class)]
#[CoversClass(FaqController::class)]
#[CoversClass(AbstractContentController::class)]
#[UsesClass(FaqPlugin::class)]
#[UsesClass(FaqRegistry::class)]
#[UsesClass(FaqQuestion::class)]
#[UsesClass(ContentService::class)]
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
final class FaqControllerTest extends TestCase
{
    private FaqController $controller;

    protected function setUp(): void
    {
        $plugin = new FaqPlugin(
            ContentFixtures::contentContext(),
            ['path' => 'faq']
        );
        $plugin->loadContent(new ContentLoader(ContentFixtures::contentPath()));

        $router = RouterFixture::create();
        $router->setContext(new RequestContext(pathInfo: '/faq/question-one'));

        $this->controller = new FaqController(
            ContentFixtures::contentService(['faq' => $plugin]),
            RendererFixture::create($router),
            $router
        );
    }

    public function testShowRendersARealQuestion(): void
    {
        $request = new Request('GET', 'http://localhost/faq/question-one');

        $html = $this->controller->show($request, 'question-one');

        $this->assertIsString($html);
        $this->assertStringContainsString('How is this package tested?', $html);
    }

    /**
     * "question-one" has a real child, so the "full" variants must
     * show alongside the regular single-page download links.
     */
    public function testShowRendersDownloadLinksIncludingFullVariantsWhenThereAreChildren(): void
    {
        $request = new Request('GET', 'http://localhost/faq/question-one');

        $html = $this->controller->show($request, 'question-one');
        $this->assertIsString($html);

        $this->assertStringContainsString('href="/faq/question-one.pdf"', $html);
        $this->assertStringContainsString('href="/faq/question-one.md"', $html);
        $this->assertStringContainsString('href="/faq/question-one.pdf?full=1"', $html);
        $this->assertStringContainsString('href="/faq/question-one.md?full=1"', $html);
    }

    /**
     * "question-one" has a real child ("question-one/sub-question") in the
     * fixture hierarchy. Unlike the HTML template (gated behind the
     * "show_children" metadata flag), the Markdown export always lists
     * children when present — there is no sidebar/related-questions widget
     * to discover them otherwise when the .md body is fetched directly
     * (e.g. by an AI agent).
     */
    public function testShowMarkdownFormatListsChildQuestionsWithAbsoluteUrls(): void
    {
        $request = new Request('GET', 'http://localhost/faq/question-one.md');

        $markdown = $this->controller->show($request, 'question-one.md');

        $this->assertIsString($markdown);
        $this->assertStringContainsString(
            '[And if the package fails?](http://localhost/faq/question-one/sub-question)',
            $markdown
        );
    }

    /**
     * "?full=1" on the .md export embeds the child question's own title
     * and full body inline, instead of just linking to it.
     */
    public function testShowMarkdownFormatWithFullQueryParamEmbedsChildQuestionBodyInline(): void
    {
        $request = new Request('GET', 'http://localhost/faq/question-one.md?full=1');

        $markdown = $this->controller->show($request, 'question-one.md');

        $this->assertIsString($markdown);
        $this->assertStringContainsString('## And if the package fails?', $markdown);
        $this->assertStringContainsString(
            'to check the list',
            $markdown
        );
        $this->assertStringNotContainsString('Related questions', $markdown);
    }

    /**
     * "?full=1" on a question WITHOUT children is a no-op.
     */
    public function testShowMarkdownFormatWithFullQueryParamOnAChildlessQuestionIgnoresTheFlag(): void
    {
        $withFull = $this->controller->show(
            new Request('GET', 'http://localhost/faq/question-one/sub-question.md?full=1'),
            'question-one/sub-question.md'
        );
        $withoutFull = $this->controller->show(
            new Request('GET', 'http://localhost/faq/question-one/sub-question.md'),
            'question-one/sub-question.md'
        );

        $this->assertSame($withoutFull, $withFull);
    }

    public function testShowJsonFormatReturnsTheFullArray(): void
    {
        $request = new Request('GET', 'http://localhost/faq/question-one.json');

        $result = $this->controller->show($request, 'question-one.json');

        $this->assertIsArray($result);
        $this->assertIsArray($result['data']);
        $this->assertSame('question-one', $result['data']['uri']);
    }

    /**
     * Smoke test for the redesigned pdf/_layout (repeating header/footer,
     * page numbering, cover, related-questions hierarchy) — never
     * exercised by any test before.
     */
    public function testShowPdfFormatRendersWithoutError(): void
    {
        $request = new Request('GET', 'http://localhost/faq/question-one.pdf');

        $pdf = $this->controller->show($request, 'question-one.pdf');

        $this->assertIsString($pdf);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertSame(1, $this->countPdfPages($pdf));
    }

    /**
     * "?full=1" dumps "question-one" together with its real child
     * ("question-one/sub-question") into the SAME pdf: 1 cover page + 1
     * TOC page + 1 page per question.
     */
    public function testShowPdfFormatWithFullQueryParamIncludesChildQuestionsInOnePdf(): void
    {
        $request = new Request('GET', 'http://localhost/faq/question-one.pdf?full=1');

        $pdf = $this->controller->show($request, 'question-one.pdf');

        $this->assertIsString($pdf);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertSame(4, $this->countPdfPages($pdf));
    }

    /**
     * "?full=1" on a question WITHOUT children is a no-op: it falls
     * straight through to the regular single-item PDF.
     */
    public function testShowPdfFormatWithFullQueryParamOnAChildlessQuestionIgnoresTheFlag(): void
    {
        $request = new Request('GET', 'http://localhost/faq/question-one/sub-question.pdf?full=1');

        $pdf = $this->controller->show($request, 'question-one/sub-question.pdf');

        $this->assertIsString($pdf);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertSame(1, $this->countPdfPages($pdf));
    }

    private function countPdfPages(string $pdf): int
    {
        $pages = preg_match_all('/\/Type\s*\/Page[^s]/', $pdf);
        $this->assertNotFalse($pages);

        return $pages;
    }

    public function testUnknownQuestionBubblesAsContentNotFoundException(): void
    {
        $request = new Request('GET', 'http://localhost/faq/does-not-exist');

        $this->expectException(ContentNotFoundException::class);

        $this->controller->show($request, 'does-not-exist');
    }

    /**
     * "draft-question" is a draft top-level question, with a non-draft
     * child ("visible-sub-answer"). Neither must appear in the
     * sidebar: FaqController feeds it plugin->registry()->all(), which
     * returns every loaded question regardless of allowed().
     */
    public function testShowRendersSidebarWithoutADraftTopLevelQuestion(): void
    {
        $request = new Request('GET', 'http://localhost/faq/question-one');

        $html = $this->controller->show($request, 'question-one');
        $this->assertIsString($html);

        $this->assertStringNotContainsString('Draft question?', $html);
        $this->assertStringNotContainsString('Visible sub answer', $html);
    }

    /**
     * "visible-question" is NOT draft, but its only child
     * ("draft-sub-question") is. The parent must still show in the sidebar, but
     * the draft child must not leak through the nested recursion of the
     * nav macro.
     */
    public function testShowRendersSidebarWithoutADraftChildNestedUnderAnAllowedQuestion(): void
    {
        $request = new Request('GET', 'http://localhost/faq/question-one');

        $html = $this->controller->show($request, 'question-one');
        $this->assertIsString($html);

        $this->assertStringContainsString('Visible question?', $html);
        $this->assertStringNotContainsString('Draft sub question', $html);
    }

    public function testTagActionFiltersQuestions(): void
    {
        $request = new Request('GET', 'http://localhost/faq/tags/tests');

        $html = $this->controller->tag($request, 'tests');

        $this->assertStringContainsString('How is this package tested?', $html);
    }
}
