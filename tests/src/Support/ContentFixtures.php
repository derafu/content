<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent\Support;

use Derafu\Content\ContentConfig;
use Derafu\Content\ContentContext;
use Derafu\Content\ContentLoader;
use Derafu\Content\ContentService;
use Derafu\Content\Contract\PluginInterface;
use FilesystemIterator;
use Psr\Cache\CacheItemPoolInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Small helper to build real object graphs (never mocks) against the
 * fixture content shipped with the test suite.
 */
final class ContentFixtures
{
    /**
     * Absolute path to the root of the fixture content tree.
     *
     * @param string $sub Sub-path within tests/fixtures/content.
     * @return string
     */
    public static function contentPath(string $sub = ''): string
    {
        $base = dirname(__DIR__, 2) . '/fixtures/content';

        return $sub === '' ? $base : $base . '/' . trim($sub, '/');
    }

    /**
     * Absolute path to the root of the fixture templates tree.
     *
     * @param string $sub Sub-path within tests/fixtures/templates.
     * @return string
     */
    public static function templatesPath(string $sub = ''): string
    {
        $base = dirname(__DIR__, 2) . '/fixtures/templates';

        return $sub === '' ? $base : $base . '/' . trim($sub, '/');
    }

    /**
     * A real ContentLoader rooted at the fixture content directory.
     *
     * @return ContentLoader
     */
    public static function contentLoader(): ContentLoader
    {
        return new ContentLoader(self::contentPath());
    }

    /**
     * A real, minimal ContentContext, sufficient for plugin construction.
     *
     * Defaults to a fresh, in-memory ArrayAdapter instead of
     * ContentContext's own filesystem-backed default: tests must stay
     * isolated from each other and from the real disk, and many test
     * classes build a plugin/registry against the exact same fixture
     * path/include/exclude, which would otherwise all share one real cache
     * entry across supposedly-independent tests.
     *
     * @param string $title Title of the fixture website.
     * @param string $url URL of the fixture website.
     * @param CacheItemPoolInterface|null $cache Cache pool. Pass an
     * explicit shared instance to test cache hit/miss behavior across two
     * separate contexts/registries.
     * @return ContentContext
     */
    public static function contentContext(
        string $title = 'Fixture Website',
        string $url = 'http://localhost',
        ?CacheItemPoolInterface $cache = null
    ): ContentContext {
        return new ContentContext(new ContentConfig([
            'title' => $title,
            'url' => $url,
        ]), $cache ?? new ArrayAdapter());
    }

    /**
     * A real ContentService, backed by the given already-loaded plugins.
     *
     * @param array<string, PluginInterface> $plugins Plugins keyed by name.
     * @return ContentService
     */
    public static function contentService(array $plugins): ContentService
    {
        return new ContentService(
            self::contentContext(),
            new FixturePluginLoader($plugins)
        );
    }

    /**
     * Recursively remove a real directory tree, used to clean up
     * FilesystemAdapter cache directories created by tests.
     *
     * @param string $dir Absolute path of the directory to remove.
     */
    public static function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
