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
use Derafu\Content\Entity\BlogPost;

/**
 * Blog registry.
 */
class BlogRegistry extends ContentRegistry implements BlogRegistryInterface
{
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
    protected function getContentClass(): string
    {
        return BlogPost::class;
    }
}
