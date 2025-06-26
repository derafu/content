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
            'required' => false, // It's not required because it can be inferred from the URL.
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
    ];

    /**
     * Constructor.
     *
     * @param array<string,array<string,mixed>> $config Configuration.
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
        return $this->config->get('title');
    }

    /**
     * {@inheritDoc}
     */
    public function url(): string
    {
        return $this->config->get('url');
    }

    /**
     * {@inheritDoc}
     */
    public function baseUrl(): string
    {
        return $this->config->get('baseUrl');
    }

    /**
     * {@inheritDoc}
     */
    public function favicon(): string|null
    {
        return $this->config->get('favicon');
    }

    /**
     * {@inheritDoc}
     */
    public function titleDelimiter(): string
    {
        return $this->config->get('titleDelimiter');
    }

    /**
     * {@inheritDoc}
     */
    public function tagline(): string|null
    {
        return $this->config->get('tagline');
    }

    /**
     * {@inheritDoc}
     */
    public function uriFormat(): string
    {
        return $this->config->get('uriFormat');
    }

    /**
     * {@inheritDoc}
     */
    public function noIndex(): bool
    {
        return $this->config->get('noIndex');
    }

    /**
     * {@inheritDoc}
     */
    public function onBrokenLinks(): string
    {
        return $this->config->get('onBrokenLinks');
    }

    /**
     * {@inheritDoc}
     */
    public function markdown(): array
    {
        return $this->config->get('markdown');
    }

    /**
     * {@inheritDoc}
     */
    public function plugins(): array
    {
        return $this->config->get('plugins')->all();
    }

    /**
     * {@inheritDoc}
     */
    public function i18n(): array
    {
        return $this->config->get('i18n')->all();
    }

    /**
     * {@inheritDoc}
     */
    public function customFields(): array
    {
        return $this->config->get('customFields')->all();
    }

    /**
     * {@inheritDoc}
     */
    public function customField(string $name, mixed $default = null): mixed
    {
        return $this->config->get('customFields.' . $name, $default);
    }
}
