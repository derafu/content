<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Plugin\Storage;

use Derafu\Content\Abstract\AbstractPlugin;
use Derafu\Content\Contract\ContentItemInterface;
use Derafu\Content\Contract\ContentPluginInterface;
use Derafu\Content\Contract\PluginInterface;

class StoragePlugin extends AbstractPlugin implements PluginInterface
{
    /**
     * Get the knowledge of the content website.
     *
     * @param array<ContentPluginInterface> $plugins Plugins.
     * @param array<string, mixed> $filters Filters.
     * @return array<ContentItemInterface>
     */
    public function knowledge(array $plugins, array $filters = []): array
    {
        $filters = array_filter($filters, fn ($value) => $value !== '');

        $knowledge = [];
        foreach ($plugins as $plugin) {
            if ($plugin instanceof ContentPluginInterface) {
                $knowledge = [
                    ...$knowledge,
                    ...$plugin->registry()->filter($filters),
                ];
            }
        }

        return $knowledge;
    }
}
