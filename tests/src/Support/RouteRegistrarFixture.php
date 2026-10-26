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

use Closure;
use Derafu\Routing\Contract\CollectionInterface;
use Derafu\Routing\Contract\RouteRegistrarInterface;
use Derafu\Routing\ValueObject\Route;

/**
 * Collects plugin routes without matching requests or generating URLs.
 */
final class RouteRegistrarFixture implements RouteRegistrarInterface
{
    public function __construct(private readonly CollectionInterface $routes)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function addRoute(
        string $name,
        string $path,
        string|array|Closure $handler,
        array $defaults = [],
        array $methods = [],
        array $roles = []
    ): static {
        $this->routes->add(new Route($name, $path, $handler, $defaults, $methods, $roles));
        return $this;
    }
}
