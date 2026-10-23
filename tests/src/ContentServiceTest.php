<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent;

use Derafu\Content\Abstract\AbstractContentItem;
use Derafu\Content\Abstract\AbstractContentPlugin;
use Derafu\Content\Abstract\AbstractContentRegistry;
use Derafu\Content\Abstract\AbstractPlugin;
use Derafu\Content\ContentBag;
use Derafu\Content\ContentConfig;
use Derafu\Content\ContentContext;
use Derafu\Content\ContentFilters;
use Derafu\Content\ContentLoader;
use Derafu\Content\ContentService;
use Derafu\Content\ContentSplFileInfo;
use Derafu\Content\Exception\PluginNotFoundException;
use Derafu\Content\Plugin\Blog\BlogPlugin;
use Derafu\Content\Plugin\Blog\BlogPost;
use Derafu\Content\Plugin\Blog\BlogRegistry;
use Derafu\Content\Plugin\Docs\DocsDoc;
use Derafu\Content\Plugin\Docs\DocsPlugin;
use Derafu\Content\Plugin\Docs\DocsRegistry;
use Derafu\TestsContent\Support\ContentFixtures;
use Derafu\TestsContent\Support\FixturePluginLoader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * The service gives the plugins of the website by name and the content of all
 * of them together.
 */
#[CoversClass(ContentService::class)]
#[UsesClass(AbstractContentItem::class)]
#[UsesClass(AbstractContentPlugin::class)]
#[UsesClass(AbstractContentRegistry::class)]
#[UsesClass(AbstractPlugin::class)]
#[UsesClass(BlogPlugin::class)]
#[UsesClass(BlogPost::class)]
#[UsesClass(BlogRegistry::class)]
#[UsesClass(ContentBag::class)]
#[UsesClass(ContentConfig::class)]
#[UsesClass(ContentContext::class)]
#[UsesClass(ContentFilters::class)]
#[UsesClass(ContentLoader::class)]
#[UsesClass(ContentSplFileInfo::class)]
#[UsesClass(DocsDoc::class)]
#[UsesClass(DocsPlugin::class)]
#[UsesClass(DocsRegistry::class)]
#[UsesClass(PluginNotFoundException::class)]
final class ContentServiceTest extends TestCase
{
    private function service(): ContentService
    {
        $context = ContentFixtures::contentContext();
        $plugins = [];
        foreach (['docs' => DocsPlugin::class, 'blog' => BlogPlugin::class] as $name => $class) {
            $plugins[$name] = new $class($context, ['path' => $name]);
            $plugins[$name]->loadContent(ContentFixtures::contentLoader());
        }

        return new ContentService($context, new FixturePluginLoader($plugins));
    }

    public function testItKeepsTheContextOfTheWebsite(): void
    {
        $context = ContentFixtures::contentContext('My Site');

        $this->assertSame($context, (new ContentService($context, new FixturePluginLoader([])))->context());
    }

    public function testItGivesThePluginsByName(): void
    {
        $service = $this->service();

        $this->assertSame(['docs', 'blog'], array_keys($service->plugins()));
        $this->assertInstanceOf(DocsPlugin::class, $service->plugin('docs'));
        $this->assertInstanceOf(BlogPlugin::class, $service->plugin('blog'));
    }

    public function testAPluginThatDoesNotExistSaysWhichOnesDo(): void
    {
        $exception = null;
        try {
            $this->service()->plugin('shop');
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(PluginNotFoundException::class, $exception);
        $this->assertSame('Plugin "shop" not found. Available plugins: docs, blog.', $exception->getMessage());
    }

    public function testAllTheContentIsTheOneOfEveryPluginTogether(): void
    {
        $service = $this->service();

        $types = array_unique(array_map(fn ($item) => $item->type(), $service->allContent()));
        sort($types);

        $this->assertSame(['blog', 'docs'], $types);
        $this->assertCount(
            count($service->allContent(['type' => 'docs'])) + count($service->allContent(['type' => 'blog'])),
            $service->allContent()
        );
    }

    public function testTheFiltersNarrowTheContentOfAllThePlugins(): void
    {
        $service = $this->service();

        $blog = $service->allContent(['type' => 'blog']);

        $this->assertNotEmpty($blog);
        foreach ($blog as $item) {
            $this->assertSame('blog', $item->type());
        }
    }

    public function testAFilterWithoutValueIsNotAFilter(): void
    {
        $service = $this->service();

        $this->assertSame(count($service->allContent()), count($service->allContent(['type' => ''])));
    }
}
