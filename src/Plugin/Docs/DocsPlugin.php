<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Plugin\Docs;

use Derafu\Content\Abstract\AbstractContentPlugin;
use Derafu\Content\ContentBag;
use Derafu\Content\Contract\ContentBagInterface;
use Derafu\Content\Contract\ContentLoaderInterface;
use Derafu\Content\Contract\ContentPluginInterface;
use Derafu\Content\Plugin\Docs\Contract\DocsRegistryInterface;

/**
 * Plugin for documentation.
 */
class DocsPlugin extends AbstractContentPlugin implements ContentPluginInterface
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
            'default' => 'docs',
        ],

        // Path to the docs content directory on the filesystem, relative to
        // website root.
        'path' => [
            'types' => 'string',
            'required' => true,
            'default' => 'resources/content/docs',
        ],

        // Array of glob patterns to include in the docs content relative to
        // the path option.
        'include' => [
            'types' => 'array',
            'required' => true,
            'default' => [
                '**.{markdown,md}',
            ],
        ],

        // Array of glob patterns to exclude in the docs content relative to
        // the path option.
        'exclude' => [
            'types' => 'array',
            'required' => true,
            'default' => [],
        ],

        // Path to the sidebar file, relative to the path option. If true,
        // it will be automatically generated. If false, no sidebar will be
        // generated.
        'sidebarPath' => [
            'types' => ['string', 'bool'],
            'required' => true,
            'default' => true, // Will be automatically generated.
        ],

        // Whether the sidebar should be collapsible.
        'sidebarCollapsible' => [
            'types' => 'bool',
            'required' => true,
            'default' => true,
        ],

        // Whether the sidebar should be collapsed by default.
        'sidebarCollapsed' => [
            'types' => 'bool',
            'required' => true,
            'default' => true,
        ],

        // Depth of the sidebar.
        'sidebarDepth' => [
            'types' => 'int',
            'required' => true,
            'default' => 5,
        ],

        // Whether to show the last update author.
        'showLastUpdateAuthor' => [
            'types' => 'bool',
            'required' => true,
            'default' => true,
        ],

        // Whether to show the last update time.
        'showLastUpdateTime' => [
            'types' => 'bool',
            'required' => true,
            'default' => true,
        ],

        // Whether to show the breadcrumbs.
        'breadcrumbs' => [
            'types' => 'bool',
            'required' => true,
            'default' => true,
        ],

        // Array of predefined tags to include in the docs content.
        'tags' => [
            'types' => ['array', 'string'],
            'required' => true,
            'default' => [],
        ],

        // What to do when an inline tag is found and it's not in the tags
        // option.
        'onInlineTags' => [
            'types' => 'string',
            'required' => true,
            'choices' => ['ignore', 'log', 'warn', 'throw'],
            'default' => 'warn',
        ],
    ];

    /**
     * Registry of the docs.
     *
     * @var DocsRegistryInterface
     */
    private DocsRegistryInterface $registry;

    /**
     * {@inheritDoc}
     */
    public function loadContent(
        ContentLoaderInterface $contentLoader
    ): ContentBagInterface {
        // Create the registry.
        $this->registry = new DocsRegistry(
            $contentLoader,
            $this->options['path'],
            $this->options['include']->all(),
            $this->options['exclude']->all()
        );

        // Load the content.
        $items = $this->registry->all();

        // Create the bag, add the items and return it.
        return new ContentBag($items);
    }

    /**
     * {@inheritDoc}
     */
    public function registry(): DocsRegistryInterface
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
