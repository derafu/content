<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent\Abstract;

use Derafu\Content\Abstract\AbstractContentItem;
use Derafu\Content\Abstract\AbstractContentPlugin;
use Derafu\Content\Abstract\AbstractContentRegistry;
use Derafu\Content\Abstract\AbstractPlugin;
use Derafu\Content\ContentAuthor;
use Derafu\Content\ContentBag;
use Derafu\Content\ContentConfig;
use Derafu\Content\ContentContext;
use Derafu\Content\ContentFilters;
use Derafu\Content\ContentLoader;
use Derafu\Content\ContentSplFileInfo;
use Derafu\Content\ContentTag;
use Derafu\Content\Contract\ContentItemInterface;
use Derafu\Content\Exception\ContentNotFoundException;
use Derafu\Content\Plugin\Blog\BlogPlugin;
use Derafu\Content\Plugin\Blog\BlogPost;
use Derafu\Content\Plugin\Blog\BlogRegistry;
use Derafu\Content\Plugin\Blog\Contract\BlogRegistryInterface;
use Derafu\Content\Plugin\Docs\Contract\DocsRegistryInterface;
use Derafu\Content\Plugin\Docs\DocsDoc;
use Derafu\Content\Plugin\Docs\DocsPlugin;
use Derafu\Content\Plugin\Docs\DocsRegistry;
use Derafu\TestsContent\Support\ContentFixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * What a registry answers from its items: the neighbours of one, the tags and
 * the authors of all of them, and the filters that narrow the list.
 */
#[CoversClass(AbstractContentRegistry::class)]
#[UsesClass(AbstractContentItem::class)]
#[UsesClass(AbstractContentPlugin::class)]
#[UsesClass(AbstractPlugin::class)]
#[UsesClass(BlogPlugin::class)]
#[UsesClass(BlogPost::class)]
#[UsesClass(BlogRegistry::class)]
#[UsesClass(ContentAuthor::class)]
#[UsesClass(ContentBag::class)]
#[UsesClass(ContentConfig::class)]
#[UsesClass(ContentContext::class)]
#[UsesClass(ContentFilters::class)]
#[UsesClass(ContentLoader::class)]
#[UsesClass(ContentNotFoundException::class)]
#[UsesClass(ContentSplFileInfo::class)]
#[UsesClass(ContentTag::class)]
#[UsesClass(DocsDoc::class)]
#[UsesClass(DocsPlugin::class)]
#[UsesClass(DocsRegistry::class)]
final class AbstractContentRegistryNavigationTest extends TestCase
{
    private function docs(): DocsRegistryInterface
    {
        $plugin = new DocsPlugin(ContentFixtures::contentContext(), ['path' => 'docs']);
        $plugin->loadContent(ContentFixtures::contentLoader());

        return $plugin->registry();
    }

    private function blog(): BlogRegistryInterface
    {
        $plugin = new BlogPlugin(ContentFixtures::contentContext(), ['path' => 'blog']);
        $plugin->loadContent(ContentFixtures::contentLoader());

        return $plugin->registry();
    }

    /**
     * @param array<int|string, ContentItemInterface> $items
     * @return list<string>
     */
    private function uris(array $items): array
    {
        return array_values(array_map(fn ($item) => $item->uri(), $items));
    }

    public function testThePreviousAndTheNextAreTheNeighboursInTheFlatList(): void
    {
        $registry = $this->docs();
        $uris = $this->uris($registry->flatten());
        $this->assertGreaterThan(2, count($uris));

        $this->assertNull($registry->previous($uris[0]));
        $this->assertSame($uris[0], $registry->previous($uris[1])?->uri());
        $this->assertSame($uris[2], $registry->next($uris[1])?->uri());
        $this->assertNull($registry->next($uris[count($uris) - 1]));
    }

    public function testAnItemThatIsNotThereHasNoNeighbours(): void
    {
        $registry = $this->docs();

        $this->assertNull($registry->previous('does-not-exist'));
        $this->assertNull($registry->next('does-not-exist'));
    }

    public function testTheFiltersOfTheNeighboursNarrowTheList(): void
    {
        $registry = $this->docs();
        $tagged = $this->uris($registry->flatten(['tag' => 'api']));

        $this->assertNotContains('guide', $tagged);
        $this->assertNull($registry->previous($tagged[0], ['tag' => 'api']));
    }

    public function testTheTagsAreTheOnesOfAllTheItemsSortedByName(): void
    {
        $tags = $this->blog()->tags();

        $names = array_map(fn ($tag) => $tag->name(), array_values($tags));
        $sorted = $names;
        sort($sorted);

        $this->assertSame($sorted, $names);
        $this->assertArrayHasKey('announcements', $tags);
        $this->assertArrayHasKey('demo', $tags);
    }

    public function testTheTagsAndTheAuthorsDoNotCountWhatIsNotPublished(): void
    {
        $registry = $this->blog();

        // The future post has the tag "demo" too: only the two published
        // posts count.
        $this->assertSame(2, $registry->tags()['demo']->count());
        $this->assertSame(1, $registry->tags()['announcements']->count());
    }

    public function testTheAuthorsAreTheOnesOfAllTheItems(): void
    {
        $registry = $this->blog();
        $authors = $registry->authors();

        $this->assertArrayHasKey('ana-tester', $authors);
        $this->assertSame('Ana Tester', $authors['ana-tester']->name());
        $this->assertSame($authors, $registry->authors());
    }

    public function testItemsCanBeFilteredByEachOfTheirData(): void
    {
        $registry = $this->blog();

        $this->assertSame(['2026-01-15-first-post'], $this->uris($registry->filter(['slug' => '2026-01-15-first-post'])));
        $this->assertSame(['2026-01-15-first-post'], $this->uris($registry->filter(['uri' => '2026-01-15-first-post'])));
        $this->assertSame(['2026-01-15-first-post'], $this->uris($registry->filter(['search' => 'FIRST POST'])));
        $this->assertSame(['2026-01-15-first-post'], $this->uris($registry->filter(['author' => 'ana-tester'])));
        $this->assertSame([], $registry->filter(['author' => 'nobody']));
        $this->assertSame(['2026-01-15-first-post'], $this->uris($registry->filter(['tag' => 'announcements'])));
        $this->assertSame(['2026-01-15-first-post'], $this->uris($registry->filter(['year' => 2026, 'month' => 1])));
        $this->assertSame([], $registry->filter(['type' => 'docs']));
        $this->assertSame([], $registry->filter(['category' => 'page']));
        $this->assertSame([], $registry->filter(['search' => 'text that no post has']));
    }

    public function testTheTypeAndTheCategoryAreOfTheItems(): void
    {
        $registry = $this->blog();

        $this->assertCount(2, $registry->filter(['type' => 'blog', 'category' => 'post']));
    }

    public function testAnItemCanBeFoundByItsId(): void
    {
        $registry = $this->blog();
        $post = $registry->get('2026-01-15-first-post');

        $this->assertSame([$post], $registry->filter(['id' => $post->id()]));
    }

    public function testIndexableAndSearchableNarrowTheListToWhatTheyAre(): void
    {
        $registry = $this->docs();

        $indexable = $registry->filter(['indexable' => true]);
        $notIndexable = $registry->filter(['indexable' => false]);
        $notSearchable = $registry->filter(['searchable' => false]);

        foreach ($indexable as $item) {
            $this->assertTrue($item->indexable());
        }
        foreach ($notIndexable as $item) {
            $this->assertFalse($item->indexable());
        }
        foreach ($notSearchable as $item) {
            $this->assertFalse($item->searchable());
        }
    }

    public function testTheTreeIsFilteredOnlyAtTheTopLevel(): void
    {
        $tree = $this->docs()->filterTree();

        $this->assertNotEmpty($tree);
        $this->assertArrayHasKey('guide', $tree);
    }
}
