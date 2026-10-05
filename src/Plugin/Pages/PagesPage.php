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
use Derafu\Routing\Contract\RouterInterface;

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
     *
     * The router is not used on purpose. The pages of a website are served
     * from the root by the file system parser, which gives them no name, so
     * the router has no route that builds `/{uri}`: the route named
     * `pages_page` is another path (`/pages/{uri}`) and using it would change
     * the links that are delivered.
     */
    public function links(RouterInterface $router): array
    {
        return [
            'self' => ['href' => '/' . $this->uri()],
            'collection' => ['href' => ''],
        ];
    }
}
