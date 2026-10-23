<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent\Plugin\Search;

use Derafu\Content\Abstract\AbstractPlugin;
use Derafu\Content\ContentConfig;
use Derafu\Content\ContentContext;
use Derafu\Content\Plugin\Search\OpenAiCompatibleLlmClient;
use Derafu\Content\Plugin\Search\SearchEngine;
use Derafu\Content\Plugin\Search\SearchPlugin;
use Derafu\TestsContent\Support\ContentFixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * The plugin builds the client of the search engine and, if the website has
 * one configured, the client of the LLM, and gives always the same ones.
 */
#[CoversClass(SearchPlugin::class)]
#[UsesClass(AbstractPlugin::class)]
#[UsesClass(ContentConfig::class)]
#[UsesClass(ContentContext::class)]
#[UsesClass(OpenAiCompatibleLlmClient::class)]
#[UsesClass(SearchEngine::class)]
final class SearchPluginTest extends TestCase
{
    /**
     * @param array<string, mixed> $options
     */
    private function plugin(array $options = []): SearchPlugin
    {
        return new SearchPlugin(
            ContentFixtures::contentContext(),
            ['url' => 'http://localhost/?q=%s', ...$options]
        );
    }

    public function testTheEngineIsBuiltOnceFromTheUrl(): void
    {
        $plugin = $this->plugin(['collection' => 'docs', 'base_url' => 'https://example.com']);

        $this->assertInstanceOf(SearchEngine::class, $plugin->engine());
        $this->assertSame($plugin->engine(), $plugin->engine());
    }

    public function testWithoutLlmUrlThereIsNoLlm(): void
    {
        $this->assertNull($this->plugin()->llm());
    }

    public function testTheLlmIsBuiltOnceFromItsOptions(): void
    {
        $plugin = $this->plugin([
            'llm_url' => 'http://localhost:11434',
            'llm_model' => 'a-model',
            'llm_api_key' => 'secret',
            'llm_completions_path' => '/api/chat/completions',
        ]);

        $this->assertInstanceOf(OpenAiCompatibleLlmClient::class, $plugin->llm());
        $this->assertSame($plugin->llm(), $plugin->llm());
    }
}
