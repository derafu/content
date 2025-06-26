<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Plugin\Blog;

use Derafu\Content\Abstract\AbstractContentRegistry;
use Derafu\Content\Plugin\Blog\Contract\BlogArchiveInterface;
use Derafu\Content\Plugin\Blog\Contract\BlogPostInterface;
use Derafu\Content\Plugin\Blog\Contract\BlogRegistryInterface;

/**
 * Blog registry.
 */
class BlogRegistry extends AbstractContentRegistry implements BlogRegistryInterface
{
    /**
     * All of the archives of items in the registry.
     *
     * @var array<int, BlogArchiveInterface>
     */
    private array $archives;

    /**
     * {@inheritDoc}
     */
    public function get(string $uri): BlogPostInterface
    {
        $post = parent::get($uri);

        assert($post instanceof BlogPostInterface);

        return $post;
    }

    /**
     * {@inheritDoc}
     */
    public function previous(string $uri, array $filters = []): ?BlogPostInterface
    {
        $post = parent::previous($uri, $filters);

        if ($post === null) {
            return null;
        }

        assert($post instanceof BlogPostInterface);

        return $post;
    }

    /**
     * {@inheritDoc}
     */
    public function next(string $uri, array $filters = []): ?BlogPostInterface
    {
        $post = parent::next($uri, $filters);

        if ($post === null) {
            return null;
        }

        assert($post instanceof BlogPostInterface);

        return $post;
    }

    /**
     * {@inheritDoc}
     */
    public function archives(): array
    {
        if (!isset($this->archives)) {
            $this->archives = [];

            $this->walk(function (BlogPostInterface $post) {
                $dateKey = $post->date()->format('Ym');
                if (!isset($this->archives[$dateKey])) {
                    $this->archives[$dateKey] = new BlogArchive($post->date());
                }
                $this->archives[$dateKey]->increment();
            });

            uasort($this->archives, fn ($a, $b) => $b->slug() <=> $a->slug());
        }

        return $this->archives;
    }

    /**
     * {@inheritDoc}
     */
    protected function getContentClass(): string
    {
        return BlogPost::class;
    }
}
