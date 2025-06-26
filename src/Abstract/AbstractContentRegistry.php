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

use Derafu\Content\Contract\ContentAuthorInterface;
use Derafu\Content\Contract\ContentItemInterface;
use Derafu\Content\Contract\ContentLoaderInterface;
use Derafu\Content\Contract\ContentRegistryInterface;
use Derafu\Content\Contract\ContentTagInterface;
use InvalidArgumentException;

/**
 * Content registry.
 */
abstract class AbstractContentRegistry implements ContentRegistryInterface
{
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
     * @param array $include Glob patterns to include.
     * @param array $exclude Glob patterns to exclude.
     */
    public function __construct(
        protected ContentLoaderInterface $contentLoader,
        protected string $path,
        protected array $include,
        protected array $exclude
    ) {
        $this->path = rtrim($path, '/') . '/';
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $uri): ContentItemInterface
    {
        // The id has implicitly the hierarchy of the content item.
        $uriParts = explode('/', $uri);
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
                throw new InvalidArgumentException(sprintf(
                    'Content item with URI "%s" (slug: %s) is not allowed.',
                    $uri,
                    $slug
                ));
            }

            return $item;
        }

        throw new InvalidArgumentException(sprintf(
            'Content item with URI "%s" (slug: %s) not found.',
            $uri,
            $slug
        ));
    }

    /**
     * {@inheritDoc}
     */
    public function all(): array
    {
        if (!isset($this->items)) {
            $this->items = $this->load();
        }

        return $this->items;
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
        if (isset($filters['limit'])) {
            $page = (int) ($filters['page'] ?? 1);
            $limit = (int) $filters['limit'];
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
     * @return string
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

        // Filter by unlisted.
        if (empty($filters['id']) && empty($filters['uri'])) {
            if ($item->unlisted()) {
                return false;
            }
        }

        // Filter by type.
        if (!empty($filters['type'])) {
            if ($item->type() !== $filters['type']) {
                return false;
            }
        }

        // Filter by category.
        if (!empty($filters['category'])) {
            if ($item->category() !== $filters['category']) {
                return false;
            }
        }

        // Filter by ID.
        if (!empty($filters['id'])) {
            if ($item->id() !== $filters['id']) {
                return false;
            }
        }

        // Filter by URI.
        if (!empty($filters['uri'])) {
            if ($item->uri() !== $filters['uri']) {
                return false;
            }
        }

        // Filter by slug.
        if (!empty($filters['slug'])) {
            if ($item->slug() !== $filters['slug']) {
                return false;
            }
        }

        // Filter by text (title, description or content data).
        if (!empty($filters['search'])) {
            $search = mb_strtolower($filters['search']);
            $text = mb_strtolower($item->title() . ' ' . $item->description() . ' ' . $item->data());
            if (!mb_strpos($text, $search)) {
                return false;
            }
        }

        // Filter by author.
        if (!empty($filters['author'])) {
            if (!isset($item->authors()[$filters['author']])) {
                return false;
            }
        }

        // Filter by tag.
        if (!empty($filters['tag'])) {
            if (!in_array($filters['tag'], array_map(fn (ContentTagInterface $tag) => $tag->slug(), $item->tags()), true)) {
                return false;
            }
        }

        // Filter by date.
        if (!empty($filters['year']) && !empty($filters['month'])) {
            if ($item->date()->format('Y-m') !== sprintf('%04d-%02d', $filters['year'], $filters['month'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Search the index of the content item in the flattened registry.
     *
     * @param string $uri URI of the content item.
     * @param array<string, ContentItemInterface>|null $items Items.
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
