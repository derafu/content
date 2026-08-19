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

use Derafu\Content\RemoteContentFetcher;
use Derafu\TestsContent\Support\FixtureHttpServer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Exercises RemoteContentFetcher against a real PHP built-in HTTP server
 * (a real TCP round-trip, no mocked PSR-18 client), the same approach
 * already used for SearchEngine/OpenAiCompatibleLlmClient.
 */
#[CoversClass(RemoteContentFetcher::class)]
final class RemoteContentFetcherTest extends TestCase
{
    private static FixtureHttpServer $server;

    public static function setUpBeforeClass(): void
    {
        self::$server = FixtureHttpServer::start(19802);
    }

    public static function tearDownAfterClass(): void
    {
        self::$server->stop();
    }

    public function testSuccessfulFetchReturnsTheRealResponseBody(): void
    {
        $fetcher = new RemoteContentFetcher();

        $body = $fetcher->fetch(self::$server->url() . '/?scenario=academy_test_ok');

        $this->assertStringContainsString('Cuestionario remoto', $body);
    }

    public function testUnreachableHostThrows(): void
    {
        $fetcher = new RemoteContentFetcher();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/could not be fetched/');

        // Nothing is listening on this port, same "connection refused"
        // approach already used by SearchEngineTest.
        $fetcher->fetch('http://127.0.0.1:19899/');
    }

    public function testErrorStatusThrows(): void
    {
        $fetcher = new RemoteContentFetcher();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/responded with status 503/');

        $fetcher->fetch(self::$server->url() . '/?scenario=results_bad_status');
    }
}
