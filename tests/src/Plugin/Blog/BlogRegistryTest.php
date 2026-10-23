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

use DateTime;
use Derafu\Content\ContentAuthor;
use Derafu\Content\ContentBag;
use Derafu\Content\ContentConfig;
use Derafu\Content\ContentContext;
use Derafu\Content\ContentLoader;
use Derafu\Content\ContentSplFileInfo;
use Derafu\Content\ContentTag;
use Derafu\Content\Exception\ContentNotFoundException;
use Derafu\Content\Plugin\Blog\BlogArchive;
use Derafu\Content\Plugin\Blog\BlogPlugin;
use Derafu\Content\Plugin\Blog\BlogPost;
use Derafu\Content\Plugin\Blog\BlogRegistry;
use Derafu\TestsContent\Support\ContentFixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the Blog plugin against real fixture posts on disk, no mocks.
 */
#[CoversClass(BlogPlugin::class)]
#[CoversClass(BlogRegistry::class)]
#[CoversClass(BlogPost::class)]
#[UsesClass(BlogArchive::class)]
#[UsesClass(ContentAuthor::class)]
#[UsesClass(ContentBag::class)]
#[UsesClass(ContentConfig::class)]
#[UsesClass(ContentContext::class)]
#[UsesClass(ContentLoader::class)]
#[UsesClass(ContentSplFileInfo::class)]
#[UsesClass(ContentTag::class)]
#[UsesClass(ContentNotFoundException::class)]
final class BlogRegistryTest extends TestCase
{
    private BlogPlugin $plugin;

    protected function setUp(): void
    {
        $this->plugin = new BlogPlugin(
            ContentFixtures::contentContext(),
            ['path' => 'blog']
        );
        $this->plugin->loadContent(new ContentLoader(ContentFixtures::contentPath()));
    }

    public function testAllFixturePostsAreLoaded(): void
    {
        $slugs = array_keys($this->plugin->registry()->all());

        sort($slugs);
        $this->assertSame(
            ['2026-01-15-first-post', '2026-02-20-second-post', '2099-01-01-future-post'],
            $slugs
        );
    }

    /**
     * "2099-01-01-future-post" has a publish date far in the future
     * (resolved from its filename prefix, same mechanism covered by
     * testDateFallsBackToTheFileNamePrefixWhenNotInFrontmatter below). A
     * future publish date must mean "not published yet": allowed() must
     * be false outside a local environment, the same way a draft is.
     */
    public function testFutureDatedPostIsNotAllowedOutsideALocalEnvironment(): void
    {
        $post = $this->plugin->registry()->all()['2099-01-01-future-post'];

        $this->assertGreaterThan(new DateTime(), $post->date());
        $this->assertFalse($post->draft());
        $this->assertFalse($post->allowed());

        $this->expectException(ContentNotFoundException::class);
        $this->plugin->registry()->get('2099-01-01-future-post');
    }

    /**
     * A future-dated post must not leak into filter()/filterTree() (the
     * blog index, tags, sidebar "recent posts") — the same exclusion
     * already applied to draft/unlisted items.
     */
    public function testFilterExcludesAFutureDatedPost(): void
    {
        $uris = array_map(fn ($post) => $post->uri(), $this->plugin->registry()->filter());

        $this->assertNotContains('2099-01-01-future-post', $uris);
    }

    public function testDateFallsBackToTheFileNamePrefixWhenNotInFrontmatter(): void
    {
        $post = $this->plugin->registry()->get('2026-01-15-first-post');

        $this->assertSame('2026-01-15', $post->date()->format('Y-m-d'));
    }

    public function testExplicitFrontmatterDateOverridesTheFileNamePrefix(): void
    {
        $post = $this->plugin->registry()->get('2026-02-20-second-post');

        // Frontmatter says 2026-02-25; the filename prefix says 2026-02-20.
        $this->assertSame('2026-02-25', $post->date()->format('Y-m-d'));
    }

    public function testHideTableOfContentsDefaultsToTrueForBlogButIsOverridable(): void
    {
        $withoutOverride = $this->plugin->registry()->get('2026-01-15-first-post');
        $withOverride = $this->plugin->registry()->get('2026-02-20-second-post');

        $this->assertTrue($withoutOverride->hide_table_of_contents());
        $this->assertFalse($withOverride->hide_table_of_contents());
    }

    public function testAuthorStringFrontmatterBecomesARealContentAuthor(): void
    {
        $post = $this->plugin->registry()->get('2026-01-15-first-post');
        $authors = $post->authors();

        $this->assertCount(1, $authors);
        $author = array_values($authors)[0];
        $this->assertInstanceOf(ContentAuthor::class, $author);
        $this->assertSame('Ana Tester', $author->name());
    }

    public function testPreviousAndNextFollowSidebarPositionOrdering(): void
    {
        $registry = $this->plugin->registry();

        // Sorted by date descending by default sidebar_position: the newer
        // post (2026-02-25) comes before the older one (2026-01-15).
        $newer = $registry->get('2026-02-20-second-post');
        $older = $registry->get('2026-01-15-first-post');

        $this->assertSame(
            $older->uri(),
            $registry->next($newer->uri())?->uri()
        );
    }

    public function testUnknownUriThrowsContentNotFoundException(): void
    {
        $this->expectException(ContentNotFoundException::class);

        $this->plugin->registry()->get('does-not-exist');
    }

    public function testTheArchivesAreTheMonthsOfThePublishedPostsFromTheNewestToTheOldest(): void
    {
        $archives = array_values($this->plugin->registry()->archives());

        $this->assertSame(['202602', '202601'], array_map(fn ($archive) => $archive->id(), $archives));
        $this->assertSame([1, 1], array_map(fn ($archive) => $archive->count(), $archives));
    }

    public function testThePostBeforeAndAfterAreTheNeighboursInTheList(): void
    {
        $registry = $this->plugin->registry();
        $uris = array_map(fn ($post) => $post->uri(), $registry->flatten(['limit' => 100]));
        $second = $uris[1];

        $this->assertSame($uris[0], $registry->previous($second)?->uri());
        $this->assertSame($uris[2] ?? null, $registry->next($second)?->uri());
        $this->assertNull($registry->previous($uris[0]));
        $this->assertNull($registry->previous('does-not-exist'));
        $this->assertNull($registry->next('does-not-exist'));
    }

    public function testAPostIsOfTheCategoryPost(): void
    {
        $this->assertSame('post', $this->plugin->registry()->get('2026-01-15-first-post')->category());
    }
}
