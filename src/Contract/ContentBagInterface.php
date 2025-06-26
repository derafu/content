<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Contract;

/**
 * Interface for content bags.
 *
 * A content bag is a collection of content items, one or more. So, the bag can
 * contain a single ContentItem or a ContentRegistry.
 */
interface ContentBagInterface
{
    /**
     * Get a value from the bag.
     *
     * @param string $name Name of the value.
     * @param mixed $default Default value if the value is not set.
     * @return mixed
     */
    public function get(string $name, mixed $default = null): mixed;
}
