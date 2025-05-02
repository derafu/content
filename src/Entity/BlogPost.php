<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Entity;

use Derafu\Content\Contract\BlogPostInterface;
use Derafu\Content\Contract\ContentAuthorInterface;
use Derafu\Content\Entity\ContentAuthor;
use Derafu\Content\Entity\ContentFile;

/**
 * Class that represents a blog post.
 */
final class BlogPost extends ContentFile implements BlogPostInterface
{
    /**
     * Title of the blog post.
     *
     * @var string
     */
    private string $title;

    /**
     * Image of the blog post.
     *
     * @var string|false
     */
    private string|false $image;

    /**
     * Author of the blog post.
     *
     * @var ContentAuthor|false
     */
    private ContentAuthor|false $author;

    /**
     * Time to read the blog post.
     *
     * @var int
     */
    private int $time;

    /**
     * Links of the blog post.
     *
     * @var array
     */
    private array $links;

    /**
     * {@inheritDoc}
     */
    public function title(): string
    {
        if (!isset($this->title)) {
            $this->title = $this->metadata('title') ?? $this->name();
        }

        return $this->title;
    }

    /**
     * {@inheritDoc}
     */
    public function image(): ?string
    {
        if (!isset($this->image)) {
            $this->image = $this->metadata()['image'] ?? false;
        }

        return $this->image ?: null;
    }

    /**
     * {@inheritDoc}
     */
    public function author(): ?ContentAuthorInterface
    {
        if (!isset($this->author)) {
            $author = $this->metadata()['author'] ?? null;

            if (empty($author)) {
                $this->author = false;
            } elseif (is_string($author)) {
                $this->author = new ContentAuthor($author);
            } else {
                $name = $author['name'] ?? $author[0];
                $slug = $author['slug'] ?? $author[1] ?? null;
                $this->author = new ContentAuthor($name, $slug);
            }
        }

        return $this->author ?: null;
    }

    /**
     * {@inheritDoc}
     */
    public function time(): int
    {
        if (!isset($this->time)) {
            $content = $this->content();
            $wordCount = str_word_count(strip_tags($content));
            $readingSpeed = 200;
            $this->time = (int) max(1, ceil($wordCount / $readingSpeed));
        }

        return $this->time;
    }

    /**
     * {@inheritDoc}
     */
    public function links(): array
    {
        if (!isset($this->links)) {
            $this->links = [
                'self' => ['href' => '/blog/' . $this->slug()],
                'collection' => ['href' => '/blog'],
            ];
        }

        return $this->links;
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title(),
            'author' => $this->author(),
            'image' => $this->image(),
            'time' => $this->time(),
            '_links' => $this->links(),
        ];
    }
}
