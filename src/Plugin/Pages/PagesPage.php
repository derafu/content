<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Plugin\Pages;

use Derafu\Content\Abstract\AbstractContentItem;
use Derafu\Content\Plugin\Pages\Contract\PagesPageInterface;

/**
 * Pages page.
 */
class PagesPage extends AbstractContentItem implements PagesPageInterface
{
    /**
     * {@inheritDoc}
     */
    public function type(): string
    {
        return 'pages';
    }

    /**
     * {@inheritDoc}
     */
    public function category(): string
    {
        return 'page';
    }

    /**
     * {@inheritDoc}
     */
    public function parent(): ?PagesPageInterface
    {
        return $this->parent ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public function links(): array
    {
        if (!isset($this->links)) {
            $urlBasePath = '';

            $this->links = [
                'self' => ['href' => $urlBasePath . '/' . $this->uri()],
                'collection' => ['href' => $urlBasePath],
            ];
        }

        return $this->links;
    }
}
