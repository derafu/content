<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Plugin\Pages\Contract;

use Derafu\Content\Contract\ContentItemInterface;

/**
 * Pages page interface.
 */
interface PagesPageInterface extends ContentItemInterface
{
    /**
     * Get the parent of the content.
     *
     * @return PagesPageInterface|null
     */
    public function parent(): ?PagesPageInterface;

    /**
     * Get the children of the content.
     *
     * @return array<PagesPageInterface>
     */
    public function children(): array;
}
