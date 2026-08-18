<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent\Plugin\Sitemap;

use Derafu\Content\ContentAuthor;
use Derafu\Content\ContentBag;
use Derafu\Content\ContentConfig;
use Derafu\Content\ContentContext;
use Derafu\Content\ContentLoader;
use Derafu\Content\ContentService;
use Derafu\Content\ContentSplFileInfo;
use Derafu\Content\ContentTag;
use Derafu\Content\Plugin\Docs\DocsDoc;
use Derafu\Content\Plugin\Docs\DocsPlugin;
use Derafu\Content\Plugin\Docs\DocsRegistry;
use Derafu\Content\Plugin\Sitemap\SitemapController;
use Derafu\Content\Plugin\Sitemap\SitemapPlugin;
use Derafu\Routing\ValueObject\RequestContext;
use Derafu\TestsContent\Support\ContentFixtures;
use Derafu\TestsContent\Support\RendererFixture;
use Derafu\TestsContent\Support\RouterFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Exercises SitemapController — implemented this session, only ever
 * manually curl-tested against the real LibreDTE site — with real
 * fixture content and a real Router/Twig render, producing actual XML.
 */
#[CoversClass(SitemapController::class)]
#[UsesClass(SitemapPlugin::class)]
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
final class SitemapControllerTest extends TestCase
{
    public function testIndexRendersOnlyIndexableItemsAsWellFormedXml(): void
    {
        $docsPlugin = new DocsPlugin(ContentFixtures::contentContext(), ['path' => 'docs']);
        $docsPlugin->loadContent(new ContentLoader(ContentFixtures::contentPath()));

        $contentService = ContentFixtures::contentService([
            'sitemap' => new SitemapPlugin(ContentFixtures::contentContext()),
            'docs' => $docsPlugin,
        ]);

        $router = RouterFixture::create();
        $router->setContext(new RequestContext(pathInfo: '/sitemap.xml'));

        $controller = new SitemapController($contentService, RendererFixture::create($router));

        $response = $controller->index();
        $xml = (string) $response->getBody();

        $this->assertSame(
            'application/xml; charset=UTF-8',
            $response->getHeaderLine('Content-Type')
        );

        $previousUseErrors = libxml_use_internal_errors(true);
        $document = simplexml_load_string($xml);
        libxml_use_internal_errors($previousUseErrors);
        $this->assertNotFalse($document, 'The sitemap is not well-formed XML.');

        // The sitemap uses a default (unprefixed) XML namespace, which
        // SimpleXML's xpath() only matches if it is explicitly registered
        // or, more simply, matched by local name instead.
        $locations = array_map(
            'strval',
            $document->xpath("//*[local-name()='url']/*[local-name()='loc']")
        );

        // All three real docs fixtures are long enough to be indexable.
        $this->assertContains('http://localhost/docs/guia/primeros-pasos', $locations);
        $this->assertContains('http://localhost/docs/guia', $locations);
        $this->assertContains('http://localhost/docs/index', $locations);

        // "docs/huerfano/hijo-perdido.md" exists on disk but has no
        // matching "docs/huerfano.md", so it is never loaded at all (see
        // the content hierarchy test) — it must not leak into the sitemap.
        $this->assertNotContains('http://localhost/docs/huerfano/hijo-perdido', $locations);
    }
}
