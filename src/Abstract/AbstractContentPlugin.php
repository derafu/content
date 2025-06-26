<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Abstract;

use Derafu\Config\Contract\OptionsInterface;
use Derafu\Config\Options;
use Derafu\Content\ContentContext;
use Derafu\Content\Contract\ContentBagInterface;
use Derafu\Content\Contract\ContentPluginInterface;

/**
 * Abstract class for content plugins.
 *
 * It's not mandatory to extend this class, but it's recommended to do so.
 */
abstract class AbstractContentPlugin extends AbstractPlugin implements ContentPluginInterface
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
    public function contentLoaded(ContentBagInterface $bag, array $actions): void
    {
        // Future implementation.
    }

    /**
     * {@inheritDoc}
     */
    public function getPathsToWatch(): array
    {
        return [$this->options->get('path')];
    }

    /**
     * {@inheritDoc}
     */
    public function getTranslationFiles(): array
    {
        $files = [];

        // TODO: Get the translation files.

        return $files;
    }

    /**
     * {@inheritDoc}
     */
    public function translateContent(ContentBagInterface $bag, array $translationFiles): void
    {
        // TODO: Translate the content.
    }
}
