<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent\Abstract;

use Derafu\Content\Abstract\AbstractContentPlugin;
use Derafu\Content\Abstract\AbstractPlugin;
use Derafu\Content\ContentBag;
use Derafu\Content\ContentConfig;
use Derafu\Content\ContentContext;
use Derafu\Content\ContentHtmlTags;
use Derafu\Content\Plugin\Api\ApiPlugin;
use Derafu\Content\Plugin\Docs\DocsPlugin;
use Derafu\Content\Plugin\Search\SearchPlugin;
use Derafu\TestsContent\Support\ContentFixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

/**
 * What every plugin has: its name, its context, its options validated against
 * its schema and the places where the website has to look for changes.
 */
#[CoversClass(AbstractPlugin::class)]
#[CoversClass(AbstractContentPlugin::class)]
#[UsesClass(ContentBag::class)]
#[UsesClass(ContentConfig::class)]
#[UsesClass(ContentContext::class)]
#[UsesClass(ContentHtmlTags::class)]
#[UsesClass(ApiPlugin::class)]
#[UsesClass(DocsPlugin::class)]
#[UsesClass(SearchPlugin::class)]
final class AbstractPluginTest extends TestCase
{
    public function testThePluginHasItsNameItsContextAndItsOptions(): void
    {
        $context = ContentFixtures::contentContext();
        $plugin = new DocsPlugin($context, ['path' => 'manual']);

        $this->assertSame('docs', $plugin->name());
        $this->assertSame($context, $plugin->context());
        $this->assertSame('manual', $plugin->options()->requireString('path'));
        $this->assertSame('handbook', (new DocsPlugin($context, ['name' => 'handbook']))->name());
    }

    public function testThePluginsWithContentWatchTheirPath(): void
    {
        $plugin = new DocsPlugin(ContentFixtures::contentContext(), ['path' => 'resources/manual']);

        $this->assertSame(['resources/manual'], $plugin->getPathsToWatch());
        $this->assertSame([], $plugin->getTranslationFiles());
    }

    public function testTheTagsOfTheHtmlStartEmpty(): void
    {
        $plugin = new DocsPlugin(ContentFixtures::contentContext(), ['path' => 'docs']);

        $tags = $plugin->injectHtmlTags(new ContentBag([]));

        $this->assertSame('', $tags->headTagsToHtml());
        $this->assertSame('', $tags->preBodyTagsToHtml());
        $this->assertSame('', $tags->postBodyTagsToHtml());
    }

    public function testTheOptionsCanBeValidatedWithoutCreatingThePlugin(): void
    {
        DocsPlugin::validateOptions(['path' => 'docs', 'sidebarCollapsed' => true]);

        $this->addToAssertionCount(1);
    }

    public function testAnOptionWithAnotherTypeIsNotValid(): void
    {
        $this->expectException(InvalidOptionsException::class);

        DocsPlugin::validateOptions(['path' => 5]);
    }

    public function testThePluginsWithoutSchemaAcceptAnyOption(): void
    {
        $plugin = new ApiPlugin(ContentFixtures::contentContext(), ['anything' => 'goes', 'name' => 'export']);

        $this->assertSame('export', $plugin->name());
        $this->assertSame('goes', $plugin->options()->get('anything'));

        ApiPlugin::validateOptions(['anything' => 'goes']);
        $this->addToAssertionCount(1);
    }
}
