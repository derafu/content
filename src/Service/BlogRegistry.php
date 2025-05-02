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

use Derafu\Content\Contract\BlogPostInterface;
use Derafu\Content\Contract\BlogRegistryInterface;
use Derafu\Content\Contract\ContentTagInterface;
use Derafu\Content\Entity\BlogPost;
use DirectoryIterator;
use RuntimeException;

/**
 * Blog registry.
 */
final class BlogRegistry implements BlogRegistryInterface
{
    /**
     * Array of blog posts.
     *
     * @var array<BlogPostInterface>
     */
    private array $posts;

    /**
     * Array of blog tags.
     *
     * @var array<string, ContentTagInterface>
     */
    private array $tags;

    /**
     * Constructor.
     *
     * @param string|null $path Path to the blog posts directory.
     */
    public function __construct(private ?string $path = null)
    {
        $this->path = rtrim(
            $path ?? __DIR__ . '/../../resources/content/blog/',
            '/'
        ) . '/';
    }

    /**
     * {@inheritDoc}
     */
    public function getPosts(array $filters = []): array
    {
        if (!isset($this->posts)) {
            $this->posts = [];
            if (is_dir($this->path)) {
                foreach (new DirectoryIterator($this->path) as $file) {
                    if ($file->isFile() && $file->getExtension() === 'md') {
                        $post = new BlogPost($file->getPathname());
                        $this->posts[] = $post;
                    }
                }
                usort(
                    $this->posts,
                    fn ($a, $b) => $b->published()->getTimestamp() <=> $a->published()->getTimestamp()
                );
            }
        }

        if (empty($filters)) {
            return $this->posts;
        }

        return $this->filterPosts($this->posts, $filters);
    }

    /**
     * {@inheritDoc}
     */
    public function getPost(string $slug): BlogPostInterface
    {
        $path = $this->path . $slug . '.md';

        if (!file_exists($path)) {
            throw new RuntimeException(
                sprintf('Post "%s" not found.', $slug),
                404
            );
        }

        return new BlogPost($path);
    }

    /**
     * {@inheritDoc}
     */
    public function getTags(array $filters = []): array
    {
        if (!isset($this->tags)) {
            $this->tags = [];
            $posts = $this->getPosts($filters);
            foreach ($posts as $post) {
                foreach ($post->tags() as $tag) {
                    if (!isset($this->tags[$tag->name()])) {
                        $this->tags[$tag->name()] = $tag;
                    }
                    $this->tags[$tag->name()]->increment();
                }
            }
            usort(
                $this->tags,
                fn ($a, $b) => $a->name() <=> $b->name()
            );
        }

        return $this->tags;
    }

    /**
     * Filter posts.
     *
     * @param array<BlogPostInterface> $posts Posts.
     * @param array<string, mixed> $filters Filters.
     * @return array<BlogPostInterface>
     */
    private function filterPosts(array $posts, array $filters): array
    {
        // Filter posts.
        $posts = array_filter($this->posts, function ($post) use ($filters) {
            // Filter by text (title, description and content).
            if (!empty($filters['search'])) {
                $search = mb_strtolower($filters['search']);
                $text = mb_strtolower($post->title() . ' ' . $post->summary() . ' ' . $post->content());
                if (!mb_strpos($text, $search)) {
                    return false;
                }
            }

            // Filter by author.
            if (!empty($filters['author'])) {
                if ($post->author()?->slug() !== $filters['author']) {
                    return false;
                }
            }

            // Filter by tag.
            if (!empty($filters['tag'])) {
                if (!in_array($filters['tag'], array_map(fn ($tag) => $tag->slug(), $post->tags()), true)) {
                    return false;
                }
            }

            // Filter by date.
            if (!empty($filters['year']) && !empty($filters['month'])) {
                if ($post->published()->format('Y-m') !== sprintf('%04d-%02d', $filters['year'], $filters['month'])) {
                    return false;
                }
            }

            return true;
        });

        // Limit posts.
        if (isset($filters['limit'])) {
            $page = $filters['page'] ?? 1;
            $limit = $filters['limit'];
            $offset = ($page - 1) * $limit;

            return array_slice($posts, $offset, $limit);
        }

        // Return all posts.
        return $posts;
    }
}
