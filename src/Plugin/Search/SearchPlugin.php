<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Plugin\Search;

use Derafu\Content\Abstract\AbstractPlugin;
use Derafu\Content\Contract\PluginInterface;
use Derafu\Content\Plugin\Search\Contract\LlmClientInterface;

/**
 * Plugin that provides a middleware for the search engine of the content
 *
 * This provides a template and an API to search the content.
 */
class SearchPlugin extends AbstractPlugin implements PluginInterface
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
            'default' => 'search',
        ],

        // URL of the search engine.
        'url' => [
            'types' => 'string',
            'required' => true,
        ],

        // Collection of the search.
        'collection' => [
            'types' => 'string',
            'required' => false,
        ],

        // Base URL of the search.
        'base_url' => [
            'types' => 'string',
            'required' => false,
        ],

        // LLM configuration.
        'llm_url' => [
            'types' => 'string',
            'required' => false,
        ],

        'llm_model' => [
            'types' => 'string',
            'required' => false,
            'default' => 'libredte',
        ],

        'llm_api_key' => [
            'types' => ['string', 'null'],
            'required' => false,
        ],

        // Path of the chat completions endpoint, appended to "llm_url".
        //
        // Defaults to the standard path used by OpenAI, OpenRouter and most
        // OpenAI-compatible providers. Self-hosted Open WebUI instances use
        // "/api/chat/completions" instead and need to override this.
        'llm_completions_path' => [
            'types' => 'string',
            'required' => false,
            'default' => '/v1/chat/completions',
        ],
    ];

    /**
     * Engine for the search.
     *
     * @var SearchEngine
     */
    private SearchEngine $engine;

    /**
     * LLM client for AI responses.
     *
     * @var LlmClientInterface
     */
    private LlmClientInterface $llm;

    /**
     * Get the engine for the search.
     *
     * @return SearchEngine
     */
    public function engine(): SearchEngine
    {
        if (!isset($this->engine)) {
            $this->engine = new SearchEngine(
                $this->options['url'],
                $this->options['collection'],
                $this->options['base_url']
            );
        }

        return $this->engine;
    }

    /**
     * Get the LLM client for AI responses.
     *
     * @return LlmClientInterface|null
     */
    public function llm(): ?LlmClientInterface
    {
        if (!isset($this->llm) && isset($this->options['llm_url'])) {
            $this->llm = new OpenAiCompatibleLlmClient(
                $this->options['llm_url'],
                $this->options['llm_model'] ?? 'libredte',
                $this->options['llm_api_key'] ?? null,
                $this->options['llm_completions_path'] ?? '/v1/chat/completions'
            );
        }

        return $this->llm ?? null;
    }

    /**
     * {@inheritDoc}
     */
    protected static function getSchema(): array
    {
        return self::OPTIONS_SCHEMA;
    }
}
