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

use Derafu\Content\Entity\ContentItem;

/**
 * Interface for content loader.
 */
interface ContentLoaderInterface
{
    /**
     * Load content items.
     *
     * @param string $path Path to content items.
     * @param array $extensions File extensions to include.
     * @param string $class Class to use to create the
     * content item.
     * @param bool $recursive Recursively load content items.
     * @return array<string,object>
     */
    public function load(
        string $path,
        array $extensions = ['md'],
        string $class = ContentItem::class,
        bool $recursive = true
    ): array;
}
