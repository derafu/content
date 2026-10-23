<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent\Support;

use Derafu\Content\Contract\ContentBagInterface;
use Derafu\Content\Plugin\Docs\DocsPlugin;

/**
 * Real plugin that uses the actions that the loader gives to the plugins once
 * their content is loaded: it adds a route to the website, or the route that
 * the "route" option says (even an incomplete one, to see the errors).
 */
final class RouteAddingPlugin extends DocsPlugin
{
    /**
     * {@inheritDoc}
     *
     * It accepts "route".
     */
    protected static function getSchema(): array
    {
        return [
            ...parent::getSchema(),
            'route' => ['types' => 'array', 'required' => false, 'schema' => ['__allowUndefinedKeys' => true]],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function contentLoaded(ContentBagInterface $bag, array $actions): void
    {
        $route = $this->options->requireContainer('route')->all() ?: [
            'name' => 'custom_route',
            'path' => '/custom/{id}',
            'handler' => 'custom.handler',
        ];

        $actions['addRoute']($route);
    }
}
