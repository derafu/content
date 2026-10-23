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
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

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
        // exactly the scenario of an engine that is down, reproduced without
        // needing to hang on a real network timeout.
        $engine = new SearchEngine('http://127.0.0.1:19899/?text=%s');

        $this->expectException(SearchUpstreamException::class);
        $this->expectExceptionMessageMatches('/could not be reached/');

        $engine->query('hola');
    }

    public function testResultsThatAreNotAListOfObjectsThrowSearchUpstreamException(): void
    {
        foreach (['results_not_list' => 'results', 'results_item_not_object' => 'results.1'] as $scenario => $key) {
            $engine = new SearchEngine(self::$server->url() . '/?scenario=' . $scenario . '&text=%s');

            try {
                $engine->query('hola');
                $this->fail('Expected a SearchUpstreamException.');
            } catch (SearchUpstreamException $e) {
                $this->assertStringContainsString('returned an invalid "results" field: The key "' . $key . '" must be of type', $e->getMessage());
            }
        }
    }

    public function testAnErrorStatusWithAMessageShowsIt(): void
    {
        $engine = new SearchEngine(self::$server->url() . '/?scenario=results_bad_status_message&text=%s');

        try {
            $engine->query('hola');
            $this->fail('Expected a SearchUpstreamException.');
        } catch (SearchUpstreamException $e) {
            $this->assertStringEndsWith('returned HTTP 500: Index is rebuilding.', $e->getMessage());
        }
    }

    public function testAnErrorStatusWithoutAKnownShapeHasNoDetail(): void
    {
        $engine = new SearchEngine(self::$server->url() . '/?scenario=results_bad_status_unknown&text=%s');

        try {
            $engine->query('hola');
            $this->fail('Expected a SearchUpstreamException.');
        } catch (SearchUpstreamException $e) {
            $this->assertStringEndsWith('returned HTTP 502.', $e->getMessage());
        }
    }

    public function testAnErrorStatusWithABodyThatIsNotJsonHasNoDetail(): void
    {
        $engine = new SearchEngine(self::$server->url() . '/?scenario=results_bad_status_text&text=%s');

        try {
            $engine->query('hola');
            $this->fail('Expected a SearchUpstreamException.');
        } catch (SearchUpstreamException $e) {
            $this->assertStringEndsWith('returned HTTP 502.', $e->getMessage());
        }
    }

    /**
     * @return array<string, array{string, string|null, string|null, string}>
     */
    public static function provideTheUrlsOfTheEngine(): array
    {
        return [
            'only the query' => [
                'https://search.test/?q=%s',
                null,
                null,
                'https://search.test/?q=hello+world',
            ],
            'the collection and the query' => [
                'https://search.test/%s/?q=%s',
                'my docs',
                null,
                'https://search.test/my+docs/?q=hello+world',
            ],
            'the collection, the base URL and the query' => [
                'https://search.test/%s/%s/?q=%s',
                'docs',
                'https://example.com',
                'https://search.test/docs/https%3A%2F%2Fexample.com/?q=hello+world',
            ],
            'a base URL without collection is not used' => [
                'https://search.test/?q=%s',
                null,
                'https://example.com',
                'https://search.test/?q=hello+world',
            ],
        ];
    }

    #[DataProvider('provideTheUrlsOfTheEngine')]
    public function testTheTemplateTakesTheCollectionTheBaseUrlAndTheQueryThatItHas(
        string $template,
        ?string $collection,
        ?string $baseUrl,
        string $expected
    ): void {
        $client = new class () implements ClientInterface {
            public string $requested = '';

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->requested = (string) $request->getUri();

                return new Response(200, [], '{"results": []}');
            }
        };

        (new SearchEngine($template, $collection, $baseUrl, $client))->query('hello world');

        $this->assertSame($expected, $client->requested);
    }
}
