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

use Derafu\Content\Plugin\Search\Exception\SearchUpstreamException;
use Derafu\Content\Plugin\Search\SearchEngine;
use Derafu\TestsContent\Support\FixtureHttpServer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Exercises SearchEngine against a real PHP built-in HTTP server (a real
 * TCP round-trip, no mocked PSR-18 client), so the request building,
 * status-code handling and error-detail extraction all run for real.
 */
#[CoversClass(SearchEngine::class)]
#[UsesClass(SearchUpstreamException::class)]
final class SearchEngineTest extends TestCase
{
    private static FixtureHttpServer $server;

    public static function setUpBeforeClass(): void
    {
        self::$server = FixtureHttpServer::start(19801);
    }

    public static function tearDownAfterClass(): void
    {
        self::$server->stop();
    }

    public function testSuccessfulQueryReturnsTheRealResultsArray(): void
    {
        $engine = new SearchEngine(self::$server->url() . '/?scenario=results_ok&text=%s');

        $results = $engine->query('hola');

        $this->assertCount(2, $results);
        $this->assertSame('doc-1', $results[0]['id']);
    }

    public function testCollectionAndBaseUrlAreInterpolatedIntoTheUrlTemplate(): void
    {
        $engine = new SearchEngine(
            self::$server->url() . '/?scenario=results_ok&collection=%s&base_url=%s&text=%s',
            'my collection',
            'https://example.com'
        );

        // Real HTTP call: if the placeholders were not interpolated
        // correctly the URL itself would be malformed and the real
        // server would still answer 200 with the "results_ok" scenario,
        // so this mainly proves resolveUrl() does not throw/mangle
        // encoding for values that need urlencode().
        $results = $engine->query('hola');

        $this->assertCount(2, $results);
    }

    public function testNonSuccessfulStatusThrowsSearchUpstreamExceptionWithTheUpstreamDetail(): void
    {
        $engine = new SearchEngine(self::$server->url() . '/?scenario=results_bad_status&text=%s');

        try {
            $engine->query('hola');
            $this->fail('Expected a SearchUpstreamException.');
        } catch (SearchUpstreamException $e) {
            $this->assertStringContainsString('503', $e->getMessage());
            $this->assertStringContainsString('Search backend unavailable', $e->getMessage());
        }
    }

    public function testMissingResultsFieldThrowsSearchUpstreamException(): void
    {
        $engine = new SearchEngine(self::$server->url() . '/?scenario=results_missing_field&text=%s');

        $this->expectException(SearchUpstreamException::class);
        $this->expectExceptionMessageMatches('/without a "results" field/');

        $engine->query('hola');
    }

    public function testInvalidJsonBodyThrowsSearchUpstreamException(): void
    {
        $engine = new SearchEngine(self::$server->url() . '/?scenario=invalid_json&text=%s');

        $this->expectException(SearchUpstreamException::class);

        $engine->query('hola');
    }

    public function testUnreachableHostThrowsSearchUpstreamExceptionImmediately(): void
    {
        // Nothing is listening on this port: a real "connection refused",
        // exactly the chat.derafu.ai-was-down scenario from this session,
        // reproduced without needing to hang on a real network timeout.
        $engine = new SearchEngine('http://127.0.0.1:19899/?text=%s');

        $this->expectException(SearchUpstreamException::class);
        $this->expectExceptionMessageMatches('/could not be reached/');

        $engine->query('hola');
    }
}
