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

use Derafu\Content\ContentLoader;
use Derafu\Content\Plugin\Docs\DocsPlugin;
use Derafu\Renderer\Factory\RendererFactory;
use Derafu\Routing\ValueObject\RequestContext;
use Derafu\TestsContent\Support\ContentFixtures;
use Derafu\TestsContent\Support\RouterFixture;
use Derafu\Twig\Extension\RoutingExtension;
use Derafu\Twig\Extension\TwigExtension;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the "openapi" macro added to pdf/_macros.pdf.twig and
 * md/_macros.md.twig, which turns a doc's OpenAPI spec
 * (DocsDoc::openapiSpec(), lost entirely by both exports before this,
 * since the HTML view's Swagger UI widget is pure client-side JS) into a
 * static, complete endpoint reference.
 */
#[CoversNothing]
final class DocsShowOpenApiTest extends TestCase
{
    private function render(string $template, string $pathInfo): string
    {
        $plugin = new DocsPlugin(
            ContentFixtures::contentContext(),
            ['path' => 'docs']
        );
        $plugin->loadContent(new ContentLoader(ContentFixtures::contentPath()));

        $router = RouterFixture::create();
        $router->setContext(new RequestContext(pathInfo: $pathInfo));

        $twigService = RendererFactory::createTwigService([
            'paths' => [
                ContentFixtures::templatesPath(),
                dirname(__DIR__, 3) . '/../resources/templates',
            ],
            'extensions' => [new TwigExtension(), new RoutingExtension($router)],
        ]);

        $data = [
            'plugin' => $plugin,
            'doc' => $plugin->registry()->get('api'),
            'full' => false,
        ];

        return $twigService->render($template, $data);
    }

    public function testPdfRendersEveryEndpointWithParametersAndResponses(): void
    {
        $html = $this->render('docs/show.pdf.twig', '/docs/api.pdf');

        $this->assertStringContainsString('Fixture API', $html);
        $this->assertStringContainsString('GET', $html);
        $this->assertStringContainsString('/widgets', $html);
        $this->assertStringContainsString('List widgets', $html);
        $this->assertStringContainsString('<code>q</code>', $html);
        $this->assertStringContainsString('A list of widgets.', $html);
        $this->assertStringContainsString('&quot;name&quot;: &quot;Widget de prueba&quot;', $html);
    }

    /**
     * "/widgets" GET/POST both declare "tags: [Widgets]" — they must be
     * grouped under one "Widgets" section carrying the top-level tag's
     * own (CommonMark) description. "/gadgets" GET declares no tag, so
     * it must still show up, grouped under "Other" rather than dropped.
     */
    public function testPdfGroupsEndpointsByTheirTagWithTheTagDescription(): void
    {
        $html = $this->render('docs/show.pdf.twig', '/docs/api.pdf');

        $this->assertStringContainsString(
            '<h3 class="pdf-openapi-group-title">Widgets</h3>',
            $html
        );
        $this->assertStringContainsString('<li>Requiere autenticaci', $html);
        $this->assertStringContainsString(
            '<h3 class="pdf-openapi-group-title">Other</h3>',
            $html
        );
        $this->assertStringContainsString('/gadgets', $html);

        // "Widgets" group (with its 2 endpoints) comes before "Other".
        $this->assertLessThan(
            strpos($html, '<h3 class="pdf-openapi-group-title">Other</h3>'),
            strpos($html, '<h3 class="pdf-openapi-group-title">Widgets</h3>')
        );
    }

    /**
     * info.description, operation.description and parameter.description
     * are all documented by the OpenAPI spec as supporting CommonMark:
     * they must come out as real HTML in the PDF, not literal "**...**"
     * text.
     */
    public function testPdfRendersMarkdownDescriptionsAsRealHtml(): void
    {
        $html = $this->render('docs/show.pdf.twig', '/docs/api.pdf');

        $this->assertStringContainsString('<strong>Importante</strong>', $html);
        $this->assertStringContainsString('<li>Supports free-text filtering via <code>q</code>.</li>', $html);
        $this->assertStringContainsString('Free-text filter, e.g. <code>q=foo</code>.', $html);
        $this->assertStringNotContainsString('**Importante**', $html);
    }

    /**
     * A real mPDF table of contents (<tocpagebreak>/<tocentry>, the same
     * mechanism the "full" export's own index already uses), not a
     * hand-rolled <ul><a href="#..."> list — this gets a real page with
     * real page numbers once mpdf actually renders it, not just an
     * in-page jump link.
     *
     * It sits right inside the OpenAPI block itself (right after its own
     * description/servers), not before the doc's own main title — the
     * doc's body can have other content of its own before it.
     */
    public function testPdfIndexIsARealMpdfTableOfContentsBeforeTheEndpointDetails(): void
    {
        $html = $this->render('docs/show.pdf.twig', '/docs/api.pdf');

        $tocPosition = strpos($html, '<tocpagebreak name="api-openapi-toc"');
        $firstEndpointPosition = strpos($html, 'pdf-openapi-endpoint"');

        $this->assertNotFalse($tocPosition);
        $this->assertNotFalse($firstEndpointPosition);
        $this->assertLessThan($firstEndpointPosition, $tocPosition);
        $this->assertStringContainsString(
            '<tocentry',
            substr($html, $firstEndpointPosition)
        );
        $this->assertStringContainsString('name="api-openapi-toc"', $html);
    }

    /**
     * The TOC is nested, not flat: one level-0 entry per tag group
     * ("Widgets", "Other"), one level-1 entry per endpoint underneath —
     * mirroring how Swagger UI collapses endpoints under their tag.
     */
    public function testPdfTocEntriesAreNestedByGroupThenEndpoint(): void
    {
        $html = $this->render('docs/show.pdf.twig', '/docs/api.pdf');

        $this->assertMatchesRegularExpression(
            '/<tocentry\s+content="Widgets"\s+level="0"/s',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/<tocentry\s+content="List widgets"\s+level="1"/s',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/<tocentry\s+content="Other"\s+level="0"/s',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/<tocentry\s+content="List gadgets"\s+level="1"/s',
            $html
        );
    }

    public function testMarkdownRendersEveryEndpointWithParametersAndResponses(): void
    {
        $markdown = $this->render('docs/show.md.twig', '/docs/api.md');

        $this->assertStringContainsString('Fixture API', $markdown);
        $this->assertStringContainsString('#### GET /widgets', $markdown);
        $this->assertStringContainsString('- `q` (query, string)', $markdown);
        $this->assertStringContainsString('- `200` — A list of widgets.', $markdown);
        $this->assertStringContainsString('"name": "Widget de prueba"', $markdown);
    }

    /**
     * Same grouping as the PDF export: "/widgets" GET/POST under a
     * "Widgets" section (with the tag's own description), "/gadgets"
     * GET (no tag) under "Other".
     */
    public function testMarkdownGroupsEndpointsByTheirTagWithTheTagDescription(): void
    {
        $markdown = $this->render('docs/show.md.twig', '/docs/api.md');

        $this->assertStringContainsString('### Widgets', $markdown);
        $this->assertStringContainsString('Requiere autenticaci', $markdown);
        $this->assertStringContainsString('### Other', $markdown);
        $this->assertStringContainsString('#### GET /gadgets', $markdown);

        $this->assertLessThan(
            strpos($markdown, '### Other'),
            strpos($markdown, '### Widgets')
        );
    }

    /**
     * The .md export loses nothing: the CommonMark markup itself passes
     * through untouched (not HTML-escaped into "&quot;"/"&#39;" the way
     * a plain autoescaped string would).
     */
    public function testMarkdownKeepsTheSourceCommonMarkSyntaxIntact(): void
    {
        $markdown = $this->render('docs/show.md.twig', '/docs/api.md');

        $this->assertStringContainsString('**Importante**', $markdown);
        $this->assertStringContainsString('- Supports free-text filtering via `q`.', $markdown);
    }

    public function testMarkdownIndexListsEveryEndpointGroupedByTagBeforeTheEndpointDetails(): void
    {
        $markdown = $this->render('docs/show.md.twig', '/docs/api.md');

        $indexPosition = strpos($markdown, 'Index:');
        $firstEndpointHeadingPosition = strpos($markdown, '#### GET /widgets');

        $this->assertNotFalse($indexPosition);
        $this->assertNotFalse($firstEndpointHeadingPosition);
        $this->assertLessThan($firstEndpointHeadingPosition, $indexPosition);
        $this->assertStringContainsString("- Widgets\n", $markdown);
        $this->assertStringContainsString('  - List widgets', $markdown);
        $this->assertStringContainsString("- Other\n", $markdown);
        $this->assertStringContainsString('  - List gadgets', $markdown);
    }
}
