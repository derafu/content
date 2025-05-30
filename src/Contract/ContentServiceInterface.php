<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Contract;

/**
 * Interface for content services.
 */
interface ContentServiceInterface
{
    /**
     * Get the context of the content website.
     *
     * @return ContentContextInterface
     */
    public function context(): ContentContextInterface;

    /**
     * Get the plugins of the content website.
     *
     * @return array<string, PluginInterface>
     */
    public function plugins(): array;

    /**
     * Get a plugin by name.
     *
     * @param string $name The name of the plugin.
     * @return PluginInterface
     */
    public function plugin(string $name): PluginInterface;
}
