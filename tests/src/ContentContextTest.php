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

use Derafu\Content\ContentConfig;
use Derafu\Content\ContentContext;
use Derafu\TestsContent\Support\ContentFixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

/**
 * ContentContext's cache pool defaults to a real, working filesystem
 * cache when none is injected, so a consuming app gets caching for free
 * without configuring anything.
 */
#[CoversClass(ContentContext::class)]
#[UsesClass(ContentConfig::class)]
final class ContentContextTest extends TestCase
{
    public function testCacheDefaultsToARealUsableFilesystemAdapter(): void
    {
        $context = new ContentContext(new ContentConfig([
            'title' => 'Fixture',
            'url' => 'http://localhost',
        ]));

        $this->assertInstanceOf(FilesystemAdapter::class, $context->cache());

        // Prove it is actually usable end to end, not just the right class.
        $key = 'derafu_content_context_test';
        $item = $context->cache()->getItem($key);
        $item->set(['ok' => true]);
        $context->cache()->save($item);

        $this->assertTrue($context->cache()->getItem($key)->get()['ok']);

        $context->cache()->deleteItem($key);

        // This default writes to the real system temp dir (there is no way
        // to exercise ContentContext's own default otherwise), so clean up
        // the directory it creates instead of leaving it behind.
        ContentFixtures::removeDirectory(sys_get_temp_dir() . '/derafu_content');
    }

    public function testAnInjectedCachePoolIsUsedInstead(): void
    {
        $cache = new ArrayAdapter();

        $context = new ContentContext(new ContentConfig([
            'title' => 'Fixture',
            'url' => 'http://localhost',
        ]), $cache);

        $this->assertSame($cache, $context->cache());
    }
}
