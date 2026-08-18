<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent\Routing;

use Derafu\Routing\Exception\RouteNotFoundException;
use Derafu\TestsContent\Support\RouterFixture;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Validates, with a real Router and real parsers (no mocks), the routing
 * precedence design decided for the Pages plugin: shipping "pages_page"
 * under a "/pages" prefix (see resources/config/content-routes.yaml) so it
 * never shadows a website's hand-authored, file-based flat pages
 * (Derafu\Routing\Parser\FileSystemParser) — and, conversely, that an
 * unprefixed catch-all *would* shadow them, which is exactly the
 * documented trade-off of opting into that alternative.
 *
 * This test covers no class of this package: it validates the interaction
 * between this package's own routing configuration (a resource file) and
 * the router matching engine, not any PHP class defined here.
 */
#[CoversNothing]
final class PagesRoutingPrecedenceTest extends TestCase
{
    public function testPrefixedPagesRouteDoesNotShadowAFileBasedFlatPage(): void
    {
        // No hand-written "/legal" route registered: only this package's
        // own content-routes.yaml (with the prefixed "pages_page") plus the
        // file-based "legal.html.twig" fixture.
        $router = RouterFixture::create();

        $match = $router->match('/legal');

        $this->assertStringEndsWith(
            'templates/pages/legal.html.twig',
            (string) $match->getHandler()
        );
    }

    public function testPrefixedPagesRouteStillResolvesUnderItsOwnPrefix(): void
    {
        $router = RouterFixture::create();

        $match = $router->match('/pages/about');

        $this->assertSame('pages_page', $match->getName());
        $this->assertSame(['page' => 'about'], $match->getParameters());
    }

    public function testHandWrittenStaticRoutesAreNeverShadowedRegardlessOfPagesConfiguration(): void
    {
        // Simulates a website's own hand-written route (e.g. LibreDTE's
        // "/legal"), registered alongside this package's content routes.
        $router = RouterFixture::create([
            'legal' => [
                'path' => '/legal',
                'handler' => 'App\\Controller\\LegalController::index',
            ],
        ]);

        $match = $router->match('/legal');

        $this->assertSame('legal', $match->getName());
        $this->assertSame('App\\Controller\\LegalController::index', $match->getHandler());
    }

    /**
     * The documented trade-off: a website that opts into an *unprefixed*
     * Pages route (see the Pages plugin documentation) turns off
     * FileSystemParser's auto-discovery for every URI that pattern
     * matches, because DynamicParser runs before FileSystemParser and a
     * catch-all matches everything.
     */
    public function testUnprefixedPagesRouteWouldShadowTheSameFileBasedFlatPage(): void
    {
        $router = RouterFixture::create([
            'pages_page_unprefixed' => [
                'path' => '/{page:.+}',
                'handler' => 'Derafu\\Content\\Plugin\\Pages\\PagesController::show',
            ],
        ], includeContentRoutes: false);

        $match = $router->match('/legal');

        $this->assertSame('pages_page_unprefixed', $match->getName());
        $this->assertStringNotContainsString('legal.html.twig', (string) $match->getHandler());
    }

    public function testRealContentDocRouteResolvesThroughDynamicParser(): void
    {
        $router = RouterFixture::create();

        $match = $router->match('/docs/guia/primeros-pasos');

        $this->assertSame('docs_doc', $match->getName());
        $this->assertSame(['doc' => 'guia/primeros-pasos'], $match->getParameters());
    }

    public function testUnmatchedUriThrowsRouteNotFoundException(): void
    {
        $router = RouterFixture::create();

        $this->expectException(RouteNotFoundException::class);

        $router->match('/this/does/not/match/anything');
    }
}
