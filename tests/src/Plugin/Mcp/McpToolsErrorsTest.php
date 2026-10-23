<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent\Plugin\Mcp;

use Derafu\Content\Abstract\AbstractPlugin;
use Derafu\Content\ContentConfig;
use Derafu\Content\ContentContext;
use Derafu\Content\ContentService;
use Derafu\Content\Exception\PluginNotFoundException;
use Derafu\Content\Plugin\Mcp\Tool\AskTool;
use Derafu\Content\Plugin\Mcp\Tool\SearchContentTool;
use Derafu\Content\Plugin\Search\Exception\SearchUpstreamException;
use Derafu\Content\Plugin\Search\SearchEngine;
use Derafu\Content\Plugin\Search\SearchPlugin;
use Derafu\TestsContent\Support\ContentFixtures;
use Mcp\Exception\ToolCallException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * A tool that can not do what it was asked answers with an error that the
 * client of MCP reads, and says what is missing in the website.
 */
#[CoversClass(AskTool::class)]
#[CoversClass(SearchContentTool::class)]
#[UsesClass(AbstractPlugin::class)]
#[UsesClass(ContentConfig::class)]
#[UsesClass(ContentContext::class)]
#[UsesClass(ContentService::class)]
#[UsesClass(PluginNotFoundException::class)]
#[UsesClass(SearchEngine::class)]
#[UsesClass(SearchPlugin::class)]
#[UsesClass(SearchUpstreamException::class)]
final class McpToolsErrorsTest extends TestCase
{
    private function withoutSearch(): ContentService
    {
        return ContentFixtures::contentService([]);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function withSearch(array $options = []): ContentService
    {
        return ContentFixtures::contentService([
            'search' => new SearchPlugin(
                ContentFixtures::contentContext(),
                ['url' => 'http://127.0.0.1:19899/?q=%s', ...$options]
            ),
        ]);
    }

    private function failureOf(callable $call): Throwable
    {
        try {
            $call();
        } catch (Throwable $e) {
            return $e;
        }

        $this->fail('The tool answered.');
    }

    public function testAskWithoutSearchPluginSaysThatTheSearchIsNotConfigured(): void
    {
        $exception = $this->failureOf(fn () => (new AskTool($this->withoutSearch()))('What is this?'));

        $this->assertInstanceOf(ToolCallException::class, $exception);
        $this->assertSame('The search engine is not configured for this site.', $exception->getMessage());
    }

    public function testAskWithoutLlmSaysThatNoBackendIsConfigured(): void
    {
        $exception = $this->failureOf(fn () => (new AskTool($this->withSearch()))('What is this?'));

        $this->assertInstanceOf(ToolCallException::class, $exception);
        $this->assertSame('The "ask" tool is enabled but no LLM backend is configured.', $exception->getMessage());
    }

    public function testSearchWithoutSearchPluginSaysThatTheSearchIsNotConfigured(): void
    {
        $exception = $this->failureOf(fn () => (new SearchContentTool($this->withoutSearch()))('hello'));

        $this->assertInstanceOf(ToolCallException::class, $exception);
        $this->assertSame('The search engine is not configured for this site.', $exception->getMessage());
    }

    public function testSearchWithAnEngineThatIsDownSaysSo(): void
    {
        $exception = $this->failureOf(fn () => (new SearchContentTool($this->withSearch()))('hello'));

        $this->assertInstanceOf(ToolCallException::class, $exception);
        $this->assertStringStartsWith('The search engine request failed: ', $exception->getMessage());
        $this->assertInstanceOf(SearchUpstreamException::class, $exception->getPrevious());
    }
}
