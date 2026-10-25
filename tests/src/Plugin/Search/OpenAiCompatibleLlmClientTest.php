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
use Derafu\Content\Plugin\Search\OpenAiCompatibleLlmClient;
use Derafu\TestsContent\Support\FixtureHttpServer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Exercises OpenAiCompatibleLlmClient against a real PHP built-in HTTP
 * server (a real TCP round-trip, no mocked PSR-18 client). Every error
 * scenario here reproduces, over a real socket, one of the response shapes
 * of the backends: Open WebUI's {"detail": "Model not found"} and the
 * OpenAI-style {"error": {...}} / {"error": "..."} shapes used by
 * OpenRouter and friends.
 */
#[CoversClass(OpenAiCompatibleLlmClient::class)]
#[UsesClass(SearchUpstreamException::class)]
final class OpenAiCompatibleLlmClientTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function invalidResponseMaps(): iterable
    {
        yield 'choice is a positional list' => ['chat_choice_list', 'choices.0'];
        yield 'choice has a numeric field' => ['chat_choice_numeric_key', 'choices.0'];
        yield 'message is a positional list' => ['chat_message_list', 'choices.0.message'];
        yield 'message has a numeric field' => ['chat_message_numeric_key', 'choices.0.message'];
    }

    #[DataProvider('invalidResponseMaps')]
    public function testChoicesAndMessagesMustHaveStringKeys(string $scenario, string $path): void
    {
        $this->expectException(SearchUpstreamException::class);
        $this->expectExceptionMessage('The key "' . $path . '" must be of type map, array given.');

        $this->client($scenario)->query('guide');
    }

    private static FixtureHttpServer $server;

    public static function setUpBeforeClass(): void
    {
        self::$server = FixtureHttpServer::start(19802);
    }

    public static function tearDownAfterClass(): void
    {
        self::$server->stop();
    }

    /**
     * The client concatenates base URL + completions path itself
     * (rtrim($url, '/') . $completionsPath), so the "scenario" selector
     * has to travel inside $completionsPath's query string, not the base
     * URL, or it would end up in the middle of the final URL instead of
     * at the end.
     */
    private function client(
        string $scenario,
        string $completionsPath = '/v1/chat/completions'
    ): OpenAiCompatibleLlmClient {
        return new OpenAiCompatibleLlmClient(
            self::$server->url(),
            'test-model',
            'test-api-key',
            $completionsPath . '?scenario=' . $scenario
        );
    }

    public function testSuccessfulQueryReturnsTheAnswerContent(): void
    {
        $client = $this->client('chat_ok');

        $answer = $client->query('How do I cancel an invoice?');

        $this->assertSame('Hello, this is a test answer.', $answer);
    }

    public function testOpenWebUiDetailErrorShapeIsExtractedVerbatim(): void
    {
        $client = $this->client('chat_error_detail');

        try {
            $client->query('hola');
            $this->fail('Expected a SearchUpstreamException.');
        } catch (SearchUpstreamException $e) {
            $this->assertStringContainsString('HTTP 400', $e->getMessage());
            $this->assertStringContainsString('Model not found', $e->getMessage());
        }
    }

    public function testOpenAiStyleNestedErrorObjectMessageIsExtracted(): void
    {
        $client = $this->client('chat_error_object');

        try {
            $client->query('hola');
            $this->fail('Expected a SearchUpstreamException.');
        } catch (SearchUpstreamException $e) {
            $this->assertStringContainsString('Rate limited', $e->getMessage());
        }
    }

    public function testFlatErrorStringIsExtracted(): void
    {
        $client = $this->client('chat_error_string');

        try {
            $client->query('hola');
            $this->fail('Expected a SearchUpstreamException.');
        } catch (SearchUpstreamException $e) {
            $this->assertStringContainsString('Bad request', $e->getMessage());
        }
    }

    public function testMissingChoicesFieldThrowsSearchUpstreamException(): void
    {
        $client = $this->client('chat_missing_field');

        $this->expectException(SearchUpstreamException::class);
        $this->expectExceptionMessageMatches('/without a "choices\[0\]\.message\.content" field/');

        $client->query('hola');
    }

    public function testCustomCompletionsPathIsActuallyTheOneRequested(): void
    {
        // Open WebUI needs "/api/chat/completions" instead of the default
        // "/v1/chat/completions". The fixture server logs the real path of
        // every request it receives to a file, so this proves — from the
        // outside, over a real socket — that the configured path is the
        // one actually hit, not just that the call happens to succeed.
        $log = tempnam(sys_get_temp_dir(), 'derafu-content-llm-path-');
        unlink($log);

        $client = new OpenAiCompatibleLlmClient(
            self::$server->url(),
            'test-model',
            'test-api-key',
            '/api/chat/completions?scenario=chat_ok&log_to=' . urlencode($log)
        );

        $client->query('hola');

        $logged = file_get_contents($log);
        $this->assertIsString($logged);
        $this->assertSame('/api/chat/completions', trim($logged));

        unlink($log);
    }

    public function testUnreachableHostThrowsSearchUpstreamExceptionImmediately(): void
    {
        $client = new OpenAiCompatibleLlmClient(
            'http://127.0.0.1:19898',
            'test-model'
        );

        $this->expectException(SearchUpstreamException::class);
        $this->expectExceptionMessageMatches('/could not be reached/');

        $client->query('hola');
    }

    public function testAContentThatIsNotATextThrowsSearchUpstreamException(): void
    {
        $client = $this->client('chat_content_not_text');

        $this->expectException(SearchUpstreamException::class);
        $this->expectExceptionMessageMatches('/a "choices\[0\]\.message\.content" that is not a text, array given/');

        $client->query('hola');
    }

    public function testChoicesThatAreNotAListThrowSearchUpstreamException(): void
    {
        $client = $this->client('chat_choices_not_list');

        $this->expectException(SearchUpstreamException::class);
        $this->expectExceptionMessageMatches('/returned an invalid response: The key "choices" must be of type list<map>, string given/');

        $client->query('hola');
    }

    public function testAnErrorDetailThatIsAListIsShownAsJson(): void
    {
        $client = $this->client('chat_error_list');

        $this->expectException(SearchUpstreamException::class);
        $this->expectExceptionMessageMatches('/returned HTTP 422: \[\{"msg":"field required"\}\]/');

        $client->query('hola');
    }

    public function testAnErrorWithoutAKnownShapeHasNoDetail(): void
    {
        foreach (['chat_error_unknown' => 500, 'chat_error_text' => 502] as $scenario => $status) {
            try {
                $this->client($scenario)->query('hola');
                $this->fail('Expected a SearchUpstreamException.');
            } catch (SearchUpstreamException $e) {
                $this->assertStringEndsWith('returned HTTP ' . $status . '.', $e->getMessage());
            }
        }
    }
}
