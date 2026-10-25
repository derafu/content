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

use Derafu\Config\Configuration;
use Derafu\Config\Contract\ConfigurationInterface;
use Derafu\Content\Contract\ContentConfigInterface;

/**
 * Configuration of the content website.
 */
class ContentConfig implements ContentConfigInterface
{
    /**
     * Configuration of the content website.
     *
     * @var ConfigurationInterface
     */
    private ConfigurationInterface $config;

    /**
     * Schema of the configuration of the content website.
     *
     * @var array<string, mixed>
     */
    private const CONFIG_SCHEMA = [
        // Title of the content website.
        'title' => [
            'types' => 'string',
            'required' => true,
        ],

        // URL of the content website.
        //
        // This can also be considered the top-level domain of the content
        // website. This must not include the base URL of the content website
        // (next attribute).
        'url' => [
            'types' => 'string',
            'required' => true,
        ],

        // Base URL of the content website.
        //
        // This can be considered as the path after the top-level domain of the
        // content website.
        'baseUrl' => [
            'types' => 'string',
            'required' => false, // It's empty when it is not configured.
        ],

        // Favicon of the content website.
        'favicon' => [
            'types' => 'string',
            'required' => false,
        ],

        // Delimiter to use in the title of the content website.
        'titleDelimiter' => [
            'types' => 'string',
            'required' => false,
            'default' => '|',
        ],

        // Tagline of the content website.
        'tagline' => [
            'types' => 'string',
            'required' => false,
        ],

        // Format to use in the URL of the content website.
        'uriFormat' => [
            'types' => 'string',
            'required' => true,
            'default' => '{id}.html',
        ],

        // Whether the content website is no-index.
        //
        // This tell to the search engines to not index the content website.
        'noIndex' => [
            'types' => 'boolean',
            'required' => false,
            'default' => false,
        ],

        // The behavior when a broken link is found.
        'onBrokenLinks' => [
            'types' => 'string',
            'required' => true,
            'choices' => [
                'ignore', // Ignore the broken link.
                'log',    // Log the broken link.
                'warn',   // Warn about the broken link.
                'throw',  // Throw an exception if a broken link is found.
            ],
            'default' => 'throw',
        ],

        // Markdown configuration.
        'markdown' => [
            'types' => 'array',
            'required' => false,
            'schema' => [
                // TODO: Add schema for markdown.
                '__allowUndefinedKeys' => true,
            ],
        ],

        // Plugins to use in the content website.
        'plugins' => [
            'types' => 'array',
            'required' => false,
            'schema' => [
                // TODO: Add schema for plugins.
                '__allowUndefinedKeys' => true,
            ],
        ],

        // Configuration of i18n in the content website.
        'i18n' => [
            'types' => 'array',
            'required' => false,
            'schema' => [
                // TODO: Add schema for i18n.
                '__allowUndefinedKeys' => true,
            ],
        ],

        // Custom fields to use in the content website.
        'customFields' => [
            'types' => 'array',
            'required' => false,
            'schema' => [
                '__allowUndefinedKeys' => true,
            ],
        ],

        // Cache of the content website.
        'cache' => [
            'types' => 'array',
            'required' => false,
            'schema' => [
                // Whether the content cache is enabled.
                'enabled' => [
                    'types' => 'boolean',
                    'required' => false,
                    'default' => true,
                ],
                '__allowUndefinedKeys' => true,
            ],
        ],
    ];

    /**
     * Constructor.
     *
     * @param array<string,mixed> $config Configuration.
     */
    public function __construct(array $config)
    {
        $this->config = new Configuration($config, self::CONFIG_SCHEMA);
    }

    /**
     * {@inheritDoc}
     */
    public function title(): string
    {
        return $this->config->getReader()->requireString('title');
    }

    /**
     * {@inheritDoc}
     */
    public function url(): string
    {
        return $this->config->getReader()->requireString('url');
    }

    /**
     * {@inheritDoc}
     */
    public function baseUrl(): string
    {
        return $this->config->getReader()->getString('baseUrl') ?? '';
    }

    /**
     * {@inheritDoc}
     */
    public function favicon(): string|null
    {
        return $this->config->getReader()->getString('favicon');
    }

    /**
     * {@inheritDoc}
     */
    public function titleDelimiter(): string
    {
        return $this->config->getReader()->requireString('titleDelimiter');
    }

    /**
     * {@inheritDoc}
     */
    public function tagline(): string|null
    {
        return $this->config->getReader()->getString('tagline');
    }

    /**
     * {@inheritDoc}
     */
    public function uriFormat(): string
    {
        return $this->config->getReader()->requireString('uriFormat');
    }

    /**
     * {@inheritDoc}
     */
    public function noIndex(): bool
    {
        return $this->config->getReader()->requireBool('noIndex');
    }

    /**
     * {@inheritDoc}
     */
    public function onBrokenLinks(): string
    {
        return $this->config->getReader()->requireString('onBrokenLinks');
    }

    /**
     * {@inheritDoc}
     *
     * @return array<string, mixed>
     */
    public function markdown(): array
    {
        return $this->config->requireContainer('markdown')->all();
    }

    /**
     * {@inheritDoc}
     *
     * @return array<string, mixed>
     */
    public function plugins(): array
    {
        return $this->config->requireContainer('plugins')->all();
    }

    /**
     * {@inheritDoc}
     *
     * @return array<string, mixed>
     */
    public function i18n(): array
    {
        return $this->config->requireContainer('i18n')->all();
    }

    /**
     * {@inheritDoc}
     */
    public function cacheEnabled(): bool
    {
        return $this->config->getReader()->requireBool('cache.enabled');
    }

    /**
     * {@inheritDoc}
     */
    public function customFields(): array
    {
        return $this->config->requireContainer('customFields')->all();
    }

    /**
     * {@inheritDoc}
     */
    public function customField(string $name, mixed $default = null): mixed
    {
        return $this->config->get('customFields.' . $name, $default);
    }
}
