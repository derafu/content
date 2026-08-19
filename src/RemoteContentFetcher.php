<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Client\ClientInterface as HttpClientInterface;
use RuntimeException;
use Throwable;

/**
 * Fetches the raw body of a remote URL, for frontmatter references (e.g.
 * a lesson's "test", or a doc's "openapi") that point to another site
 * instead of a local attachment. Composes a PSR-18 client instead of
 * depending on Guzzle directly, defaulting to a real one if none is
 * injected. Shared across plugins, so it lives at the package root
 * alongside the other cross-cutting Content* classes instead of under
 * any single plugin's namespace.
 */
class RemoteContentFetcher
{
    /**
     * The PSR-18 HTTP client used to fetch remote content.
     *
     * @var HttpClientInterface
     */
    private readonly HttpClientInterface $httpClient;

    /**
     * Constructor.
     *
     * @param HttpClientInterface|null $httpClient The PSR-18 HTTP client to
     * use for requests.
     */
    public function __construct(?HttpClientInterface $httpClient = null)
    {
        $this->httpClient = $httpClient ?? new HttpClient();
    }

    /**
     * Fetch the raw body of a remote URL.
     *
     * @param string $url The URL to fetch.
     * @return string The raw response body.
     * @throws RuntimeException If the URL could not be fetched.
     */
    public function fetch(string $url): string
    {
        try {
            $response = $this->httpClient->sendRequest(new Request('GET', $url));
        } catch (Throwable $e) {
            throw new RuntimeException(sprintf(
                'Remote content at %s could not be fetched: %s',
                $url,
                $e->getMessage()
            ), previous: $e);
        }

        if ($response->getStatusCode() >= 400) {
            throw new RuntimeException(sprintf(
                'Remote content at %s responded with status %d.',
                $url,
                $response->getStatusCode()
            ));
        }

        return (string) $response->getBody();
    }
}
