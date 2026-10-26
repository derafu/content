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

use Derafu\Content\Abstract\AbstractContentItem;
use Derafu\Content\Plugin\Pages\Contract\PagesPageInterface;
use Derafu\Translation\Exception\Core\TranslatableLogicException as LogicException;

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
     *
     * @throws LogicException If the assigned parent is not a page.
     */
    public function parent(): ?PagesPageInterface
    {
        $parent = parent::parent();
        if ($parent !== null && !$parent instanceof PagesPageInterface) {
            throw new LogicException([
                'The parent of page "{page}" must implement {interface}; {type} given.',
                'page' => $this->name(),
                'interface' => PagesPageInterface::class,
                'type' => get_debug_type($parent),
            ]);
        }

        return $parent;
    }

    /**
     * {@inheritDoc}
     *
     * Pages have no listing route.
     */
    protected function collectionRoute(): ?string
    {
        return null;
    }
}
