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

/**
 * Class that represents a blog post.
 */
class BlogPost extends ContentItem implements BlogPostInterface
{
    /**
     * {@inheritDoc}
     */
    public function type(): string
    {
        return 'blog';
    }

    /**
     * {@inheritDoc}
     */
    public function category(): string
    {
        return 'post';
    }

    /**
     * {@inheritDoc}
     */
    public function links(): array
    {
        if (!isset($this->links)) {
            $urlBasePath = '/blog';

            $this->links = [
                'self' => ['href' => $urlBasePath . '/' . $this->uri()],
                'collection' => ['href' => $urlBasePath],
            ];
        }

        return $this->links;
    }
}
