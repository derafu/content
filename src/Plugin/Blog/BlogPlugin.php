<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Plugin\Blog;

use Derafu\Content\Abstract\AbstractContentPlugin;
use Derafu\Content\ContentBag;
use Derafu\Content\Contract\ContentBagInterface;
use Derafu\Content\Contract\ContentLoaderInterface;
use Derafu\Content\Contract\ContentPluginInterface;
use Derafu\Content\Plugin\Blog\Contract\BlogRegistryInterface;

/**
 * Plugin for creating a blog.
 */
class BlogPlugin extends AbstractContentPlugin implements ContentPluginInterface
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
            'default' => 'blog',
        ],

        // Path to the blog content directory on the filesystem, relative to
        // website root.
        'path' => [
            'types' => 'string',
            'required' => true,
            'default' => 'resources/content/blog',
        ],

        // Blog page title for better SEO.
        'blogTitle' => [
            'types' => 'string',
            'required' => true,
            'default' => 'Blog',
        ],

        // Blog page description for better SEO.
        'blogDescription' => [
            'types' => 'string',
            'required' => true,
            'default' => 'Thoughts, stories, and the latest from our world.',
        ],

        // Number of posts to show in the sidebar (recent posts).
        'blogSidebarCount' => [
            'types' => 'int',
            'required' => true,
            'default' => 5,
        ],

        // Title of the sidebar.
        'blogSidebarTitle' => [
            'types' => 'string',
            'required' => true,
            'default' => 'Recent posts',
        ],

        // Array of glob patterns to include in the blog content relative to
        // the path option.
        'include' => [
            'types' => 'array',
            'required' => true,
            'default' => [
                '**.{markdown,md}',
            ],
        ],

        // Array of glob patterns to exclude in the blog content relative to
        // the path option.
        'exclude' => [
            'types' => 'array',
            'required' => true,
            'default' => [],
        ],

        // Number of posts per page.
        'postsPerPage' => [
            'types' => 'int',
            'required' => true,
            'default' => 10,
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

        // Path to the authors map file, relative to the blog content directory.
        'authorsMapPath' => [
            'types' => 'string',
            'required' => false,
        ],

        // Options for the feed.
        'feedOptions' => [
            'types' => 'array',
            'required' => false,
            'schema' => [
                'limit' => [
                    'types' => 'int',
                    'required' => true,
                    'default' => 20,
                ],
                'title' => [
                    'types' => 'string',
                    'required' => false, // By default, the blog title is used.
                ],
                'description' => [
                    'types' => 'string',
                    'required' => false, // By default, the blog description is used.
                ],
                'copyright' => [
                    'types' => 'string',
                    'required' => false,
                ],
                'language' => [
                    'types' => 'string',
                    'required' => false,
                ],
                'sortPosts' => [
                    'types' => 'string',
                    'required' => true,
                    'choices' => ['descending', 'ascending'],
                    'default' => 'descending',
                ],
            ],
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

        // Array of predefined tags to include in the blog content.
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
     * Registry of the blog.
     *
     * @var BlogRegistryInterface
     */
    private BlogRegistryInterface $registry;

    /**
     * {@inheritDoc}
     */
    public function loadContent(
        ContentLoaderInterface $contentLoader
    ): ContentBagInterface {
        // Create the registry.
        $this->registry = new BlogRegistry(
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
    public function registry(): BlogRegistryInterface
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
