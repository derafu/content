<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent\Plugin\Docs;

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
use Derafu\Content\Plugin\Docs\DocsController;
use Derafu\Content\Plugin\Docs\DocsDoc;
use Derafu\Content\Plugin\Docs\DocsOpenApiEndpoint;
use Derafu\Content\Plugin\Docs\DocsOpenApiEndpointGroup;
use Derafu\Content\Plugin\Docs\DocsOpenApiParameter;
use Derafu\Content\Plugin\Docs\DocsOpenApiResponse;
use Derafu\Content\Plugin\Docs\DocsOpenApiSpec;
use Derafu\Content\Plugin\Docs\DocsOpenApiTag;
use Derafu\Content\Plugin\Docs\DocsPlugin;
use Derafu\Content\Plugin\Docs\DocsRegistry;
use Derafu\Http\Request;
use Derafu\Routing\Contract\RouterInterface;
use Derafu\Routing\ValueObject\RequestContext;
use Derafu\TestsContent\Support\ContentFixtures;
use Derafu\TestsContent\Support\RendererFixture;
use Derafu\TestsContent\Support\RouterFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Exercises DocsController end to end, including a real Twig render of the
 * sidebar (layouts/docs.html.twig + docs/_nav_item.html.twig) through a
 * real Router — the same code path exercised manually against LibreDTE
 * while implementing sidebarPath/sidebarCollapsible/sidebarDepth, now
 * pinned down as a permanent, no-mock regression test.
 */
#[CoversClass(DocsController::class)]
#[CoversClass(AbstractContentController::class)]
#[UsesClass(DocsPlugin::class)]
#[UsesClass(DocsRegistry::class)]
#[UsesClass(DocsDoc::class)]
#[UsesClass(DocsOpenApiSpec::class)]
#[UsesClass(DocsOpenApiEndpoint::class)]
#[UsesClass(DocsOpenApiEndpointGroup::class)]
#[UsesClass(DocsOpenApiParameter::class)]
#[UsesClass(DocsOpenApiResponse::class)]
#[UsesClass(DocsOpenApiTag::class)]
#[UsesClass(ContentAttachment::class)]
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
final class DocsControllerTest extends TestCase
{
    private DocsPlugin $plugin;

    protected function setUp(): void
    {
        $this->plugin = new DocsPlugin(
            ContentFixtures::contentContext(),
            ['path' => 'docs']
        );
        $this->plugin->loadContent(new ContentLoader(ContentFixtures::contentPath()));
    }

    private function controller(?RouterInterface $router = null): DocsController
    {
        return new DocsController(
            ContentFixtures::contentService(['docs' => $this->plugin]),
            RendererFixture::create($router),
            $router ?? RouterFixture::create()
        );
    }

    public function testJsonFormatReturnsTheFullContentArray(): void
    {
        $router = RouterFixture::create();
        $router->setContext(new RequestContext(pathInfo: '/docs/guia/primeros-pasos.json'));

        $request = new Request('GET', 'http://localhost/docs/guia/primeros-pasos.json');

        $result = $this->controller($router)->show($request, 'guia/primeros-pasos.json');

        $this->assertIsArray($result);
        $this->assertSame('guia/primeros-pasos', $result['data']['uri']);
        $this->assertStringContainsString(
            'primeros pasos, hijo',
            $result['data']['data']
        );
    }

    public function testUnknownDocBubblesAsContentNotFoundException(): void
    {
        $request = new Request('GET', 'http://localhost/docs/no-existe');

        $this->expectException(ContentNotFoundException::class);

        $this->controller()->show($request, 'no-existe');
    }

    public function testHtmlRenderShowsTheSidebarWithBothTopLevelDocsByDefault(): void
    {
        $router = RouterFixture::create();
        $router->setContext(new RequestContext(pathInfo: '/docs/guia/primeros-pasos'));

        $request = new Request('GET', 'http://localhost/docs/guia/primeros-pasos');
        $html = $this->controller($router)->show($request, 'guia/primeros-pasos');

        $this->assertIsString($html);
        // Sidebar is present (default sidebarPath: true) with both
        // top-level docs and the nested child listed.
        $this->assertStringContainsString(
            '<nav class="col-md-3 col-lg-2 d-md-block bg-light">',
            $html
        );
        $this->assertStringContainsString('Inicio', $html);
        $this->assertStringContainsString('Guía', $html);
        $this->assertStringContainsString('Primeros pasos', $html);
        // The active doc's link is highlighted.
        $this->assertMatchesRegularExpression(
            '#text-primary fw-bold[^>]*>\s*Primeros pasos#',
            $html
        );
    }

    /**
     * "guia" has real children, so the HTML page must show the "PDF
     * completo"/"Markdown completo" links alongside the regular
     * single-page ones — plain, always-visible links, not hidden behind
     * a dropdown.
     */
    public function testHtmlRenderShowsDownloadLinksIncludingFullVariantsWhenThereAreChildren(): void
    {
        $router = RouterFixture::create();
        $router->setContext(new RequestContext(pathInfo: '/docs/guia'));

        $request = new Request('GET', 'http://localhost/docs/guia');
        $html = $this->controller($router)->show($request, 'guia');

        $this->assertStringContainsString('href="/docs/guia.pdf"', $html);
        $this->assertStringContainsString('href="/docs/guia.md"', $html);
        $this->assertStringContainsString('href="/docs/guia.pdf?full=1"', $html);
        $this->assertStringContainsString('href="/docs/guia.md?full=1"', $html);
        // Both the single-page and the "full" Markdown get a "Copy to
        // clipboard" action, not just a download link.
        $this->assertStringContainsString('data-copy-md="/docs/guia.md"', $html);
        $this->assertStringContainsString('data-copy-md="/docs/guia.md?full=1"', $html);
    }

    /**
     * "guia/primeros-pasos" has no children: only the two single-page
     * download links, no "completo" variants.
     */
    public function testHtmlRenderShowsOnlySinglePageDownloadLinksForAChildlessDoc(): void
    {
        $router = RouterFixture::create();
        $router->setContext(new RequestContext(pathInfo: '/docs/guia/primeros-pasos'));

        $request = new Request('GET', 'http://localhost/docs/guia/primeros-pasos');
        $html = $this->controller($router)->show($request, 'guia/primeros-pasos');

        $this->assertStringContainsString('href="/docs/guia/primeros-pasos.pdf"', $html);
        $this->assertStringContainsString('href="/docs/guia/primeros-pasos.md"', $html);
        $this->assertStringNotContainsString('full=1', $html);
    }

    /**
     * "guia" has a real child ("guia/primeros-pasos") in the fixture
     * hierarchy. Unlike the HTML template (gated behind the
     * "show_children" metadata flag), the Markdown export always lists
     * children when present: an AI agent fetching the .md body has no
     * sidebar to discover them otherwise.
     */
    public function testMarkdownFormatListsChildDocsWithAbsoluteUrls(): void
    {
        $router = RouterFixture::create();
        $router->setContext(new RequestContext(pathInfo: '/docs/guia.md'));

        $request = new Request('GET', 'http://localhost/docs/guia.md');
        $markdown = $this->controller($router)->show($request, 'guia.md');

        $this->assertIsString($markdown);
        $this->assertStringContainsString(
            '[Primeros pasos](http://localhost/docs/guia/primeros-pasos)',
            $markdown
        );
    }

    /**
     * "?full=1" on the .md export embeds the child's own title and full
     * body inline (as a "## " heading), instead of just linking to it —
     * mirrors the PDF's "full" bundling, but with no pages/TOC concept.
     */
    public function testMarkdownFormatWithFullQueryParamEmbedsChildDocBodyInline(): void
    {
        $router = RouterFixture::create();
        $router->setContext(new RequestContext(pathInfo: '/docs/guia.md'));

        $request = new Request('GET', 'http://localhost/docs/guia.md?full=1');
        $markdown = $this->controller($router)->show($request, 'guia.md');

        $this->assertIsString($markdown);
        $this->assertStringContainsString('## Primeros pasos', $markdown);
        $this->assertStringContainsString(
            'hijo de la sección guía, usado para validar',
            $markdown
        );
        $this->assertStringNotContainsString('Pages in this section', $markdown);
    }

    /**
     * "?full=1" on a doc WITHOUT children is a no-op: it must render
     * byte-for-byte the same Markdown body as without the flag.
     */
    public function testMarkdownFormatWithFullQueryParamOnAChildlessDocIgnoresTheFlag(): void
    {
        $router = RouterFixture::create();
        $router->setContext(new RequestContext(pathInfo: '/docs/guia/primeros-pasos.md'));

        $withFull = $this->controller($router)->show(
            new Request('GET', 'http://localhost/docs/guia/primeros-pasos.md?full=1'),
            'guia/primeros-pasos.md'
        );
        $withoutFull = $this->controller($router)->show(
            new Request('GET', 'http://localhost/docs/guia/primeros-pasos.md'),
            'guia/primeros-pasos.md'
        );

        $this->assertSame($withoutFull, $withFull);
    }

    /**
     * "guia" itself has a root-relative frontmatter image and a body with
     * a root-relative Markdown image and a root-relative Markdown link.
     * The .md export must resolve all three to absolute URLs so the body
     * stays self-contained outside this website (see
     * AbstractContentItemUrlResolutionTest for the unit-level coverage of
     * the underlying resolution logic).
     */
    public function testMarkdownFormatResolvesTheDocsOwnImageAndLinkUrls(): void
    {
        $router = RouterFixture::create();
        $router->setContext(new RequestContext(pathInfo: '/docs/guia.md'));

        $request = new Request('GET', 'http://localhost/docs/guia.md');
        $markdown = $this->controller($router)->show($request, 'guia.md');

        $this->assertIsString($markdown);
        $this->assertStringContainsString(
            'image: "http://localhost/img/content/docs/guia/cover.png"',
            $markdown
        );
        $this->assertStringContainsString(
            '![Diagrama de la guía](http://localhost/img/content/docs/guia/diagrama.png)',
            $markdown
        );
        $this->assertStringContainsString(
            '[este artículo hijo](http://localhost/docs/guia/primeros-pasos)',
            $markdown
        );
    }

    /**
     * No test exercised docs/show.pdf.twig before this one — a smoke test
     * proving the .data(link_urls) call added to it actually renders
     * without error and produces a real PDF.
     */
    public function testPdfFormatRendersWithoutError(): void
    {
        $router = RouterFixture::create();
        $router->setContext(new RequestContext(pathInfo: '/docs/guia.pdf'));

        $request = new Request('GET', 'http://localhost/docs/guia.pdf');
        $pdf = $this->controller($router)->show($request, 'guia.pdf');

        $this->assertIsString($pdf);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertSame(1, $this->countPdfPages($pdf));
    }

    /**
     * "?full=1" dumps "guia" together with its real child
     * ("guia/primeros-pasos") into the SAME pdf: 1 cover page + 1 TOC
     * page + 1 page for "guia" + 1 page for its child.
     */
    public function testPdfFormatWithFullQueryParamIncludesChildDocsInOnePdf(): void
    {
        $router = RouterFixture::create();
        $router->setContext(new RequestContext(pathInfo: '/docs/guia.pdf'));

        $request = new Request('GET', 'http://localhost/docs/guia.pdf?full=1');
        $pdf = $this->controller($router)->show($request, 'guia.pdf');

        $this->assertIsString($pdf);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertSame(4, $this->countPdfPages($pdf));
    }

    /**
     * "?full=1" on a doc WITHOUT children is a no-op: there is nothing to
     * bundle, so it falls straight through to the regular single-item
     * PDF — no cover, no table of contents.
     */
    public function testPdfFormatWithFullQueryParamOnAChildlessDocIgnoresTheFlag(): void
    {
        $router = RouterFixture::create();
        $router->setContext(new RequestContext(pathInfo: '/docs/guia/primeros-pasos.pdf'));

        $request = new Request('GET', 'http://localhost/docs/guia/primeros-pasos.pdf?full=1');
        $pdf = $this->controller($router)->show($request, 'guia/primeros-pasos.pdf');

        $this->assertIsString($pdf);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertSame(1, $this->countPdfPages($pdf));
    }

    private function countPdfPages(string $pdf): int
    {
        return preg_match_all('/\/Type\s*\/Page[^s]/', $pdf);
    }

    /**
     * The "api" fixture doc's OpenAPI spec renders a real mpdf table of
     * contents (<tocpagebreak>/<tocentry>, not a hand-rolled in-page link
     * list) for its endpoints: mpdf must actually accept those tags and
     * produce a real, bigger PDF, not merely pass through DocsShowOpenApi
     * Test's intermediate-HTML assertions.
     */
    public function testPdfFormatRendersARealTableOfContentsForTheOpenapiSpec(): void
    {
        $router = RouterFixture::create();
        $router->setContext(new RequestContext(pathInfo: '/docs/api.pdf'));

        $request = new Request('GET', 'http://localhost/docs/api.pdf');
        $pdf = $this->controller($router)->show($request, 'api.pdf');

        $this->assertIsString($pdf);
        $this->assertStringStartsWith('%PDF-', $pdf);
        // The doc's own content plus the OpenAPI index page it forces
        // via <tocpagebreak>: at least 2 pages, never just the 1 a doc
        // with no OpenAPI spec would produce.
        $this->assertGreaterThanOrEqual(2, $this->countPdfPages($pdf));
    }

    /**
     * "borrador-padre" is a draft top-level section, with a non-draft
     * child ("hijo-visible"). Neither must appear in the sidebar: today
     * DocsController feeds it plugin->registry()->all(), which returns
     * every loaded doc regardless of allowed() — this is the real bug
     * reported for this package (a draft leaks into the nav in
     * production even though direct access to it is already blocked).
     */
    public function testHtmlRenderSidebarDoesNotListADraftTopLevelSection(): void
    {
        $router = RouterFixture::create();
        $router->setContext(new RequestContext(pathInfo: '/docs/index'));

        $request = new Request('GET', 'http://localhost/docs/index');
        $html = $this->controller($router)->show($request, 'index');

        $this->assertStringNotContainsString('Sección borrador', $html);
        $this->assertStringNotContainsString('Hijo visible', $html);
    }

    /**
     * "seccion-visible" is NOT draft, but its only child ("nota-borrador")
     * is. The parent must still show in the sidebar, but the draft child
     * must not leak through the nested recursion of the nav macro, which
     * calls item.children directly (bypassing whatever the controller
     * filtered at the top level).
     */
    public function testHtmlRenderSidebarDoesNotListADraftChildNestedUnderAnAllowedSection(): void
    {
        $router = RouterFixture::create();
        $router->setContext(new RequestContext(pathInfo: '/docs/index'));

        $request = new Request('GET', 'http://localhost/docs/index');
        $html = $this->controller($router)->show($request, 'index');

        $this->assertStringContainsString('Sección visible', $html);
        $this->assertStringNotContainsString('Nota borrador', $html);
    }

    /**
     * "pasado-deprecado" has an already-past deprecated() date: the real
     * Twig render must show the warning banner, using deprecatedAt() for
     * the actual date (not deprecated(), which is a bool since this
     * session's fix) — a real end-to-end check that the template rename
     * across every plugin's show template didn't break the Twig syntax.
     */
    public function testHtmlRenderShowsTheDeprecatedBannerWithItsRealDate(): void
    {
        $router = RouterFixture::create();
        $router->setContext(new RequestContext(pathInfo: '/docs/pasado-deprecado'));

        $request = new Request('GET', 'http://localhost/docs/pasado-deprecado');
        $html = $this->controller($router)->show($request, 'pasado-deprecado');

        $this->assertStringContainsString('is deprecated since 01/01/2020', $html);
    }

    /**
     * "futuro-deprecado" has a deprecated() date far in the future:
     * deprecated() must be false today, so the banner must not render at
     * all yet — the real Twig counterpart of
     * testFutureDeprecationDateDoesNotDisableIndexableOrSearchableYet.
     */
    public function testHtmlRenderDoesNotShowTheDeprecatedBannerBeforeItsDateArrives(): void
    {
        $router = RouterFixture::create();
        $router->setContext(new RequestContext(pathInfo: '/docs/futuro-deprecado'));

        $request = new Request('GET', 'http://localhost/docs/futuro-deprecado');
        $html = $this->controller($router)->show($request, 'futuro-deprecado');

        $this->assertStringNotContainsString('is deprecated since', $html);
    }

    /**
     * "no-listado" is a top-level doc marked unlisted (not draft). Same
     * bug class as the draft one: DocsController feeds the sidebar
     * plugin->registry()->all(), unfiltered.
     */
    public function testHtmlRenderSidebarDoesNotListAnUnlistedTopLevelDoc(): void
    {
        $router = RouterFixture::create();
        $router->setContext(new RequestContext(pathInfo: '/docs/index'));

        $request = new Request('GET', 'http://localhost/docs/index');
        $html = $this->controller($router)->show($request, 'index');

        $this->assertStringNotContainsString('No Listado', $html);
    }

    /**
     * "seccion-visible" is not draft, and one of its children
     * ("nota-no-listada") is not draft either — only unlisted. The
     * parent must still show, but this child must not leak through the
     * nested recursion of the nav macro: visibleChildren() must exclude
     * it just like it excludes a draft child.
     */
    public function testHtmlRenderSidebarDoesNotListAnUnlistedChildNestedUnderAnAllowedSection(): void
    {
        $router = RouterFixture::create();
        $router->setContext(new RequestContext(pathInfo: '/docs/index'));

        $request = new Request('GET', 'http://localhost/docs/index');
        $html = $this->controller($router)->show($request, 'index');

        $this->assertStringContainsString('Sección visible', $html);
        $this->assertStringNotContainsString('Nota no listada', $html);
    }

    public function testHtmlRenderHidesTheSidebarWhenSidebarPathIsDisabled(): void
    {
        $plugin = new DocsPlugin(
            ContentFixtures::contentContext(),
            ['path' => 'docs', 'sidebarPath' => false]
        );
        $plugin->loadContent(new ContentLoader(ContentFixtures::contentPath()));

        $router = RouterFixture::create();
        $router->setContext(new RequestContext(pathInfo: '/docs/index'));

        $controller = new DocsController(
            ContentFixtures::contentService(['docs' => $plugin]),
            RendererFixture::create($router),
            $router
        );

        $request = new Request('GET', 'http://localhost/docs/index');
        $html = $controller->show($request, 'index');

        // The outer plugin sidebar (nav + tags) must be gone. The doc's own
        // inner table-of-contents column ("col-md-3" inside the content
        // area) is unrelated and still present, so assert on the specific
        // sidebar markup rather than the reused Bootstrap class alone.
        $this->assertStringNotContainsString(
            '<nav class="col-md-3 col-lg-2 d-md-block bg-light">',
            $html
        );
        $this->assertStringContainsString('<main class="col-12">', $html);
    }
}
