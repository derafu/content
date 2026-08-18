<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent\Plugin\Pages;

use Derafu\Content\Abstract\AbstractContentController;
use Derafu\Content\ContentAuthor;
use Derafu\Content\ContentBag;
use Derafu\Content\ContentConfig;
use Derafu\Content\ContentContext;
use Derafu\Content\ContentLoader;
use Derafu\Content\ContentService;
use Derafu\Content\ContentSplFileInfo;
use Derafu\Content\Exception\ContentNotFoundException;
use Derafu\Content\Plugin\Pages\PagesController;
use Derafu\Content\Plugin\Pages\PagesPage;
use Derafu\Content\Plugin\Pages\PagesPlugin;
use Derafu\Content\Plugin\Pages\PagesRegistry;
use Derafu\Http\Request;
use Derafu\Routing\ValueObject\RequestContext;
use Derafu\TestsContent\Support\ContentFixtures;
use Derafu\TestsContent\Support\RendererFixture;
use Derafu\TestsContent\Support\RouterFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Exercises PagesController end to end with a real ContentService (built
 * from a real, already-loaded PagesPlugin over fixture content) and a
 * real Request, and — for the HTML path — a real Twig renderer, so the
 * actual show.{html,md,json}.twig templates run for real. No mocks.
 */
#[CoversClass(PagesController::class)]
#[CoversClass(AbstractContentController::class)]
#[UsesClass(PagesPlugin::class)]
#[UsesClass(PagesRegistry::class)]
#[UsesClass(PagesPage::class)]
#[UsesClass(ContentService::class)]
#[UsesClass(ContentAuthor::class)]
#[UsesClass(ContentBag::class)]
#[UsesClass(ContentConfig::class)]
#[UsesClass(ContentContext::class)]
#[UsesClass(ContentLoader::class)]
#[UsesClass(ContentSplFileInfo::class)]
#[UsesClass(ContentNotFoundException::class)]
final class PagesControllerTest extends TestCase
{
    private PagesController $controller;

    protected function setUp(): void
    {
        $plugin = new PagesPlugin(
            ContentFixtures::contentContext(),
            ['path' => 'pages']
        );
        $plugin->loadContent(new ContentLoader(ContentFixtures::contentPath()));

        $contentService = ContentFixtures::contentService(['pages' => $plugin]);

        $router = RouterFixture::create();
        $router->setContext(new RequestContext(pathInfo: '/pages/about'));

        $this->controller = new PagesController(
            $contentService,
            RendererFixture::create($router)
        );
    }

    public function testJsonFormatReturnsTheFullContentArray(): void
    {
        $request = new Request('GET', 'http://localhost/pages/about.json');

        $result = $this->controller->show($request, 'about.json');

        $this->assertIsArray($result);
        $this->assertSame('about', $result['data']['uri']);
        $this->assertSame('Sobre nosotros', $result['data']['title']);
        $this->assertStringContainsString('Página de prueba', $result['data']['data']);
    }

    public function testHtmlFormatRendersTheRealTwigTemplate(): void
    {
        $request = new Request('GET', 'http://localhost/pages/about');

        $html = $this->controller->show($request, 'about');

        $this->assertIsString($html);
        $this->assertStringContainsString('<title>Sobre nosotros</title>', $html);
        $this->assertStringContainsString('<h1>Sobre nosotros</h1>', $html);
        // The Markdown body must have gone through the real "markdown"
        // filter (rendered to actual HTML), not been echoed as raw text.
        $this->assertStringContainsString(
            '<p>Página de prueba usada por los tests automatizados',
            $html
        );
        // Pages have no "completo" concept: only the two single-page
        // download links.
        $this->assertStringContainsString('href="/pages/about.pdf"', $html);
        $this->assertStringContainsString('href="/pages/about.md"', $html);
        $this->assertStringNotContainsString('full=1', $html);
    }

    public function testMarkdownFormatReturnsTheRawMarkdownBody(): void
    {
        $request = new Request('GET', 'http://localhost/pages/about.md');

        $markdown = $this->controller->show($request, 'about.md');

        $this->assertIsString($markdown);
        $this->assertStringContainsString('# Sobre nosotros', $markdown);
        $this->assertStringNotContainsString('<h1>', $markdown);
    }

    /**
     * "pages" is the only source whose include patterns allow ".html.twig"
     * files alongside Markdown, and show.html.twig branches on
     * page.isMarkdown() to pick "| markdown" vs "| twig" — a branch never
     * exercised by any test or the real LibreDTE site so far, since every
     * page used there is Markdown.
     */
    public function testHtmlTwigSourcePageIsRenderedThroughTheRealTwigFilter(): void
    {
        $request = new Request('GET', 'http://localhost/pages/bienvenida');

        $html = $this->controller->show($request, 'bienvenida');

        $this->assertIsString($html);
        // Proves the body went through Twig's "twig" filter (its own "page"
        // variable interpolated), not the Markdown filter nor raw text.
        $this->assertStringContainsString('Hola desde Twig: Página Twig', $html);
        $this->assertStringNotContainsString('{{ page.title }}', $html);
    }

    /**
     * Smoke test for the redesigned pdf/_layout (repeating header/footer,
     * page numbering, cover) — never exercised by any test before.
     */
    public function testPdfFormatRendersWithoutError(): void
    {
        $request = new Request('GET', 'http://localhost/pages/about.pdf');

        $pdf = $this->controller->show($request, 'about.pdf');

        $this->assertIsString($pdf);
        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    public function testUnknownPageBubblesAsContentNotFoundException(): void
    {
        $request = new Request('GET', 'http://localhost/pages/no-existe');

        $this->expectException(ContentNotFoundException::class);

        $this->controller->show($request, 'no-existe');
    }
}
