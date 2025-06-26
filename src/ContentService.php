<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content;

use Derafu\Content\Contract\ContentContextInterface;
use Derafu\Content\Contract\ContentServiceInterface;
use Derafu\Content\Contract\PluginInterface;
use Derafu\Content\Contract\PluginLoaderInterface;
use InvalidArgumentException;

/**
 * Main service to manage the content.
 */
class ContentService implements ContentServiceInterface
{
    /**
     * Plugins of the content service.
     *
     * @var array<string, PluginInterface>
     */
    private array $plugins;

    /**
     * Constructor.
     *
     * @param ContentContextInterface $context Content context.
     */
    public function __construct(
        private readonly ContentContextInterface $context,
        private readonly PluginLoaderInterface $pluginLoader
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function context(): ContentContextInterface
    {
        return $this->context;
    }

    /**
     * {@inheritDoc}
     */
    public function plugins(): array
    {
        if (!isset($this->plugins)) {
            $this->plugins = $this->pluginLoader->loadAll($this);
        }

        return $this->plugins;
    }

    /**
     * {@inheritDoc}
     */
    public function plugin(string $name): PluginInterface
    {
        $plugins = array_keys($this->plugins());

        if (!in_array($name, $plugins)) {
            throw new InvalidArgumentException(sprintf(
                'Plugin "%s" not found. Available plugins: %s.',
                $name,
                implode(', ', $plugins)
            ));
        }

        return $this->plugins[$name];
    }
}
