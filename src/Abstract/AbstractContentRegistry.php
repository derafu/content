<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Abstract;

use Derafu\Array\ArrayReader;
use Derafu\Content\Contract\ContentAuthorInterface;
use Derafu\Content\Contract\ContentItemInterface;
use Derafu\Content\Contract\ContentLoaderInterface;
use Derafu\Content\Contract\ContentRegistryInterface;
use Derafu\Content\Contract\ContentTagInterface;
use Derafu\Content\Exception\ContentNotFoundException;
use Derafu\Translation\Exception\Core\TranslatableRuntimeException as RuntimeException;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Content registry.
 */
abstract class AbstractContentRegistry implements ContentRegistryInterface
{
    /**
     * TTL, in seconds, of the cached item tree of this registry.
     *
     * A short TTL is enough to remove the "rescan and reparse everything on
     * every single request" cost while keeping content changes visible
     * within a bounded, small delay, with no manual cache invalidation.
     *
     * @var int
     */
    private const CACHE_TTL = 60;

    /**
     * Content items of the registry.
     *
     * @var array<string, ContentItemInterface>
     */
    private array $items;

    /**
     * All of the authors of items in the registry.
     *
     * @var array<string, ContentAuthorInterface>
     */
    private array $authors;

    /**
     * All of the tags of items in the registry.
     *
     * @var array<string, ContentTagInterface>
     */
    private array $tags;

    /**
     * Constructor.
     *
     * @param string $path Path to root directory of the content items.
     * @param array<string> $include Glob patterns to include.
     * @param array<string> $exclude Glob patterns to exclude.
     */
    public function __construct(
        protected ContentLoaderInterface $contentLoader,
        protected string $path,
        protected array $include,
        protected array $exclude,
        private ?CacheItemPoolInterface $cache = null
    ) {
        $this->path = rtrim($path, '/') . '/';
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $uri): ContentItemInterface
    {
        // Remove file extension if present (handles any extension at the end)
        $uriWithoutExtension = preg_replace('/\.[^\/.]+$/', '', $uri);
        if ($uriWithoutExtension === null) {
            throw new RuntimeException([
                'The content "{content}" could not be processed: {error}',
                'content' => $uri,
                'error' => preg_last_error_msg(),
            ]);
        }

        // The id has implicitly the hierarchy of the content item.
        $uriParts = explode('/', $uriWithoutExtension);

        $n_uriParts = count($uriParts);

        $items = $this->all();

        for ($i = 0; $i < $n_uriParts; $i++) {
            $slug = $uriParts[$i];
            if (!isset($items[$slug])) {
                break;
            }

            $item = $items[$slug];
            $items = $item->children();
            if (!empty($items) && $i < $n_uriParts - 1) {
                continue;
            }

            if (!$item->allowed()) {
                throw new ContentNotFoundException([
                    'Content item with URI "{uri}" (slug: {slug}) is not allowed.',
                    'uri' => $uri,
                    'slug' => $slug,
                ]);
            }

            return $item;
        }

        if (empty($slug)) {
            throw new ContentNotFoundException([
                'Content item with URI "{uri}" not found.',
                'uri' => $uri,
            ]);
        }

        throw new ContentNotFoundException([
            'Content item with URI "{uri}" (slug: {slug}) not found.',
            'uri' => $uri,
            'slug' => $slug,
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function all(): array
    {
        if (!isset($this->items)) {
            if ($this->cache === null) {
                $this->items = $this->load();
            } else {
                $item = $this->cache->getItem($this->cacheKey());

                $cached = $item->isHit() ? $item->get() : null;

                // A cache entry that is not a tree of items (another version
                // of the package wrote it, or something else did) is as if it
                // was not there.
                if (is_array($cached) && array_all($cached, fn ($value) => $value instanceof ContentItemInterface)) {
                    /** @var array<string, ContentItemInterface> $cached */
                    $this->items = $cached;
                } else {
                    $this->items = $this->load();
                    $this->cache->save(
                        $item->set($this->items)->expiresAfter(self::CACHE_TTL)
                    );
                }
            }
        }

        return $this->items;
    }

    /**
     * Build the cache key that identifies this registry's item tree.
     *
     * Depends only on what determines which files get loaded (path,
     * include and exclude patterns), so two registries pointed at the same
     * content share a cache entry regardless of instantiation order.
     *
     * @return string
     */
    private function cacheKey(): string
    {
        return 'derafu_content_registry_' . md5(serialize([
            $this->path,
            $this->include,
            $this->exclude,
        ]));
    }

    /**
     * {@inheritDoc}
     */
    public function walk(
        callable $callback,
        ?ContentItemInterface $item = null
    ): void {
        $items = $item ? [$item] : $this->all();

        foreach ($items as $current) {
            $callback($current);
            foreach ($current->children() as $child) {
                $this->walk($callback, $child);
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function filter(array $filters = []): array
    {
        // Walk through the items and check if they match the filters.
        $matched = [];
        $this->walk(function (ContentItemInterface $item) use ($filters, &$matched) {
            if ($this->matches($item, $filters)) {
                $matched[] = $item;
            }
        });

        // Limit items.
        $reader = ArrayReader::fromArray($filters, 'filters');
        $limit = $reader->getInt('limit');
        if ($limit !== null) {
            $page = $reader->getInt('page', 1);
            $offset = ($page - 1) * $limit;

            return array_slice($matched, $offset, $limit);
        }

        // Return the matched items.
        return $matched;
    }

    /**
     * {@inheritDoc}
     */
    public function flatten(array $filters = []): array
    {
        return $this->filter($filters);
    }

    /**
     * {@inheritDoc}
     */
    public function filterTree(array $filters = []): array
    {
        return array_filter(
            $this->all(),
            fn (ContentItemInterface $item): bool => $this->matches($item, $filters)
        );
    }

    /**
     * {@inheritDoc}
     */
    public function previous(string $uri, array $filters = []): ?ContentItemInterface
    {
        $items = $this->flatten($filters);
        $index = $this->search($uri, $items);
        if ($index === null) {
            return null;
        }

        if (array_key_exists($index - 1, $items)) {
            return $items[$index - 1];
        }

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function next(string $uri, array $filters = []): ?ContentItemInterface
    {
        $items = $this->flatten($filters);
        $index = $this->search($uri, $items);
        if ($index === null) {
            return null;
        }

        if (array_key_exists($index + 1, $items)) {
            return $items[$index + 1];
        }

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function authors(): array
    {
        if (!isset($this->authors)) {
            $this->authors = [];

            $this->walk(function (ContentItemInterface $item) {
                if (!$item->allowed() || $item->unlisted()) {
                    return;
                }

                foreach ($item->authors() as $author) {
                    $slug = $author->slug();
                    if (!isset($this->authors[$slug])) {
                        $this->authors[$slug] = $author;
                    }
                }
            });

            uasort($this->authors, fn ($a, $b) => $a->name() <=> $b->name());
        }

        return $this->authors;
    }

    /**
     * {@inheritDoc}
     */
    public function tags(): array
    {
        if (!isset($this->tags)) {
            $this->tags = [];

            $this->walk(function (ContentItemInterface $item) {
                if (!$item->allowed() || $item->unlisted()) {
                    return;
                }

                foreach ($item->tags() as $tag) {
                    $slug = $tag->slug();
                    if (!isset($this->tags[$slug])) {
                        $this->tags[$slug] = $tag;
                    }
                    $this->tags[$slug]->increment();
                }
            });

            uasort($this->tags, fn ($a, $b) => $a->name() <=> $b->name());
        }

        return $this->tags;
    }

    /**
     * Get the content class.
     *
     * @return class-string<ContentItemInterface>
     */
    abstract protected function getContentClass(): string;

    /**
     * Load the content items.
     *
     * @return array<string, ContentItemInterface>
     */
    protected function load(): array
    {
        $hierarchy = $this->contentLoader->scan(
            $this->path,
            $this->include,
            $this->exclude
        );

        return $this->contentLoader->load($this->getContentClass(), $hierarchy);
    }

    /**
     * Check if the item matches the provided filters.
     *
     * @param ContentItemInterface $item Item.
     * @param array<string, mixed> $filters Filters.
     * @return bool
     */
    protected function matches(ContentItemInterface $item, array $filters): bool
    {
        // Check if the item is allowed.
        if (!$item->allowed()) {
            return false;
        }

        $filters = ArrayReader::fromArray($filters, 'filters');
        $id = $filters->getString('id');
        $uri = $filters->getString('uri');

        // Filter by unlisted.
        if (!$id && !$uri) {
            if ($item->unlisted()) {
                return false;
            }
        }

        // Filter by type.
        if (($type = $filters->getString('type')) && $item->type() !== $type) {
            return false;
        }

        // Filter by category.
        if (($category = $filters->getString('category')) && $item->category() !== $category) {
            return false;
        }

        // Filter by ID.
        if ($id && $item->id() !== $id) {
            return false;
        }

        // Filter by URI.
        if ($uri && $item->uri() !== $uri) {
            return false;
        }

        // Filter by slug.
        if (($slug = $filters->getString('slug')) && $item->slug() !== $slug) {
            return false;
        }

        // Filter by text (title, description or content data).
        if ($search = $filters->getString('search')) {
            $search = mb_strtolower($search);
            $text = mb_strtolower($item->title() . ' ' . $item->description() . ' ' . $item->data());
            if (mb_strpos($text, $search) === false) {
                return false;
            }
        }

        // Filter by author.
        if ($author = $filters->getString('author')) {
            $slugs = array_map(fn (ContentAuthorInterface $a) => $a->slug(), $item->authors());
            if (!in_array($author, $slugs, true)) {
                return false;
            }
        }

        // Filter by tag.
        if ($tag = $filters->getString('tag')) {
            if (!in_array($tag, array_map(fn (ContentTagInterface $tag) => $tag->slug(), $item->tags()), true)) {
                return false;
            }
        }

        // Filter by date.
        $year = $filters->getInt('year');
        $month = $filters->getInt('month');
        if ($year && $month) {
            if ($item->date()->format('Y-m') !== sprintf('%04d-%02d', $year, $month)) {
                return false;
            }
        }

        // Filter by indexable.
        $indexable = $filters->getBool('indexable');
        if ($indexable !== null && $item->indexable() !== $indexable) {
            return false;
        }

        // Filter by searchable.
        $searchable = $filters->getBool('searchable');
        if ($searchable !== null && $item->searchable() !== $searchable) {
            return false;
        }

        return true;
    }

    /**
     * Search the index of the content item in the flattened registry.
     *
     * @param string $uri URI of the content item.
     * @param array<int|string, ContentItemInterface>|null $items Items.
     * @return int|null
     */
    protected function search(string $uri, ?array $items = null): ?int
    {
        $items = $items ?? $this->flatten();

        $filtered = array_filter($items, fn ($item) => $item->uri() === $uri);
        if (empty($filtered)) {
            return null;
        }

        return (int) array_key_first($filtered);
    }
}
