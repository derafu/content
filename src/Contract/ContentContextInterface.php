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
 * Interface for content context.
 */
interface ContentContextInterface
{
    /**
     * Get the configuration of the content website.
     *
     * @return ContentConfigInterface
     */
    public function config(): ContentConfigInterface;
}
