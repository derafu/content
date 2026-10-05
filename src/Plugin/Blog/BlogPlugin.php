<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
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

        // Options for the feed.
        'feedOptions' => [
            'types' => 'array',
            'required' => false,
            'schema' => [
                // Number of posts in the feed: always the latest ones.
                'limit' => [
                    'types' => 'int',
                    'required' => true,
                    'default' => 10,
                ],
                // Most posts a reader can ask for with "?limit=" in the URL
                // of the feed. A bigger number is reduced to this one.
                'maxLimit' => [
                    'types' => 'int',
                    'required' => true,
                    'default' => 50,
                ],
                'title' => [
                    'types' => 'string',
                    'required' => false, // By default "Blog of {site}", with the title of the website.
                ],
                'description' => [
                    'types' => 'string',
                    'required' => false, // By default a generic description of the blog.
                ],
                'copyright' => [
                    'types' => 'string',
                    'required' => false, // It is not written in the feed unless it is set.
                ],
                'language' => [
                    'types' => 'string',
                    'required' => false, // For example "es-CL". It is not written in the feed unless it is set.
                ],
                // Order of the posts in the feed. The posts that are part of
                // it are always the latest ones, this only sets the order in
                // which they are listed.
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
            $this->options['exclude']->all(),
            $this->context->cache()
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
