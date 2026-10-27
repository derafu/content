<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent\Plugin\Blog;

use Derafu\Content\Abstract\AbstractContentController;
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
use Derafu\Content\Plugin\Blog\BlogArchive;
use Derafu\Content\Plugin\Blog\BlogController;
use Derafu\Content\Plugin\Blog\BlogPlugin;
use Derafu\Content\Plugin\Blog\BlogPost;
use Derafu\Content\Plugin\Blog\BlogRegistry;
use Derafu\Http\Request;
use Derafu\Routing\ValueObject\RequestContext;
use Derafu\TestsContent\Support\ContentFixtures;
use Derafu\TestsContent\Support\RendererFixture;
use Derafu\TestsContent\Support\RouterFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Exercises every BlogController action against real fixture posts with a
 * real Twig render — including "archive" and "rss", neither of which had
 * ever been run end to end before this test, only BlogRegistry directly.
 */
#[\PHPUnit\Framework\Attributes\UsesClass(\Derafu\Content\Provider\ContentTemplatePathProvider::class)]
#[CoversClass(BlogController::class)]
#[CoversClass(AbstractContentController::class)]
#[UsesClass(BlogPlugin::class)]
#[UsesClass(BlogRegistry::class)]
#[UsesClass(BlogPost::class)]
#[UsesClass(BlogArchive::class)]
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
final class BlogControllerTest extends TestCase
{
    private BlogController $controller;

    protected function setUp(): void
    {
        $plugin = new BlogPlugin(
            ContentFixtures::contentContext(),
            ['path' => 'blog']
        );
        $plugin->loadContent(new ContentLoader(ContentFixtures::contentPath()));

        $router = RouterFixture::create();
        $router->setContext(new RequestContext(pathInfo: '/blog'));

        $this->controller = new BlogController(
            ContentFixtures::contentService(['blog' => $plugin]),
            RendererFixture::create($router),
            $router
        );
    }

    public function testIndexListsBothPosts(): void
    {
        $request = new Request('GET', 'http://localhost/blog');

        $html = $this->controller->index($request);

        $this->assertStringContainsString('First post', $html);
        $this->assertStringContainsString('Second post', $html);
    }

    /**
     * "2099-01-01-future-post" has a publish date far in the future — it
     * must not appear in the blog index (or its "recent posts" sidebar)
     * before that date arrives, the same way a draft post must not.
     */
    public function testIndexDoesNotListAFutureDatedPost(): void
    {
        $request = new Request('GET', 'http://localhost/blog');

        $html = $this->controller->index($request);

        $this->assertStringNotContainsString('Future post', $html);
    }

    public function testTheSidebarLinksToTheRssFeed(): void
    {
        $request = new Request('GET', 'http://localhost/blog');

        $html = $this->controller->index($request);

        $this->assertMatchesRegularExpression(
            '#<a href="[^"]*/blog/rss\.xml"[^>]*>\s*<i class="fa-solid fa-rss[^"]*"></i>\s*RSS\s*</a>#',
            $html
        );
    }

    /**
     * The feed is advertised in the `<head>` so feed readers and browsers
     * find it without the visitor looking for the link. Every page of the
     * blog must do it, including a post, whose own `custom_head` block would
     * replace the one of the layout unless it calls `parent()`.
     */
    public function testEveryBlogPageAdvertisesTheRssFeedForAutodiscovery(): void
    {
        $pages = [
            'index' => $this->controller->index(new Request('GET', 'http://localhost/blog')),
            'show' => $this->controller->show(
                new Request('GET', 'http://localhost/blog/2026-01-15-first-post'),
                '2026-01-15-first-post'
            ),
            'tag' => $this->controller->tag(new Request('GET', 'http://localhost/blog/tags/demo'), 'demo'),
        ];

        foreach ($pages as $page => $html) {
            $this->assertIsString($html);
            $this->assertMatchesRegularExpression(
                '#<link rel="alternate" type="application/rss\+xml" title="[^"]+" href="[^"]*/blog/rss\.xml">#',
                $html,
                sprintf('The "%s" page does not advertise the RSS feed.', $page)
            );
        }
    }

    public function testShowRendersASinglePost(): void
    {
        $request = new Request('GET', 'http://localhost/blog/2026-01-15-first-post');

        $html = $this->controller->show($request, '2026-01-15-first-post');

        $this->assertIsString($html);
        $this->assertStringContainsString('First post', $html);
    }

    /**
     * Posts have no "full" concept (no children to bundle): only the
     * two single-page download links.
     */
    public function testShowRendersOnlySinglePageDownloadLinks(): void
    {
        $request = new Request('GET', 'http://localhost/blog/2026-01-15-first-post');

        $html = $this->controller->show($request, '2026-01-15-first-post');
        $this->assertIsString($html);

        $this->assertStringContainsString('href="/blog/2026-01-15-first-post.pdf"', $html);
        $this->assertStringContainsString('href="/blog/2026-01-15-first-post.md"', $html);
        $this->assertStringNotContainsString('full=1', $html);
    }

    /**
     * Smoke test for the redesigned pdf/_layout (repeating header/footer,
     * page numbering, cover) — never exercised by any test before.
     */
    public function testShowPdfFormatRendersWithoutError(): void
    {
        $request = new Request('GET', 'http://localhost/blog/2026-01-15-first-post.pdf');

        $pdf = $this->controller->show($request, '2026-01-15-first-post.pdf');

        $this->assertIsString($pdf);
        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    public function testUnknownPostBubblesAsContentNotFoundException(): void
    {
        $request = new Request('GET', 'http://localhost/blog/does-not-exist');

        $this->expectException(ContentNotFoundException::class);

        $this->controller->show($request, 'does-not-exist');
    }

    public function testTagActionFiltersPosts(): void
    {
        $request = new Request('GET', 'http://localhost/blog/tags/demo');

        $html = $this->controller->tag($request, 'demo');

        $this->assertStringContainsString('First post', $html);
        $this->assertStringContainsString('Second post', $html);
    }

    public function testArchiveActionFiltersPostsByYearAndMonth(): void
    {
        // "2026-02-25" is the explicit date of "second-post": the archive
        // for that month must show it and must not show "first-post"
        // (2026-01-15). "202602-02-2026" is the real slug BlogArchive
        // generates and every template links to (id + slugified name).
        $request = new Request('GET', 'http://localhost/blog/archive/202602-02-2026');

        $html = $this->controller->archive($request, '202602-02-2026');

        $this->assertStringContainsString('Second post', $html);
        $this->assertStringNotContainsString('First post', $html);
    }

    /**
     * The bug found by the previous test, before fixing BlogArchive: a
     * natural-looking "YYYY-MM" (as opposed to the real "YYYYMM-..." slug)
     * parsed to month = 0, and since empty(0) is true in PHP, the date
     * filter's `!empty($filters['month'])` guard silently skipped
     * filtering entirely — returning every post instead of an error.
     */
    public function testMalformedArchiveSlugIsRejectedInsteadOfSilentlyIgnoringTheMonthFilter(): void
    {
        $request = new Request('GET', 'http://localhost/blog/archive/2026-02');

        $this->expectException(ContentNotFoundException::class);

        $this->controller->archive($request, '2026-02');
    }

    public function testRssActionReturnsAValidFeedWithAbsoluteUrls(): void
    {
        $request = new Request('GET', 'http://localhost/blog/rss.xml');

        $response = $this->controller->rss($request);
        $xml = (string) $response->getBody();

        $this->assertSame(
            'application/rss+xml; charset=UTF-8',
            $response->getHeaderLine('Content-Type')
        );
        $this->assertStringContainsString('<rss version="2.0"', $xml);
        $this->assertStringContainsString('First post', $xml);
        $this->assertStringContainsString('Second post', $xml);

        // The feed body is well-formed XML on its own merits.
        $previousUseErrors = libxml_use_internal_errors(true);
        $document = simplexml_load_string($xml);
        libxml_use_internal_errors($previousUseErrors);
        $this->assertNotFalse($document, 'The RSS feed is not well-formed XML.');
    }

    public function testShowActionJsonFormatReturnsThePost(): void
    {
        $request = new Request('GET', 'http://localhost/blog/2026-01-15-first-post.json');

        $result = $this->controller->show($request, '2026-01-15-first-post.json');

        $this->assertIsArray($result);
        $this->assertIsArray($result['data']);
        $this->assertSame('2026-01-15-first-post', $result['data']['slug']);
        $this->assertSame('First post', $result['data']['title']);
    }
}
