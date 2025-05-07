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
    public function get(string $slug): BlogPostInterface
    {
        $post = parent::get($slug);

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
