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

use Derafu\Content\ContentAuthor;
use Derafu\Content\ContentBag;
use Derafu\Content\ContentConfig;
use Derafu\Content\ContentContext;
use Derafu\Content\ContentLoader;
use Derafu\Content\ContentSplFileInfo;
use Derafu\Content\ContentTag;
use Derafu\Content\Exception\ContentNotFoundException;
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

    public function testBothFixturePostsAreLoaded(): void
    {
        $this->assertCount(2, $this->plugin->registry()->all());
    }

    public function testDateFallsBackToTheFileNamePrefixWhenNotInFrontmatter(): void
    {
        $post = $this->plugin->registry()->get('2026-01-15-primer-post');

        $this->assertSame('2026-01-15', $post->date()->format('Y-m-d'));
    }

    public function testExplicitFrontmatterDateOverridesTheFileNamePrefix(): void
    {
        $post = $this->plugin->registry()->get('2026-02-20-segundo-post');

        // Frontmatter says 2026-02-25; the filename prefix says 2026-02-20.
        $this->assertSame('2026-02-25', $post->date()->format('Y-m-d'));
    }

    public function testHideTableOfContentsDefaultsToTrueForBlogButIsOverridable(): void
    {
        $withoutOverride = $this->plugin->registry()->get('2026-01-15-primer-post');
        $withOverride = $this->plugin->registry()->get('2026-02-20-segundo-post');

        $this->assertTrue($withoutOverride->hide_table_of_contents());
        $this->assertFalse($withOverride->hide_table_of_contents());
    }

    public function testAuthorStringFrontmatterBecomesARealContentAuthor(): void
    {
        $post = $this->plugin->registry()->get('2026-01-15-primer-post');
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
        $newer = $registry->get('2026-02-20-segundo-post');
        $older = $registry->get('2026-01-15-primer-post');

        $this->assertSame(
            $older->uri(),
            $registry->next($newer->uri())?->uri()
        );
    }

    public function testUnknownUriThrowsContentNotFoundException(): void
    {
        $this->expectException(ContentNotFoundException::class);

        $this->plugin->registry()->get('no-existe');
    }
}
