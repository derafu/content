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

use Derafu\Routing\Parser\DynamicParser;
use Derafu\Routing\Parser\FileSystemParser;
use Derafu\Routing\Parser\StaticParser;
use Derafu\Routing\Router;
use Symfony\Component\Yaml\Yaml;

/**
 * Builds a real Router (real parsers, real routes), loaded with this
 * package's actual content-routes.yaml, so routing precedence can be
 * tested against the exact configuration a consuming website ships.
 */
final class RouterFixture
{
    /**
     * Build a router mirroring how a website like the ones this package
     * targets would wire it: StaticParser and DynamicParser first (for
     * hand-written routes, including this package's own content routes),
     * then FileSystemParser last (for auto-discovered, hand-authored flat
     * pages placed directly under a "pages" template directory).
     *
     * @param array<string, array<string, mixed>> $extraRoutes Extra routes to
     * register on top of this package's own content-routes.yaml, keyed by
     * route name (same shape as a content-routes.yaml entry). Useful to
     * simulate a website's hand-written routes (e.g. static "/legal").
     * @param bool $includeContentRoutes Whether to load this package's own
     * content-routes.yaml.
     * @return Router
     */
    public static function create(
        array $extraRoutes = [],
        bool $includeContentRoutes = true
    ): Router {
        $routes = $extraRoutes;

        if ($includeContentRoutes) {
            $contentRoutesFile = dirname(__DIR__, 3)
                . '/resources/config/content-routes.yaml';
            $routes = [
                ...$routes,
                ...Yaml::parseFile($contentRoutesFile),
            ];
        }

        return new Router(
            parsers: [
                new StaticParser(),
                new DynamicParser(),
                new FileSystemParser(
                    [ContentFixtures::templatesPath('pages')],
                    ['.html.twig']
                ),
            ],
            routes: $routes,
        );
    }
}
