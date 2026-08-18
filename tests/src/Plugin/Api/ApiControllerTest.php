<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent\Plugin\Api;

use Derafu\Content\ContentAuthor;
use Derafu\Content\ContentBag;
use Derafu\Content\ContentConfig;
use Derafu\Content\ContentContext;
use Derafu\Content\ContentLoader;
use Derafu\Content\ContentService;
use Derafu\Content\ContentSplFileInfo;
use Derafu\Content\ContentTag;
use Derafu\Content\Plugin\Api\ApiController;
use Derafu\Content\Plugin\Api\ApiPlugin;
use Derafu\Content\Plugin\Docs\DocsDoc;
use Derafu\Content\Plugin\Docs\DocsPlugin;
use Derafu\Content\Plugin\Docs\DocsRegistry;
use Derafu\Content\Plugin\Pages\PagesPage;
use Derafu\Content\Plugin\Pages\PagesPlugin;
use Derafu\Content\Plugin\Pages\PagesRegistry;
use Derafu\Http\Request;
use Derafu\Routing\ValueObject\RequestContext;
use Derafu\TestsContent\Support\ContentFixtures;
use Derafu\TestsContent\Support\RouterFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Exercises ApiController — refactored this session to use
 * ContentService::allContent() instead of the removed
 * ApiPlugin::knowledge() — against two real content plugins at once
 * (Docs and Pages), with a real Router (its "link" field needs real URL
 * generation, including for Pages' own route which lives outside this
 * package's own content-routes.yaml import chain).
 */
#[CoversClass(ApiController::class)]
#[UsesClass(ApiPlugin::class)]
#[UsesClass(DocsPlugin::class)]
#[UsesClass(DocsRegistry::class)]
#[UsesClass(DocsDoc::class)]
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
#[UsesClass(ContentTag::class)]
final class ApiControllerTest extends TestCase
{
    private ApiController $controller;

    protected function setUp(): void
    {
        $docsPlugin = new DocsPlugin(ContentFixtures::contentContext(), ['path' => 'docs']);
        $docsPlugin->loadContent(new ContentLoader(ContentFixtures::contentPath()));

        $pagesPlugin = new PagesPlugin(ContentFixtures::contentContext(), ['path' => 'pages']);
        $pagesPlugin->loadContent(new ContentLoader(ContentFixtures::contentPath()));

        $contentService = ContentFixtures::contentService([
            'api' => new ApiPlugin(ContentFixtures::contentContext()),
            'docs' => $docsPlugin,
            'pages' => $pagesPlugin,
        ]);

        $router = RouterFixture::create([
            'homepage' => ['path' => '/', 'handler' => 'App\\Controller\\HomeController::index'],
        ]);
        $router->setContext(new RequestContext(pathInfo: '/api/content.json'));

        $this->controller = new ApiController($contentService, $router);
    }

    public function testIndexAggregatesContentFromEveryContentPlugin(): void
    {
        $request = new Request('GET', 'http://localhost/api/content.json');

        $result = $this->controller->index($request);

        $types = array_column($result['data'], 'type');

        $this->assertContains('docs', $types);
        $this->assertContains('pages', $types);
        $this->assertSame(count($result['data']), $result['meta']['count']);
    }

    public function testEachItemsLinkIsARealGeneratedUrl(): void
    {
        $request = new Request('GET', 'http://localhost/api/content.json');

        $result = $this->controller->index($request);

        $byUri = array_column($result['data'], null, 'uri');

        $this->assertSame(
            'http://localhost/docs/guia.json',
            $byUri['guia']['link']
        );
        $this->assertSame(
            'http://localhost/pages/about.json',
            $byUri['about']['link']
        );
    }

    public function testDraftPagesAreExcludedFromTheExport(): void
    {
        $request = new Request('GET', 'http://localhost/api/content.json');

        $result = $this->controller->index($request);

        $uris = array_column($result['data'], 'uri');

        $this->assertNotContains('borrador', $uris);
    }

    public function testFilterQueryParameterNarrowsTheExportToOneType(): void
    {
        $request = new Request('GET', 'http://localhost/api/content.json?type=pages');

        $result = $this->controller->index($request);

        $types = array_unique(array_column($result['data'], 'type'));

        $this->assertSame(['pages'], $types);
    }
}
