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
use Derafu\Content\ContentAuthor;
use Derafu\Content\ContentBag;
use Derafu\Content\ContentConfig;
use Derafu\Content\ContentContext;
use Derafu\Content\ContentLoader;
use Derafu\Content\ContentService;
use Derafu\Content\ContentSplFileInfo;
use Derafu\Content\ContentTag;
use Derafu\Content\Exception\ContentNotFoundException;
use Derafu\Content\Plugin\Docs\DocsController;
use Derafu\Content\Plugin\Docs\DocsDoc;
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
#[UsesClass(ContentService::class)]
#[UsesClass(ContentAuthor::class)]
#[UsesClass(ContentBag::class)]
#[UsesClass(ContentConfig::class)]
#[UsesClass(ContentContext::class)]
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
            RendererFixture::create($router)
        );
    }

    public function testJsonFormatReturnsTheFullContentArray(): void
    {
        $request = new Request('GET', 'http://localhost/docs/guia/primeros-pasos.json');

        $result = $this->controller()->show($request, 'guia/primeros-pasos.json');

        $this->assertIsArray($result);
        $this->assertSame('guia/primeros-pasos', $result['data']['uri']);
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
            RendererFixture::create($router)
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
