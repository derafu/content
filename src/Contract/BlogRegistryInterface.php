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
interface BlogRegistryInterface extends ContentRegistryInterface
{
    /**
     * Get blog posts filtered by criteria.
     *
     * @param array<string, mixed> $filters Filter criteria.
     * @return array<BlogPostInterface>
     */
    public function filter(array $filters = []): array;

    /**
     * Get a blog post by URI.
     *
     * @param string $uri URI of the blog post.
     * @return BlogPostInterface
     */
    public function get(string $uri): BlogPostInterface;

    /**
     * Get the previous blog post relative to the given URI.
     *
     * @param string $uri URI of the blog post.
     * @param array<string, mixed> $filters Filter criteria.
     * @return BlogPostInterface|null
     */
    public function previous(string $uri, array $filters = []): ?BlogPostInterface;

    /**
     * Get the next blog post relative to the given URI.
     *
     * @param string $uri URI of the blog post.
     * @param array<string, mixed> $filters Filter criteria.
     * @return BlogPostInterface|null
     */
    public function next(string $uri, array $filters = []): ?BlogPostInterface;
}
