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
 * Exercises FaqController end to end with a real Twig render, never
 * before rendered in this session (only FaqRegistry directly).
 */
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
        $router->setContext(new RequestContext(pathInfo: '/faq/pregunta-uno'));

        $this->controller = new FaqController(
            ContentFixtures::contentService(['faq' => $plugin]),
            RendererFixture::create($router)
        );
    }

    public function testShowRendersARealQuestion(): void
    {
        $request = new Request('GET', 'http://localhost/faq/pregunta-uno');

        $html = $this->controller->show($request, 'pregunta-uno');

        $this->assertIsString($html);
        $this->assertStringContainsString('¿Cómo se prueba este paquete?', $html);
    }

    /**
     * "pregunta-uno" has a real child, so the "completo" variants must
     * show alongside the regular single-page download links.
     */
    public function testShowRendersDownloadLinksIncludingFullVariantsWhenThereAreChildren(): void
    {
        $request = new Request('GET', 'http://localhost/faq/pregunta-uno');

        $html = $this->controller->show($request, 'pregunta-uno');

        $this->assertStringContainsString('href="/faq/pregunta-uno.pdf"', $html);
        $this->assertStringContainsString('href="/faq/pregunta-uno.md"', $html);
        $this->assertStringContainsString('href="/faq/pregunta-uno.pdf?full=1"', $html);
        $this->assertStringContainsString('href="/faq/pregunta-uno.md?full=1"', $html);
    }

    /**
     * "pregunta-uno" has a real child ("pregunta-uno/sub-pregunta") in the
     * fixture hierarchy. Unlike the HTML template (gated behind the
     * "show_children" metadata flag), the Markdown export always lists
     * children when present — there is no sidebar/related-questions widget
     * to discover them otherwise when the .md body is fetched directly
     * (e.g. by an AI agent).
     */
    public function testShowMarkdownFormatListsChildQuestionsWithAbsoluteUrls(): void
    {
        $request = new Request('GET', 'http://localhost/faq/pregunta-uno.md');

        $markdown = $this->controller->show($request, 'pregunta-uno.md');

        $this->assertIsString($markdown);
        $this->assertStringContainsString(
            '[¿Y si el paquete falla?](http://localhost/faq/pregunta-uno/sub-pregunta)',
            $markdown
        );
    }

    /**
     * "?full=1" on the .md export embeds the child question's own title
     * and full body inline, instead of just linking to it.
     */
    public function testShowMarkdownFormatWithFullQueryParamEmbedsChildQuestionBodyInline(): void
    {
        $request = new Request('GET', 'http://localhost/faq/pregunta-uno.md?full=1');

        $markdown = $this->controller->show($request, 'pregunta-uno.md');

        $this->assertIsString($markdown);
        $this->assertStringContainsString('## ¿Y si el paquete falla?', $markdown);
        $this->assertStringContainsString(
            'validar el listado',
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
            new Request('GET', 'http://localhost/faq/pregunta-uno/sub-pregunta.md?full=1'),
            'pregunta-uno/sub-pregunta.md'
        );
        $withoutFull = $this->controller->show(
            new Request('GET', 'http://localhost/faq/pregunta-uno/sub-pregunta.md'),
            'pregunta-uno/sub-pregunta.md'
        );

        $this->assertSame($withoutFull, $withFull);
    }

    public function testShowJsonFormatReturnsTheFullArray(): void
    {
        $request = new Request('GET', 'http://localhost/faq/pregunta-uno.json');

        $result = $this->controller->show($request, 'pregunta-uno.json');

        $this->assertIsArray($result);
        $this->assertSame('pregunta-uno', $result['data']['uri']);
    }

    /**
     * Smoke test for the redesigned pdf/_layout (repeating header/footer,
     * page numbering, cover, related-questions hierarchy) — never
     * exercised by any test before.
     */
    public function testShowPdfFormatRendersWithoutError(): void
    {
        $request = new Request('GET', 'http://localhost/faq/pregunta-uno.pdf');

        $pdf = $this->controller->show($request, 'pregunta-uno.pdf');

        $this->assertIsString($pdf);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertSame(1, $this->countPdfPages($pdf));
    }

    /**
     * "?full=1" dumps "pregunta-uno" together with its real child
     * ("pregunta-uno/sub-pregunta") into the SAME pdf: 1 cover page + 1
     * TOC page + 1 page per question.
     */
    public function testShowPdfFormatWithFullQueryParamIncludesChildQuestionsInOnePdf(): void
    {
        $request = new Request('GET', 'http://localhost/faq/pregunta-uno.pdf?full=1');

        $pdf = $this->controller->show($request, 'pregunta-uno.pdf');

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
        $request = new Request('GET', 'http://localhost/faq/pregunta-uno/sub-pregunta.pdf?full=1');

        $pdf = $this->controller->show($request, 'pregunta-uno/sub-pregunta.pdf');

        $this->assertIsString($pdf);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertSame(1, $this->countPdfPages($pdf));
    }

    private function countPdfPages(string $pdf): int
    {
        return preg_match_all('/\/Type\s*\/Page[^s]/', $pdf);
    }

    public function testUnknownQuestionBubblesAsContentNotFoundException(): void
    {
        $request = new Request('GET', 'http://localhost/faq/no-existe');

        $this->expectException(ContentNotFoundException::class);

        $this->controller->show($request, 'no-existe');
    }

    public function testTagActionFiltersQuestions(): void
    {
        $request = new Request('GET', 'http://localhost/faq/tags/tests');

        $html = $this->controller->tag($request, 'tests');

        $this->assertStringContainsString('¿Cómo se prueba este paquete?', $html);
    }
}
