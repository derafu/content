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
use Derafu\Content\ContentFilters;
use Derafu\Content\ContentHtmlTag;
use Derafu\Content\ContentHtmlTags;
use Derafu\Content\ContentLoader;
use Derafu\Content\ContentService;
use Derafu\Content\ContentSplFileInfo;
use Derafu\Content\ContentTag;
use Derafu\Content\Exception\ContentNotFoundException;
use Derafu\Content\Plugin\Docs\DocsController;
use Derafu\Content\Plugin\Docs\DocsDoc;
use Derafu\Content\Plugin\Docs\DocsOpenApiView;
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
 * real Router: sidebarPath/sidebarCollapsible/sidebarDepth are pinned down
 * by a no-mock regression test.
 */
#[CoversClass(DocsController::class)]
#[CoversClass(AbstractContentController::class)]
#[UsesClass(DocsPlugin::class)]
#[UsesClass(DocsRegistry::class)]
#[UsesClass(DocsDoc::class)]
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
#[UsesClass(ContentFilters::class)]
#[UsesClass(DocsOpenApiView::class)]
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
        $router->setContext(new RequestContext(pathInfo: '/docs/guide/getting-started.json'));

        $request = new Request('GET', 'http://localhost/docs/guide/getting-started.json');

        $result = $this->controller($router)->show($request, 'guide/getting-started.json');

        $this->assertIsArray($result);
        $this->assertIsArray($result['data']);
        $this->assertSame('guide/getting-started', $result['data']['uri']);
        $this->assertIsString($result['data']['data']);
        $this->assertStringContainsString(
            'getting started, child',
            $result['data']['data']
        );
    }

    public function testUnknownDocBubblesAsContentNotFoundException(): void
    {
        $request = new Request('GET', 'http://localhost/docs/does-not-exist');

        $this->expectException(ContentNotFoundException::class);

        $this->controller()->show($request, 'does-not-exist');
    }

    public function testHtmlRenderShowsTheSidebarWithBothTopLevelDocsByDefault(): void
    {
        $router = RouterFixture::create();
        $router->setContext(new RequestContext(pathInfo: '/docs/guide/getting-started'));

        $request = new Request('GET', 'http://localhost/docs/guide/getting-started');
        $html = $this->controller($router)->show($request, 'guide/getting-started');

        $this->assertIsString($html);
        // Sidebar is present (default sidebarPath: true) with both
        // top-level docs and the nested child listed.
        $this->assertStringContainsString(
            '<nav class="col-md-3 col-lg-2 d-md-block bg-light">',
            $html
        );
        $this->assertStringContainsString('Home', $html);
        $this->assertStringContainsString('Guide', $html);
        $this->assertStringContainsString('Getting started', $html);
        // The active doc's link is highlighted.
        $this->assertMatchesRegularExpression(
            '#text-primary fw-bold[^>]*>\s*Getting started#',
            $html
        );
    }

    /**
     * "guide" has real children, so the HTML page must show the full PDF
     * and full Markdown links alongside the regular
     * single-page ones — plain, always-visible links, not hidden behind
     * a dropdown.
     */
    public function testHtmlRenderShowsDownloadLinksIncludingFullVariantsWhenThereAreChildren(): void
    {
        $router = RouterFixture::create();
        $router->setContext(new RequestContext(pathInfo: '/docs/guide'));

        $request = new Request('GET', 'http://localhost/docs/guide');
        $html = $this->controller($router)->show($request, 'guide');
        $this->assertIsString($html);

        $this->assertStringContainsString('href="/docs/guide.pdf"', $html);
        $this->assertStringContainsString('href="/docs/guide.md"', $html);
        $this->assertStringContainsString('href="/docs/guide.pdf?full=1"', $html);
        $this->assertStringContainsString('href="/docs/guide.md?full=1"', $html);
        // Both the single-page and the "full" Markdown get a "Copy to
        // clipboard" action, not just a download link.
        $this->assertStringContainsString('data-copy-md="/docs/guide.md"', $html);
        $this->assertStringContainsString('data-copy-md="/docs/guide.md?full=1"', $html);
    }

    /**
     * "guide/getting-started" has no children: only the two single-page
     * download links, no "full" variants.
     */
    public function testHtmlRenderShowsOnlySinglePageDownloadLinksForAChildlessDoc(): void
    {
        $router = RouterFixture::create();
        $router->setContext(new RequestContext(pathInfo: '/docs/guide/getting-started'));

        $request = new Request('GET', 'http://localhost/docs/guide/getting-started');
        $html = $this->controller($router)->show($request, 'guide/getting-started');
        $this->assertIsString($html);

        $this->assertStringContainsString('href="/docs/guide/getting-started.pdf"', $html);
        $this->assertStringContainsString('href="/docs/guide/getting-started.md"', $html);
        $this->assertStringNotContainsString('full=1', $html);
    }

    /**
     * "guide" has a real child ("guide/getting-started") in the fixture
     * hierarchy. Unlike the HTML template (gated behind the
     * "show_children" metadata flag), the Markdown export always lists
     * children when present: an AI agent fetching the .md body has no
     * sidebar to discover them otherwise.
     */
    public function testMarkdownFormatListsChildDocsWithAbsoluteUrls(): void
    {
        $router = RouterFixture::create();
        $router->setContext(new RequestContext(pathInfo: '/docs/guide.md'));

        $request = new Request('GET', 'http://localhost/docs/guide.md');
        $markdown = $this->controller($router)->show($request, 'guide.md');

        $this->assertIsString($markdown);
        $this->assertStringContainsString(
            '[Getting started](http://localhost/docs/guide/getting-started)',
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
        $router->setContext(new RequestContext(pathInfo: '/docs/guide.md'));

        $request = new Request('GET', 'http://localhost/docs/guide.md?full=1');
        $markdown = $this->controller($router)->show($request, 'guide.md');

        $this->assertIsString($markdown);
        $this->assertStringContainsString('## Getting started', $markdown);
        $this->assertStringContainsString(
            'child of the guide section, used to check',
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
        $router->setContext(new RequestContext(pathInfo: '/docs/guide/getting-started.md'));

        $withFull = $this->controller($router)->show(
            new Request('GET', 'http://localhost/docs/guide/getting-started.md?full=1'),
            'guide/getting-started.md'
        );
        $withoutFull = $this->controller($router)->show(
            new Request('GET', 'http://localhost/docs/guide/getting-started.md'),
            'guide/getting-started.md'
        );

        $this->assertSame($withoutFull, $withFull);
    }

    /**
     * "guide" itself has a root-relative frontmatter image and a body with
     * a root-relative Markdown image and a root-relative Markdown link.
     * The .md export must resolve all three to absolute URLs so the body
     * stays self-contained outside this website (see
     * AbstractContentItemUrlResolutionTest for the unit-level coverage of
     * the underlying resolution logic).
     */
    public function testMarkdownFormatResolvesTheDocsOwnImageAndLinkUrls(): void
    {
        $router = RouterFixture::create();
        $router->setContext(new RequestContext(pathInfo: '/docs/guide.md'));

        $request = new Request('GET', 'http://localhost/docs/guide.md');
        $markdown = $this->controller($router)->show($request, 'guide.md');

        $this->assertIsString($markdown);
        $this->assertStringContainsString(
            'image: "http://localhost/img/content/docs/guide/cover.png"',
            $markdown
        );
        $this->assertStringContainsString(
            '![Guide diagram](http://localhost/img/content/docs/guide/diagram.png)',
            $markdown
        );
        $this->assertStringContainsString(
            '[this child article](http://localhost/docs/guide/getting-started)',
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
        $router->setContext(new RequestContext(pathInfo: '/docs/guide.pdf'));

        $request = new Request('GET', 'http://localhost/docs/guide.pdf');
        $pdf = $this->controller($router)->show($request, 'guide.pdf');

        $this->assertIsString($pdf);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertSame(1, $this->countPdfPages($pdf));
    }

    /**
     * "?full=1" dumps "guide" together with its real child
     * ("guide/getting-started") into the SAME pdf: 1 cover page + 1 TOC
     * page + 1 page for "guide" + 1 page for its child.
     */
    public function testPdfFormatWithFullQueryParamIncludesChildDocsInOnePdf(): void
    {
        $router = RouterFixture::create();
        $router->setContext(new RequestContext(pathInfo: '/docs/guide.pdf'));

        $request = new Request('GET', 'http://localhost/docs/guide.pdf?full=1');
        $pdf = $this->controller($router)->show($request, 'guide.pdf');

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
        $router->setContext(new RequestContext(pathInfo: '/docs/guide/getting-started.pdf'));

        $request = new Request('GET', 'http://localhost/docs/guide/getting-started.pdf?full=1');
        $pdf = $this->controller($router)->show($request, 'guide/getting-started.pdf');

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
     * "draft-parent" is a draft top-level section, with a non-draft
     * child ("visible-child"). Neither must appear in the sidebar: today
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
        $this->assertIsString($html);

        $this->assertStringNotContainsString('Draft section', $html);
        $this->assertStringNotContainsString('Visible child', $html);
    }

    /**
     * "visible-section" is NOT draft, but its only child ("draft-note")
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
        $this->assertIsString($html);

        $this->assertStringContainsString('Visible section', $html);
        $this->assertStringNotContainsString('Draft note', $html);
    }

    /**
     * "past-deprecated" has an already-past deprecated() date: the real
     * Twig render must show the warning banner, using deprecatedAt() for
     * the actual date (not deprecated(), which is a bool) — a real
     * end-to-end check of the Twig syntax of every plugin's show template.
     */
    public function testHtmlRenderShowsTheDeprecatedBannerWithItsRealDate(): void
    {
        $router = RouterFixture::create();
        $router->setContext(new RequestContext(pathInfo: '/docs/past-deprecated'));

        $request = new Request('GET', 'http://localhost/docs/past-deprecated');
        $html = $this->controller($router)->show($request, 'past-deprecated');
        $this->assertIsString($html);

        $this->assertStringContainsString('is deprecated since 01/01/2020', $html);
    }

    /**
     * "future-deprecated" has a deprecated() date far in the future:
     * deprecated() must be false today, so the banner must not render at
     * all yet — the real Twig counterpart of
     * testFutureDeprecationDateDoesNotDisableIndexableOrSearchableYet.
     */
    public function testHtmlRenderDoesNotShowTheDeprecatedBannerBeforeItsDateArrives(): void
    {
        $router = RouterFixture::create();
        $router->setContext(new RequestContext(pathInfo: '/docs/future-deprecated'));

        $request = new Request('GET', 'http://localhost/docs/future-deprecated');
        $html = $this->controller($router)->show($request, 'future-deprecated');
        $this->assertIsString($html);

        $this->assertStringNotContainsString('is deprecated since', $html);
    }

    /**
     * "not-listed" is a top-level doc marked unlisted (not draft). Same
     * bug class as the draft one: DocsController feeds the sidebar
     * plugin->registry()->all(), unfiltered.
     */
    public function testHtmlRenderSidebarDoesNotListAnUnlistedTopLevelDoc(): void
    {
        $router = RouterFixture::create();
        $router->setContext(new RequestContext(pathInfo: '/docs/index'));

        $request = new Request('GET', 'http://localhost/docs/index');
        $html = $this->controller($router)->show($request, 'index');
        $this->assertIsString($html);

        $this->assertStringNotContainsString('Not Listed', $html);
    }

    /**
     * "visible-section" is not draft, and one of its children
     * ("unlisted-note") is not draft either — only unlisted. The
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
        $this->assertIsString($html);

        $this->assertStringContainsString('Visible section', $html);
        $this->assertStringNotContainsString('Unlisted note', $html);
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
        $this->assertIsString($html);

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

    public function testTagActionListsTheDocsThatHaveTheTag(): void
    {
        $router = RouterFixture::create();
        $router->setContext(new RequestContext(pathInfo: '/docs/tags/api'));

        $html = $this->controller($router)->tag(new Request('GET', 'http://localhost/docs/tags/api'), 'api');

        $this->assertStringContainsString('<title>api</title>', $html);
        $this->assertStringContainsString('A section with a fixture OpenAPI spec', $html);
    }

    public function testTagActionWithATagThatNoDocHasListsNoDocs(): void
    {
        $router = RouterFixture::create();
        $router->setContext(new RequestContext(pathInfo: '/docs/tags/unused'));

        $html = $this->controller($router)->tag(new Request('GET', 'http://localhost/docs/tags/unused'), 'unused');

        $this->assertStringContainsString('<title>unused</title>', $html);
        $this->assertStringNotContainsString('A section with a fixture OpenAPI spec', $html);
    }
}
