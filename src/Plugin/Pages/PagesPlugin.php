<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Plugin\Pages;

use Derafu\Content\Abstract\AbstractContentPlugin;
use Derafu\Content\ContentBag;
use Derafu\Content\Contract\ContentBagInterface;
use Derafu\Content\Contract\ContentLoaderInterface;
use Derafu\Content\Contract\ContentPluginInterface;
use Derafu\Content\Plugin\Pages\Contract\PagesRegistryInterface;

/**
 * Plugin for pages of the website.
 */
class PagesPlugin extends AbstractContentPlugin implements ContentPluginInterface
{
    /**
     * Schema of the options of the plugin.
     *
     * @var array
     */
    private const OPTIONS_SCHEMA = [
        // Name of the plugin.
        'name' => [
            'types' => 'string',
            'required' => true,
            'default' => 'pages',
        ],

        // Path to the docs content directory on the filesystem, relative to
        // website root.
        'path' => [
            'types' => 'string',
            'required' => true,
            'default' => 'resources/content/pages',
        ],

        // Array of glob patterns to include in the pages content relative to
        // the path option.
        'include' => [
            'types' => 'array',
            'required' => true,
            'default' => [
                '**.{markdown,md,html.twig}',
            ],
        ],

        // Array of glob patterns to exclude in the pages content relative to
        // the path option.
        'exclude' => [
            'types' => 'array',
            'required' => true,
            'default' => [],
        ],

        // Whether to show the last update author.
        'showLastUpdateAuthor' => [
            'types' => 'bool',
            'required' => true,
            'default' => false,
        ],

        // Whether to show the last update time.
        'showLastUpdateTime' => [
            'types' => 'bool',
            'required' => true,
            'default' => false,
        ],
    ];

    /**
     * Registry of the plugin.
     *
     * @var PagesRegistryInterface
     */
    private PagesRegistryInterface $registry;

    /**
     * {@inheritDoc}
     */
    public function loadContent(
        ContentLoaderInterface $contentLoader
    ): ContentBagInterface {
        $this->registry = new PagesRegistry(
            $contentLoader,
            $this->options['path'],
            $this->options['include'],
            $this->options['exclude']
        );

        $items = $this->registry->all();

        return new ContentBag($items);
    }

    /**
     * {@inheritDoc}
     */
    public function registry(): PagesRegistryInterface
    {
        return $this->registry;
    }

    /**
     * {@inheritDoc}
     */
    protected static function getSchema(): array
    {
        return self::OPTIONS_SCHEMA;
    }
}
