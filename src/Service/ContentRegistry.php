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
use Derafu\Content\Contract\ContentMonthInterface;
use Derafu\Content\Contract\ContentRegistryInterface;
use Derafu\Content\Contract\ContentTagInterface;
use Derafu\Content\Entity\ContentItem;
use Derafu\Content\Entity\ContentMonth;
use DirectoryIterator;
use InvalidArgumentException;

/**
 * Content registry.
 */
class ContentRegistry implements ContentRegistryInterface
{
    /**
     * Valid extensions of the content items.
     *
     * @var array<string>
     */
    protected const EXTENSIONS = ['md'];

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
     * @param string|null $path Path to root directory of the content items.
     */
    public function __construct(protected ?string $path = null)
    {
        $this->path = rtrim($path ?? '', '/') . '/';
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
    public function filter(array $filters = []): array
    {
        $items = $this->all();

        if (empty($filters)) {
            return $items;
        }

        return $this->applyFilters($items, $filters);
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $slug): ContentItemInterface
    {
        foreach (self::EXTENSIONS as $extension) {
            $path = $this->path . $slug . '.' . $extension;
            if (file_exists($path)) {
                return $this->createFromPath($path);
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
    public function tags(): array
    {
        if (!isset($this->tags)) {
            $this->tags = [];
            foreach ($this->all() as $item) {
                foreach ($item->tags() as $tag) {
                    if (!isset($this->tags[$tag->slug()])) {
                        $this->tags[$tag->slug()] = $tag;
                    }
                    $this->tags[$tag->slug()]->increment();
                }
            }
            uasort(
                $this->tags,
                fn ($a, $b) => $a->name() <=> $b->name()
            );
        }

        return $this->tags;
    }

    /**
     * Load all content items from filesystem.
     *
     * @return array<string, ContentItemInterface>
     */
    private function load(): array
    {
        $items = [];

        if (!is_dir($this->path)) {
            return $items;
        }

        foreach (new DirectoryIterator($this->path) as $file) {
            if ($file->isFile() && in_array($file->getExtension(), self::EXTENSIONS)) {
                $item = $this->createFromPath($file->getPathname());
                $items[$item->slug()] = $item;
            }
        }

        uasort(
            $items,
            fn ($a, $b) => $b->published()->getTimestamp() <=> $a->published()->getTimestamp()
        );

        return $items;
    }

    /**
     * Create content object from file path.
     *
     * @param string $path
     * @return ContentItemInterface
     */
    protected function createFromPath(string $path): ContentItemInterface
    {
        return new ContentItem($path);
    }

    /**
     * Filter items by provided filters.
     *
     * @param array<ContentItemInterface> $items
     * @param array<string, mixed> $filters
     * @return array<ContentItemInterface>
     */
    private function applyFilters(array $items, array $filters): array
    {
        // Filter items.
        $items = array_filter($items, function ($item) use ($filters) {
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
        });

        // Limit items.
        if (isset($filters['limit'])) {
            $page = $filters['page'] ?? 1;
            $limit = $filters['limit'];
            $offset = ($page - 1) * $limit;

            return array_slice($items, $offset, $limit);
        }

        // Return all items.
        return $items;
    }

    /**
     * Get all months.
     *
     * @return array<int, ContentMonthInterface>
     */
    public function months(): array
    {
        if (!isset($this->months)) {
            $this->months = [];

            foreach ($this->all() as $item) {
                $date = $item->published()->format('Ym');
                if (!isset($this->months[$date])) {
                    $this->months[$date] = new ContentMonth($item->published());
                }
                $this->months[$date]->increment();
            }

            uasort(
                $this->months,
                fn ($a, $b) => $a->name() <=> $b->name()
            );
        }

        return $this->months;
    }
}
