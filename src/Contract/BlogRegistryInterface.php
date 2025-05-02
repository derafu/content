<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Contract;

/**
 * Blog registry interface.
 */
interface BlogRegistryInterface
{
    /**
     * Get all blog posts.
     *
     * @param array $filters Filter criteria.
     * @return BlogPostInterface[]
     */
    public function getPosts(array $filters = []): array;

    /**
     * Get a blog post by slug.
     *
     * @param string $slug Slug of the blog post.
     * @return BlogPostInterface
     */
    public function getPost(string $slug): BlogPostInterface;

    /**
     * Get all tags.
     *
     * @param array $filters Filter criteria.
     * @return array<string, BlogTagInterface>
     */
    public function getTags(array $filters = []): array;
}
