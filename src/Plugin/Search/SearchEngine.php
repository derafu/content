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
 * Search engine client for querying external search services.
 *
 * This engine is used to query external search engines by constructing URLs
 * based on the provided collection and base URL parameters. It uses PSR-18
 * HTTP client for making requests with proper dependency injection.
 *
 * The URL template must contain placeholders in the following order:
 *
 *   - %s: Collection (optional).
 *   - %s: Base URL (optional).
 *   - %s: Search query (required).
 *
 * You can omit the collection and base URL placeholders if they are not needed.
 */
class SearchEngine
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
     * @param string $url The URL template for the search engine.
     * @param string|null $collection The collection identifier for the search
     * engine.
     * @param string|null $baseUrl The base URL for the search engine.
     * @param HttpClientInterface|null $httpClient The PSR-18 HTTP client to use
     * for requests.
     */
    public function __construct(
        private readonly string $url,
        private ?string $collection = null,
        private ?string $baseUrl = null,
        ?HttpClientInterface $httpClient = null
    ) {
        if ($this->collection !== null) {
            $this->collection = urlencode($this->collection);
        }
        if ($this->baseUrl !== null) {
            $this->baseUrl = urlencode($this->baseUrl);
        }

        $this->httpClient = $httpClient ?? new HttpClient();
    }

    /**
     * Query the search engine and return results.
     *
     * @param string $query The search query to execute.
     * @return array<mixed> The search results from the engine.
     * @throws RuntimeException If the HTTP request fails or response is invalid.
     */
    public function query(string $query): array
    {
        $url = $this->resolveUrl($query);

        try {
            // Create PSR-7 request.
            $request = new Request('GET', $url, [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ]);

            // Send request using PSR-18 client.
            $response = $this->httpClient->sendRequest($request);

            $responseBody = $response->getBody()->getContents();
            $decodedResponse = json_decode($responseBody, true);

            if ($decodedResponse === null) {
                throw new RuntimeException(
                    'Invalid JSON response from search engine.'
                );
            }

            if (!isset($decodedResponse['results'])) {
                throw new RuntimeException(
                    'Response does not contain results field.'
                );
            }

            return $decodedResponse['results'];

        } catch (Throwable $e) {
            throw new RuntimeException(
                "HTTP request failed: {$e->getMessage()}.",
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Resolve the URL template for the given query.
     *
     * This method constructs the final URL by replacing placeholders in the URL template
     * with the appropriate values. The method handles different scenarios based on
     * whether collection and base URL are provided.
     *
     * @param string $query The search query to encode in the URL.
     * @return string The resolved URL ready for the HTTP request.
     */
    private function resolveUrl(string $query): string
    {
        $encodedQuery = urlencode($query);

        // If collection or base URL is not set, return the URL with only the
        // query. This assumes that the URL template contains only one
        // placeholder for the query.
        if (empty($this->collection) || empty($this->baseUrl)) {
            return sprintf($this->url, $encodedQuery);
        }

        // If base URL is not set, return the URL with collection and query.
        // The expected order of parameters is: collection, query.
        if (empty($this->baseUrl)) {
            return sprintf($this->url, $this->collection, $encodedQuery);
        }

        // If both collection and base URL are set, return the complete URL.
        // The expected order of parameters is: collection, base_url, query.
        return sprintf(
            $this->url,
            $this->collection,
            $this->baseUrl,
            $encodedQuery
        );
    }
}
