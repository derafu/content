<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Plugin\Academy;

use Derafu\Content\Abstract\AbstractContentPlugin;
use Derafu\Content\ContentBag;
use Derafu\Content\Contract\ContentBagInterface;
use Derafu\Content\Contract\ContentLoaderInterface;
use Derafu\Content\Contract\ContentPluginInterface;
use Derafu\Content\Plugin\Academy\Contract\AcademyRegistryInterface;

/**
 * Plugin for creating an academy.
 */
class AcademyPlugin extends AbstractContentPlugin implements ContentPluginInterface
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
            'default' => 'academy',
        ],

        // Path to the academy content directory on the filesystem, relative to
        // website root.
        'path' => [
            'types' => 'string',
            'required' => true,
            'default' => 'resources/content/academy',
        ],

        // Academy page title for better SEO.
        'academyTitle' => [
            'types' => 'string',
            'required' => true,
            'default' => 'Academy',
        ],

        // Academy page description for better SEO.
        'academyDescription' => [
            'types' => 'string',
            'required' => true,
            'default' => 'Do you want to learn about a topic? Start a course with us!',
        ],

        // Array of glob patterns to include in the academy content relative to
        // the path option.
        'include' => [
            'types' => 'array',
            'required' => true,
            'default' => [
                '**.{markdown,md}',
            ],
        ],

        // Array of glob patterns to exclude in the academy content relative to
        // the path option.
        'exclude' => [
            'types' => 'array',
            'required' => true,
            'default' => [],
        ],

        // Whether to show the reading time.
        'showReadingTime' => [
            'types' => 'bool',
            'required' => true,
            'default' => true,
        ],

        // A callback to customize the reading time number displayed.
        'readingTime' => [
            'types' => 'string',
            'required' => false,
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

        // Array of predefined tags to include in the academy content.
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
     * Registry of the academy.
     *
     * @var AcademyRegistryInterface
     */
    private AcademyRegistryInterface $registry;

    /**
     * {@inheritDoc}
     */
    public function loadContent(
        ContentLoaderInterface $contentLoader
    ): ContentBagInterface {
        // Create the registry.
        $this->registry = new AcademyRegistry(
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
    public function registry(): AcademyRegistryInterface
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
