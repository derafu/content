<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Plugin\Search;

use Derafu\Content\Plugin\Search\Contract\LlmClientInterface;
use Derafu\Content\Plugin\Search\Exception\SearchUpstreamException;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Client\ClientInterface as HttpClientInterface;
use Throwable;

/**
 * LLM client for any backend exposing an OpenAI-compatible chat completions
 * endpoint.
 *
 * That covers, with no code changes and just configuration (url, model,
 * completions path, api key): OpenAI, OpenRouter (which itself proxies
 * Claude, Gemini, Llama, etc.), Open WebUI, Anthropic through its own
 * OpenAI-compatible endpoint, Groq, Together AI, Ollama, and most other
 * providers, since this request/response shape has become a de facto
 * standard. The completions path defaults to the standard "/v1/chat/
 * completions" used by OpenAI/OpenRouter/most providers; self-hosted Open
 * WebUI instances use "/api/chat/completions" instead and need to override
 * it.
 *
 * It uses a PSR-18 HTTP client for making requests with proper dependency
 * injection.
 */
class OpenAiCompatibleLlmClient implements LlmClientInterface
{
    /**
     * The PSR-18 HTTP client for making requests.
     *
     * @var HttpClientInterface
     */
    private readonly HttpClientInterface $httpClient;

    /**
     * Constructor.
     *
     * @param string $url The base URL of the LLM backend.
     * @param string $model The model name to use.
     * @param string|null $apiKey The API key for authentication.
     * @param string $completionsPath Path of the chat completions endpoint,
     * appended to the base URL.
     * @param HttpClientInterface|null $httpClient The PSR-18 HTTP client to
     * use for requests.
     */
    public function __construct(
        private readonly string $url,
        private readonly string $model,
        private readonly ?string $apiKey = null,
        private readonly string $completionsPath = '/v1/chat/completions',
        ?HttpClientInterface $httpClient = null
    ) {
        $this->httpClient = $httpClient ?? new HttpClient();
    }

    /**
     * {@inheritDoc}
     */
    public function query(string $question): string
    {
        $url = rtrim($this->url, '/') . $this->completionsPath;

        $data = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $question,
                ],
            ],
            'stream' => false,
            'temperature' => 0.7,
            'max_tokens' => 500,
        ];

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        // Add authentication if API key is provided.
        if ($this->apiKey !== null) {
            $headers['Authorization'] = 'Bearer ' . $this->apiKey;
        }

        try {
            $request = new Request('POST', $url, $headers, json_encode($data, JSON_THROW_ON_ERROR));
            $response = $this->httpClient->sendRequest($request);
        } catch (Throwable $e) {
            throw new SearchUpstreamException(sprintf(
                'The LLM backend at "%s" could not be reached: %s.',
                $url,
                $e->getMessage()
            ), $e);
        }

        if ($response->getStatusCode() !== 200) {
            $detail = $this->extractErrorDetail(
                $response->getBody()->getContents()
            );

            throw new SearchUpstreamException(sprintf(
                'The LLM backend at "%s" returned HTTP %d%s.',
                $url,
                $response->getStatusCode(),
                $detail !== null ? sprintf(': %s', $detail) : ''
            ));
        }

        $decodedResponse = json_decode(
            $response->getBody()->getContents(),
            true
        );

        if (!isset($decodedResponse['choices'][0]['message']['content'])) {
            throw new SearchUpstreamException(sprintf(
                'The LLM backend at "%s" returned a response without a '
                    . '"choices[0].message.content" field.',
                $url
            ));
        }

        return $decodedResponse['choices'][0]['message']['content'];
    }

    /**
     * Extract a human-readable error detail from the response body of a
     * failed request, trying the common shapes used by OpenAI-compatible
     * backends.
     *
     * @param string $responseBody Raw response body.
     * @return string|null The extracted detail, or null if none could be
     * extracted.
     */
    private function extractErrorDetail(string $responseBody): ?string
    {
        $errorData = json_decode($responseBody, true);

        if (!is_array($errorData)) {
            return null;
        }

        // OpenAI-compatible shape: {"error": "..."} or
        // {"error": {"message": "..."}}.
        if (isset($errorData['error'])) {
            $error = $errorData['error'];

            if (is_string($error)) {
                return $error;
            }

            if (is_array($error) && isset($error['message']) && is_string($error['message'])) {
                return $error['message'];
            }
        }

        // FastAPI-style shape used by Open WebUI: {"detail": "..."} or
        // {"detail": [...]} for validation errors.
        if (isset($errorData['detail'])) {
            $detail = $errorData['detail'];

            if (is_string($detail)) {
                return $detail;
            }

            if (is_array($detail)) {
                return json_encode($detail);
            }
        }

        return null;
    }
}
