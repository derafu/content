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
 * Interface for content plugins.
 */
interface ContentPluginInterface extends PluginInterface
{
    /**
     * Load the content of the plugin.
     *
     * This will be executed after the content config is loaded.
     *
     * @param ContentLoaderInterface $contentLoader Content loader.
     * @return ContentBagInterface
     */
    public function loadContent(
        ContentLoaderInterface $contentLoader
    ): ContentBagInterface;

    /**
     * This will be executed after all the contents are loaded.
     *
     * The actions are a list of actions to execute after the content is loaded.
     * The key is the name of the action and the value is a callable that will
     * be executed after the content is loaded.
     *
     * @param ContentBagInterface $bag The content bag.
     * @param array<string, callable> $actions The actions that can be executed
     * after the content is loaded.
     * @return void
     */
    public function contentLoaded(ContentBagInterface $bag, array $actions): void;

    /**
     * Get the paths to watch for changes.
     *
     * This will be used to watch for changes in the content.
     *
     * @return array<string>
     */
    public function getPathsToWatch(): array;

    /**
     * Get the translation files.
     *
     * @return array<string, string>
     */
    public function getTranslationFiles(): array;

    /**
     * Translate the content.
     *
     * @param ContentBagInterface $bag The content bag.
     * @param array<string, string> $translationFiles The translation files.
     * @return void
     */
    public function translateContent(
        ContentBagInterface $bag,
        array $translationFiles
    ): void;

    /**
     * Get the registry of the plugin.
     *
     * @return ContentRegistryInterface
     */
    public function registry(): ContentRegistryInterface;
}
