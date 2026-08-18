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
use Derafu\Content\Plugin\Pages\Contract\PagesPageInterface;
use Derafu\Content\Plugin\Pages\Contract\PagesRegistryInterface;

/**
 * Pages registry.
 */
class PagesRegistry extends AbstractContentRegistry implements PagesRegistryInterface
{
    /**
     * {@inheritDoc}
     */
    public function get(string $uri): PagesPageInterface
    {
        $page = parent::get($uri);

        assert($page instanceof PagesPageInterface);

        return $page;
    }

    /**
     * {@inheritDoc}
     */
    public function previous(string $uri, array $filters = []): ?PagesPageInterface
    {
        $page = parent::previous($uri, $filters);

        if ($page === null) {
            return null;
        }

        assert($page instanceof PagesPageInterface);

        return $page;
    }

    /**
     * {@inheritDoc}
     */
    public function next(string $uri, array $filters = []): ?PagesPageInterface
    {
        $page = parent::next($uri, $filters);

        if ($page === null) {
            return null;
        }

        assert($page instanceof PagesPageInterface);

        return $page;
    }

    /**
     * {@inheritDoc}
     */
    protected function getContentClass(): string
    {
        return PagesPage::class;
    }
}
