<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Service;

use Derafu\Content\Contract\ContentItemInterface;
use Derafu\Content\Contract\ContentLoaderInterface;
use Derafu\Content\Contract\ContentMonthInterface;
use Derafu\Content\Contract\ContentRegistryInterface;
use Derafu\Content\Contract\ContentTagInterface;
use Derafu\Content\Entity\ContentItem;
use Derafu\Content\ValueObject\ContentMonth;
use InvalidArgumentException;

/**
 * Content registry.
 */
class ContentRegistry implements ContentRegistryInterface
{
    /**
     * Content items of the registry.
     *
     * @var array<string, ContentItemInterface>
     */
    private array $items;

    /**
     * All of the tags of items in the registry.
     *
     * @var array<string, ContentTagInterface>
     */
    private array $tags;

    /**
     * All of the months of items in the registry.
     *
     * @var array<int, ContentMonthInterface>
     */
    private array $months;

    /**
     * Constructor.
     *
     * @param string $path Path to root directory of the content items.
     * @param array $extensions File extensions to include.
     * @param ContentLoaderInterface|null $loader Content loader.
     */
    public function __construct(
        protected string $path = '',
        protected array $extensions = ['md'],
        protected ?ContentLoaderInterface $loader = null
    ) {
        $this->path = rtrim($path, '/') . '/';
        $this->loader = $loader ?? new ContentLoader();
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $uri): ContentItemInterface
    {
        $uriParts = explode('/', $uri);
        $n_uriParts = count($uriParts);

        $items = $this->all();

        for ($i = 0; $i < $n_uriParts; $i++) {
            $slug = $uriParts[$i];
            if (isset($items[$slug])) {
                $item = $items[$slug];
                $items = $item->children();
                if (!empty($items) && $i < $n_uriParts - 1) {
                    continue;
                }

                return $item;
            }
        }

        throw new InvalidArgumentException(sprintf(
            'Content item %s not found.',
            $slug
        ));
    }

    /**
     * {@inheritDoc}
     */
    public function all(): array
    {
        if (!isset($this->items)) {
            $this->items = $this->loader->load(
                $this->path,
                $this->extensions,
                $this->getContentClass()
            );
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
     * {@inheritDoc}
     */
    public function months(): array
    {
        if (!isset($this->months)) {
            $this->months = [];

            $this->walk(function (ContentItemInterface $item) {
                $dateKey = $item->published()->format('Ym');
                if (!isset($this->months[$dateKey])) {
                    $this->months[$dateKey] = new ContentMonth($item->published());
                }
                $this->months[$dateKey]->increment();
            });

            uasort($this->months, fn ($a, $b) => $b->slug() <=> $a->slug());
        }

        return $this->months;
    }

    /**
     * Get the content class.
     *
     * @return string
     */
    protected function getContentClass(): string
    {
        return ContentItem::class;
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

        // Filter by URI.
        if (!empty($filters['uri'])) {
            if ($item->uri() !== $filters['uri']) {
                return false;
            }
        }

        // Filter by text (title, summary and content data).
        if (!empty($filters['search'])) {
            $search = mb_strtolower($filters['search']);
            $text = mb_strtolower($item->title() . ' ' . $item->summary() . ' ' . $item->data());
            if (!mb_strpos($text, $search)) {
                return false;
            }
        }

        // Filter by author.
        if (!empty($filters['author'])) {
            if ($item->author()?->slug() !== $filters['author']) {
                return false;
            }
        }

        // Filter by tag.
        if (!empty($filters['tag'])) {
            if (!in_array($filters['tag'], array_map(fn ($tag) => $tag->slug(), $item->tags()), true)) {
                return false;
            }
        }

        // Filter by date.
        if (!empty($filters['year']) && !empty($filters['month'])) {
            if ($item->published()->format('Y-m') !== sprintf('%04d-%02d', $filters['year'], $filters['month'])) {
                return false;
            }
        }

        return true;
    }
}
