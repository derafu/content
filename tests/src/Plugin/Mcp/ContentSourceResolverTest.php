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

use Derafu\Content\Abstract\AbstractContentPlugin;
use Derafu\Content\Abstract\AbstractPlugin;
use Derafu\Content\ContentBag;
use Derafu\Content\ContentConfig;
use Derafu\Content\ContentContext;
use Derafu\Content\ContentService;
use Derafu\Content\Exception\PluginNotFoundException;
use Derafu\Content\Plugin\Docs\DocsPlugin;
use Derafu\Content\Plugin\Mcp\Tool\ContentSourceResolver;
use Derafu\Content\Plugin\Search\SearchPlugin;
use Derafu\TestsContent\Support\ContentFixtures;
use Mcp\Exception\ToolCallException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * A tool names a source of content: the name has to be one of a plugin that
 * has content, and what it is not is an error that the client of MCP reads.
 */
#[CoversClass(ContentSourceResolver::class)]
#[UsesClass(AbstractContentPlugin::class)]
#[UsesClass(AbstractPlugin::class)]
#[UsesClass(ContentBag::class)]
#[UsesClass(ContentConfig::class)]
#[UsesClass(ContentContext::class)]
#[UsesClass(ContentService::class)]
#[UsesClass(DocsPlugin::class)]
#[UsesClass(PluginNotFoundException::class)]
#[UsesClass(SearchPlugin::class)]
final class ContentSourceResolverTest extends TestCase
{
    private function service(): ContentService
    {
        return ContentFixtures::contentService([
            'docs' => new DocsPlugin(ContentFixtures::contentContext(), ['path' => 'docs']),
            'search' => new SearchPlugin(ContentFixtures::contentContext(), ['url' => 'http://localhost/?q=%s']),
        ]);
    }

    public function testASourceWithContentIsItsPlugin(): void
    {
        $this->assertInstanceOf(DocsPlugin::class, ContentSourceResolver::resolve($this->service(), 'docs'));
    }

    public function testASourceThatDoesNotExistIsAnErrorForTheClient(): void
    {
        $exception = null;
        try {
            ContentSourceResolver::resolve($this->service(), 'shop');
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(ToolCallException::class, $exception);
        $this->assertSame('Unknown content source "shop".', $exception->getMessage());
    }

    public function testAPluginWithoutContentIsNotASource(): void
    {
        $exception = null;
        try {
            ContentSourceResolver::resolve($this->service(), 'search');
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(ToolCallException::class, $exception);
        $this->assertSame('"search" is not a content source.', $exception->getMessage());
    }
}
