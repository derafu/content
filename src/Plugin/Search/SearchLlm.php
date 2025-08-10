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

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Client\ClientInterface as HttpClientInterface;
use RuntimeException;
use Throwable;

/**
 * LLM client for querying Open WebUI API.
 *
 * This client is used to query Open WebUI API for generating AI responses.
 * It uses PSR-18 HTTP client for making requests with proper dependency injection.
 */
class SearchLlm
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
     * @param string $url The URL for the Open WebUI API.
     * @param string $model The model name to use.
     * @param string|null $apiKey The API key for authentication.
     * @param HttpClientInterface|null $httpClient The PSR-18 HTTP client to use
     * for requests.
     */
    public function __construct(
        private readonly string $url,
        private readonly string $model,
        private readonly ?string $apiKey = null,
        ?HttpClientInterface $httpClient = null
    ) {
        $this->httpClient = $httpClient ?? new HttpClient();
    }

    /**
     * Query the LLM and return complete response.
     *
     * @param string $query The query to send to the LLM.
     * @return string The complete response from the LLM.
     * @throws RuntimeException If the HTTP request failed or response is invalid.
     */
    public function query(string $query): string
    {
        $data = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $query,
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
            // Create PSR-7 request.
            $url = $this->url . '/api/chat/completions';
            $request = new Request('POST', $url, $headers, json_encode($data));

            // Send request using PSR-18 client.
            $response = $this->httpClient->sendRequest($request);

            if ($response->getStatusCode() !== 200) {
                $responseBody = $response->getBody()->getContents();
                $errorDetails = '';

                $errorData = json_decode($responseBody, true);
                if ($errorData && isset($errorData['error'])) {
                    $errorDetails = " - {$errorData['error']}";
                }

                throw new RuntimeException(
                    "HTTP request failed with status: {$response->getStatusCode()}{$errorDetails}. URL: {$url}, Method: POST"
                );
            }

            $responseBody = $response->getBody()->getContents();
            $decodedResponse = json_decode($responseBody, true);

            if ($decodedResponse === null) {
                throw new RuntimeException('Invalid JSON response from LLM.');
            }

            if (!isset($decodedResponse['choices'][0]['message']['content'])) {
                throw new RuntimeException('Response does not contain content field.');
            }

            return $decodedResponse['choices'][0]['message']['content'];

        } catch (Throwable $e) {
            throw new RuntimeException(
                "LLM request failed: {$e->getMessage()}.",
                $e->getCode(),
                $e
            );
        }
    }
}
