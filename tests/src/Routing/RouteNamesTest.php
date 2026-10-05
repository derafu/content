<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent\Routing;

use Derafu\TestsContent\Support\RendererFixture;
use Derafu\TestsContent\Support\RouterFixture;
use Derafu\Twig\Lint\RouteReferenceScanner;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Yaml\Yaml;

/**
 * Every route name this package refers to must be defined by this package.
 *
 * A template that links to a route which does not exist only fails when that
 * link is rendered, which can be never for years (it happened with a
 * breadcrumb inside a loop that never ran). And a name that only the website
 * using the package defines (like `homepage`) is a hidden requirement on every
 * website. This reads the sources, without rendering anything, and fails on
 * any name that the router of the package does not have.
 *
 * Nor may an internal path be written by hand (`href="/docs"`, `'/blog'`):
 * it repeats what the routes already say, and breaks without a warning when a
 * route changes, when the website is mounted under a path, or when a prefix
 * such as the language is added. The route has to be asked for its URL.
 * Paths of static files (`/img/...`, `/css/...`) are not routes and are fine.
 *
 * The templates are read with the parser of Twig (`RouteReferenceScanner`),
 * with the same environment that renders them: a template that can not be
 * parsed makes the test fail instead of being skipped.
 *
 * LIMITATION: it only checks names written as a string literal, in the Twig
 * functions `path()`, `url()` and `is_active_path()` and in the PHP calls to
 * `->generate()`. A name built at runtime can not be checked by reading, for
 * example `path(content.route.name, ...)` in `_downloads.html.twig` and
 * `url(item.route().name, ...)` in `sitemap.xml.twig`, where the name comes
 * from `AbstractContentItem::route()` (`{type}_{category}`). The scanner does
 * find those calls (it reports them as dynamic), but there is nothing to check.
 */
#[CoversNothing]
final class RouteNamesTest extends TestCase
{
    public function testEveryRouteNameUsedInTemplatesIsDefined(): void
    {
        $scanner = new RouteReferenceScanner(RendererFixture::twig(RouterFixture::create()));

        $used = [];
        foreach ($scanner->scanDirectory($this->packagePath('resources/templates')) as $reference) {
            if (!$reference->isDynamic()) {
                $used[$reference->name][$reference->template] = $reference->template;
            }
        }

        $this->assertNotEmpty($used, 'No route name was found in the templates.');
        $this->assertUndefinedRoutes(array_map('array_values', $used));
    }

    public function testEveryRouteNameUsedInPhpSourcesIsDefined(): void
    {
        $used = $this->namesUsedIn(
            'src',
            'php',
            '/->generate\(\s*([\'"])([A-Za-z0-9_.\-]+)\1/',
            ['#/\*.*?\*/#s', '#^\s*//.*$#m']
        );

        $this->assertUndefinedRoutes($used);
    }

    /**
     * Prefixes of paths of static files, which no route serves.
     */
    private const STATIC_FILES = ['/img/', '/css/', '/js/', '/static/', '/fonts/', '/favicon'];

    public function testNoInternalPathIsWrittenByHandInTemplates(): void
    {
        $used = $this->namesUsedIn(
            'resources/templates',
            'twig',
            '/\b(?:href|src|action|formaction|poster)\s*=\s*([\'"])(\/(?!\/)[^\'"{]*)\1/',
            ['/\{#.*?#\}/s']
        );

        $this->assertNoHandWrittenPath($used);
    }

    /**
     * The sections of the routes (`/docs`, `/blog`, ...) are what a path
     * written by hand in PHP would repeat, so they are read from the routes.
     */
    public function testNoInternalPathIsWrittenByHandInPhpSources(): void
    {
        $sections = [];
        foreach (Yaml::parseFile($this->packagePath('resources/config/content-routes.yaml')) as $route) {
            $segment = explode('/', ltrim($route['path'], '/'))[0];
            if ($segment !== '' && !str_contains($segment, '{')) {
                $sections[$segment] = preg_quote($segment, '/');
            }
        }

        $this->assertNotEmpty($sections, 'No section was found in the routes.');

        $used = $this->namesUsedIn(
            'src',
            'php',
            '/([\'"])(\/(?:' . implode('|', $sections) . ')(?:[\/?#.][^\'"]*)?)\1/',
            ['#/\*.*?\*/#s', '#^\s*//.*$#m']
        );

        $this->assertNoHandWrittenPath($used);
    }

    /**
     * @param array<string, array<string>> $used Files by path.
     */
    private function assertNoHandWrittenPath(array $used): void
    {
        $byHand = [];
        foreach ($used as $path => $files) {
            if (!array_filter(self::STATIC_FILES, fn (string $prefix) => str_starts_with($path, $prefix))) {
                $byHand[] = sprintf('%s (in %s)', $path, implode(', ', $files));
            }
        }

        $this->assertSame(
            [],
            $byHand,
            'Internal paths written by hand, use the router: ' . implode('; ', $byHand)
        );
    }

    /**
     * @param array<string, array<string>> $used Files by route name.
     */
    private function assertUndefinedRoutes(array $used): void
    {
        $router = RouterFixture::create();

        $undefined = [];
        foreach ($used as $name => $files) {
            if (!$router->has($name)) {
                $undefined[] = sprintf('%s (in %s)', $name, implode(', ', $files));
            }
        }

        $this->assertSame(
            [],
            $undefined,
            'Route names not defined by the routes of the package: ' . implode('; ', $undefined)
        );
    }

    /**
     * Finds the route names used in the files of a directory.
     *
     * @param string $directory Directory relative to the root of the package.
     * @param string $extension Extension of the files to read.
     * @param string $pattern Regular expression that captures the name in the
     * second group.
     * @param array<string> $comments Regular expressions of the comments to
     * remove before searching, so a name inside one is not counted. They are
     * applied one by one: joining them would let a modifier meant for one
     * (like `s` for block comments) change what another one removes.
     * @return array<string, array<string>> Relative files by route name.
     */
    private function namesUsedIn(
        string $directory,
        string $extension,
        string $pattern,
        array $comments
    ): array {
        $root = $this->packagePath($directory);
        $used = [];

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->getExtension() !== $extension) {
                continue;
            }

            $code = preg_replace($comments, '', (string) file_get_contents($file->getPathname()));
            preg_match_all($pattern, (string) $code, $matches);

            foreach ($matches[2] as $name) {
                $relative = substr($file->getPathname(), strlen($root) + 1);
                $used[$name][$relative] = $relative;
            }
        }

        return array_map('array_values', $used);
    }

    private function packagePath(string $path): string
    {
        return dirname(__DIR__, 3) . '/' . $path;
    }
}
