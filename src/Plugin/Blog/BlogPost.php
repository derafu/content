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

use Derafu\Content\Abstract\AbstractContentItem;
use Derafu\Content\Plugin\Blog\Contract\BlogPostInterface;

/**
 * Class that represents a blog post.
 */
class BlogPost extends AbstractContentItem implements BlogPostInterface
{
    /**
     * {@inheritDoc}
     */
    protected array $metadataSchema = [
        '__allowUndefinedKeys' => true,
        'hide_table_of_contents' => [
            'types' => 'bool',
            'required' => true,
            'default' => true,
        ],
    ];

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
