<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Contract;

/**
 * Plugin loader interface.
 */
interface PluginLoaderInterface
{
    /**
     * Load all plugins.
     *
     * @param ContentServiceInterface $contentService Content service.
     * @return array<string, PluginInterface>
     */
    public function loadAll(ContentServiceInterface $contentService): array;
}
