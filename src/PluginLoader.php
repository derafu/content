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
use Derafu\Content\Contract\ContentLoaderInterface;
use Derafu\Content\Contract\ContentPluginInterface;
use Derafu\Content\Contract\ContentServiceInterface;
use Derafu\Content\Contract\PluginInterface;
use Derafu\Content\Contract\PluginLoaderInterface;
use Derafu\Routing\Contract\RouterInterface;
use InvalidArgumentException;

/**
 * Plugin loader.
 */
class PluginLoader implements PluginLoaderInterface
{
    /**
     * Constructor.
     *
     * @param ContentLoaderInterface $contentLoader Content loader.
     */
    public function __construct(
        private readonly ContentLoaderInterface $contentLoader,
        private readonly RouterInterface $router
    ) {
    }

    /**
     * Load the plugins.
     *
     * @return array<string, PluginInterface>
     */
    public function loadAll(ContentServiceInterface $contentService): array
    {
        $plugins = [];

        // Initialize plugins: this just creates the plugin object and validate
        // the options of the plugin against the schema of the plugin.
        $pluginsList = $contentService->context()->config()->plugins();
        foreach ($pluginsList as $pluginName => $pluginOptions) {
            $plugins[$pluginName] = $this->load(
                $pluginName,
                $pluginOptions ?? [],
                $contentService->context()
            );
        }

        // Load the content of the plugins.
        $bags = [];
        foreach ($plugins as $plugin) {
            if ($plugin instanceof ContentPluginInterface) {
                $bags[$plugin->name()] = $plugin->loadContent(
                    $this->contentLoader
                );
            }
        }

        // Prepare the actions to execute after the content is loaded in each
        // plugin.
        $actions = $this->getActions();

        // Execute the actions in the content data loaded in each plugin.
        foreach ($plugins as $plugin) {
            if ($plugin instanceof ContentPluginInterface) {
                $plugin->contentLoaded($bags[$plugin->name()], $actions);
            }
        }

        // Return the plugins.
        return $plugins;
    }

    /**
     * Load a plugin.
     *
     * @param string $name Name of the plugin.
     * @param array $options Options of the plugin.
     * @param ContentContextInterface $contentContext Content context.
     * @return PluginInterface
     */
    private function load(
        string $name,
        array $options,
        ContentContextInterface $contentContext
    ): PluginInterface {
        if (!empty($options['class'])) {
            $class = $options['class'];
        } else {
            $plugin = ucfirst($name);
            $class = 'Derafu\Content\Plugin\\'
                . $plugin . '\\'
                . $plugin . 'Plugin'
            ;
        }

        return new $class($contentContext, $options);
    }

    /**
     * Get the actions to execute after the content is loaded in each plugin.
     *
     * @return array<string, callable>
     */
    private function getActions(): array
    {
        return [
            // Create a route to add to the website.
            'addRoute' => fn (array $route) =>
                $this->router->addRoute(
                    $route['name'] ?? throw new InvalidArgumentException('Key "name" is required for addRoute().'),
                    $route['path'] ?? throw new InvalidArgumentException('Key "path" is required for addRoute().'),
                    $route['handler'] ?? throw new InvalidArgumentException('Key "handler" is required for addRoute().'),
                    $route['defaults'] ?? [],
                    $route['methods'] ?? [],
                )
            ,
        ];
    }
}
