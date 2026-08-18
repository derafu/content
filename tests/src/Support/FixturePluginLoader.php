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

use Derafu\Content\Contract\ContentServiceInterface;
use Derafu\Content\Contract\PluginInterface;
use Derafu\Content\Contract\PluginLoaderInterface;

/**
 * Real (non-mock) plugin loader used by tests.
 *
 * It does not scan any configuration nor build plugins itself: the test
 * hands it already-constructed, already-loaded plugin instances (real
 * objects, backed by real fixture content on disk), and this loader just
 * returns them the same way PluginLoader::loadAll() would in production.
 */
final class FixturePluginLoader implements PluginLoaderInterface
{
    /**
     * @param array<string, PluginInterface> $plugins Plugins keyed by name.
     */
    public function __construct(
        private readonly array $plugins
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function loadAll(ContentServiceInterface $contentService): array
    {
        return $this->plugins;
    }
}
