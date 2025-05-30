<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Abstract;

use Derafu\Config\Contract\OptionsInterface;
use Derafu\Config\Options;
use Derafu\Content\ContentContext;
use Derafu\Content\ContentHtmlTags;
use Derafu\Content\Contract\ContentBagInterface;
use Derafu\Content\Contract\ContentHtmlTagsInterface;
use Derafu\Content\Contract\PluginInterface;

/**
 * Abstract class for plugins.
 *
 * It's not mandatory to extend this class, but it's recommended to do so.
 */
abstract class AbstractPlugin implements PluginInterface
{
    /**
     * Context of the content website.
     *
     * @var ContentContext
     */
    protected ContentContext $context;

    /**
     * Options of the plugin.
     *
     * @var OptionsInterface
     */
    protected OptionsInterface $options;

    /**
     * Constructor of the plugin.
     *
     * @param ContentContext $context Context of the content website.
     */
    public function __construct(ContentContext $context, array $options = [])
    {
        $this->context = $context;
        $this->options = new Options($options, static::getSchema());
    }

    /**
     * {@inheritDoc}
     */
    public function name(): string
    {
        return $this->options->get('name');
    }

    /**
     * {@inheritDoc}
     */
    public function injectHtmlTags(ContentBagInterface $bag): ContentHtmlTagsInterface
    {
        $htmlTags = new ContentHtmlTags();

        // TODO: Add the HTML tags of the plugin.

        return $htmlTags;
    }

    /**
     * {@inheritDoc}
     */
    public function options(): OptionsInterface
    {
        return $this->options;
    }

    /**
     * {@inheritDoc}
     */
    public static function validateOptions(array $options): void
    {
        $options = new Options($options, static::getSchema());
        $options->validate();
    }

    /**
     * Get the schema of the options of the plugin.
     *
     * @return array
     */
    protected static function getSchema(): array
    {
        return [
            '__allowUndefinedKeys' => true,
        ];
    }
}
