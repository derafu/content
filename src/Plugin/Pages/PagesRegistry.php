<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Plugin\Pages;

use Derafu\Content\Abstract\AbstractContentRegistry;
use Derafu\Content\Plugin\Pages\Contract\PagesRegistryInterface;

/**
 * Pages registry.
 */
class PagesRegistry extends AbstractContentRegistry implements PagesRegistryInterface
{
    /**
     * {@inheritDoc}
     */
    protected function getContentClass(): string
    {
        return PagesPage::class;
    }
}
