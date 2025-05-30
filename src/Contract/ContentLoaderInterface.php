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
 * Interface for content loader.
 */
interface ContentLoaderInterface
{
    /**
     * Load content items.
     *
     * @param string $path Path to content items.
     * @param array $include Glob patterns to include.
     * @param array $exclude Glob patterns to exclude.
     * @return array
     */
    public function scan(string $path, array $include, array $exclude): array;

    /**
     * Load content items from hierarchy.
     *
     * @param string $class Class of the content items.
     * @param array $hierarchy Hierarchy of content items.
     * @return array<string,ContentItemInterface>
     */
    public function load(string $class, array $hierarchy): array;
}
