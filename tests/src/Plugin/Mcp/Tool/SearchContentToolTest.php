<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent\Plugin\Mcp\Tool;

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
use Derafu\Content\Plugin\Mcp\Tool\SearchContentTool;
use Derafu\Content\Plugin\Search\SearchEngine;
use Derafu\Content\Plugin\Search\SearchPlugin;
use Derafu\Content\Plugin\Search\SearchResultsFilter;
use Derafu\TestsContent\Support\ContentFixtures;
use Derafu\TestsContent\Support\FixtureHttpServer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Exercises SearchContentTool directly (no MCP JSON-RPC envelope) against
 * a real fixture HTTP server standing in for the upstream engine (Qdrant),
 * so the same post-filter added to SearchController in this session
 * (searchable()/allowed()/unlisted(), extracted to SearchResultsFilter)
 * runs here too instead of leaking stale/hidden index entries through
 * this second, independent consumer.
 */
#[CoversClass(SearchContentTool::class)]
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
final class SearchContentToolTest extends TestCase
{
    private static FixtureHttpServer $server;

    public static function setUpBeforeClass(): void
    {
        self::$server = FixtureHttpServer::start(19803);
    }

    public static function tearDownAfterClass(): void
    {
        self::$server->stop();
    }

    private function tool(): SearchContentTool
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

        return new SearchContentTool($contentService);
    }

    /**
     * Same fixture scenario used by SearchControllerTest: 5 raw results,
     * only "guia" (fully visible: not draft, not unlisted, searchable)
     * must survive.
     */
    public function testFiltersOutNonSearchableAndNoLongerAllowedResults(): void
    {
        $results = ($this->tool())('hola');

        $uris = array_column($results, 'uri');

        $this->assertSame(['guia'], $uris);
    }
}
