<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Plugin\Docs\Contract;

use Derafu\Content\Contract\ContentItemInterface;

/**
 * Docs doc interface.
 */
interface DocsDocInterface extends ContentItemInterface
{
    /**
     * Get the parent of the content.
     *
     * @return DocsDocInterface|null
     */
    public function parent(): ?DocsDocInterface;

    /**
     * Get the children of the content.
     *
     * @return array<DocsDocInterface>
     */
    public function children(): array;
}
