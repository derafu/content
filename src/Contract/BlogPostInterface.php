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
 * Blog post interface.
 */
interface BlogPostInterface extends ContentFileInterface
{
    /**
     * Get the title of the blog post.
     *
     * @return string
     */
    public function title(): string;

    /**
     * Get the image of the blog post.
     *
     * @return string|null
     */
    public function image(): ?string;

    /**
     * Get the author of the blog post.
     *
     * @return ContentAuthorInterface|null
     */
    public function author(): ?ContentAuthorInterface;

    /**
     * Get the reading time of the blog post in minutes.
     *
     * @return int
     */
    public function time(): int;

    /**
     * Get the links of the blog post.
     *
     * @return array
     */
    public function links(): array;
}
