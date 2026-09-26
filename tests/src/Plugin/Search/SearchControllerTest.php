<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent\Plugin\Search;

use Derafu\Content\ContentAuthor;
use Derafu\Content\ContentBag;
use Derafu\Content\ContentConfig;
use Derafu\Content\ContentContext;
use Derafu\Content\ContentLoader;
use Derafu\Content\ContentService;
use Derafu\Content\ContentSplFileInfo;
use Derafu\Content\ContentTag;
use Derafu\Content\Exception\ContentNotFoundException;
use Derafu\Content\Plugin\Docs\DocsDoc;
use Derafu\Content\Plugin\Docs\DocsPlugin;
use Derafu\Content\Plugin\Docs\DocsRegistry;
use Derafu\Content\Plugin\Search\SearchController;
use Derafu\Content\Plugin\Search\SearchEngine;
use Derafu\Content\Plugin\Search\SearchPlugin;
use Derafu\Content\Plugin\Search\SearchResultsFilter;
use Derafu\Http\Request;
use Derafu\TestsContent\Support\ContentFixtures;
use Derafu\TestsContent\Support\FixtureHttpServer;
use Derafu\TestsContent\Support\RendererFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Exercises SearchController against a real fixture HTTP server standing
 * in for the upstream engine (Qdrant, in production) and a real Docs
 * registry, so the post-filter added in this session (searchable()/
 * allowed()/unlisted() applied to the raw engine results) runs for real
 * instead of being asserted against a hand-built array.
 */
#[CoversClass(SearchController::class)]
#[UsesClass(SearchPlugin::class)]
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
#[UsesClass(SearchEngine::class)]
#[UsesClass(SearchResultsFilter::class)]
final class SearchControllerTest extends TestCase
{
    private static FixtureHttpServer $server;

    public static function setUpBeforeClass(): void
    {
        self::$server = FixtureHttpServer::start(19802);
    }

    public static function tearDownAfterClass(): void
    {
        self::$server->stop();
    }

    private function controller(): SearchController
    {
        $docsPlugin = new DocsPlugin(ContentFixtures::contentContext(), ['path' => 'docs']);
        $docsPlugin->loadContent(new ContentLoader(ContentFixtures::contentPath()));

        $searchPlugin = new SearchPlugin(ContentFixtures::contentContext(), [
            'url' => self::$server->url() . '/?scenario=results_mixed_searchability&text=%s',
        ]);

        $contentService = ContentFixtures::contentService([
            'search' => $searchPlugin,
            'docs' => $docsPlugin,
        ]);

        return new SearchController($contentService, RendererFixture::create());
    }

    /**
     * The fixture engine returns 5 raw results: one fully visible doc
     * ("guia"), one searchable:false, one draft (a stale index entry
     * that later became draft), one unlisted, and one whose uri no
     * longer exists in the registry at all — only "guia" must survive.
     */
    public function testApiIndexFiltersOutNonSearchableAndNoLongerAllowedResults(): void
    {
        $request = new Request('GET', 'http://localhost/api/search.json?q=hola');

        $results = $this->controller()->api_index($request);

        $uris = array_column($results, 'uri');

        $this->assertSame(['guia'], $uris);
    }
}
