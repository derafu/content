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

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * The endpoints of the package that are not content live under `/api/content`.
 *
 * A path like `/api/mcp` or `/api/search.json` belongs to nobody: the website
 * that adds an MCP server of its own (one that works over its database) or a
 * search of its own would have to fight the package for it. Under
 * `/api/content` the paths are the package's, and the rest of `/api/` is left
 * to the website.
 *
 * The content itself is not served under `/api/`: each item is served by its
 * extension (`.html`, `.md`, `.json`...) under the path of its plugin. And the
 * pages for a person (`/search`) stay where they are.
 */
#[CoversNothing]
final class ApiRoutesTest extends TestCase
{
    /**
     * @return array<string, array{path: string, handler: string}>
     */
    private function routes(): array
    {
        /** @var array<string, array{path: string, handler: string}> */
        return Yaml::parseFile(dirname(__DIR__, 3) . '/resources/config/content-routes.yaml');
    }

    public function testTheEndpointsOfThePackageAreUnderApiContent(): void
    {
        $paths = array_map(fn (array $route): string => $route['path'], $this->routes());

        $this->assertSame('/api/content.json', $paths['content_api']);
        $this->assertSame('/api/content/mcp', $paths['content_mcp']);
        $this->assertSame('/api/content/search.json', $paths['content_search_api']);
        $this->assertSame('/api/content/search/llm.json', $paths['content_search_llm_query']);
    }

    public function testTheRoutesUnderApiContentAreNamedWithContent(): void
    {
        $wrong = [];
        foreach ($this->routes() as $name => $route) {
            if (str_starts_with($route['path'], '/api/content') && !str_starts_with($name, 'content_')) {
                $wrong[] = $name;
            }
        }

        $this->assertSame([], $wrong, 'The routes under /api/content are named content_*.');
    }

    public function testThePageOfTheSearchStaysWhereItIs(): void
    {
        $this->assertSame('/search', $this->routes()['search']['path']);
    }

    public function testNoRouteTakesAGenericPathUnderApi(): void
    {
        $generic = [];
        foreach ($this->routes() as $name => $route) {
            $path = $route['path'];
            if (
                str_starts_with($path, '/api/')
                && $path !== '/api/content.json'
                && !str_starts_with($path, '/api/content/')
            ) {
                $generic[$name] = $path;
            }
        }

        $this->assertSame([], $generic, 'These routes take a path under /api/ that is not the package\'s.');
    }
}
