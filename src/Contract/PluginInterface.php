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

use Derafu\Config\Contract\OptionsInterface;

/**
 * Interface for plugins.
 */
interface PluginInterface
{
    /**
     * Get the name of the plugin.
     *
     * @return string
     */
    public function name(): string;

    /**
     * Inject the HTML tags into the website, in head and/or body.
     *
     * @param ContentBagInterface $bag The content bag.
     * @return ContentHtmlTagsInterface
     */
    public function injectHtmlTags(ContentBagInterface $bag): ContentHtmlTagsInterface;

    /**
     * Get the options of the plugin.
     *
     * @return OptionsInterface
     */
    public function options(): OptionsInterface;

    /**
     * Validate the options.
     *
     * This will only validate the options, without creating the object.
     *
     * This will be called before the plugin is instantiated. So the plugin
     * does not need to be instantiated to validate the options. And, in
     * consequence, should not validate the options if this method is
     * implemented.
     *
     * @param array $options Options of the plugin.
     * @return void
     */
    public static function validateOptions(array $options): void;
}
