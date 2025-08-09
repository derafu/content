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

/**
 * Engine for the search.
 *
 * This engine is used to query the real search engine. It will resolve the URL
 * based on the collection and base URL if available.
 *
 * The URL must be a template with the following placeholders, in this order:
 *
 *   - %s: Collection.
 *   - %s: Base URL.
 *   - %s: Query.
 *
 * You can omit the collection and base URL if you don't need them.
 */
class SearchEngine
{
    /**
     * Constructor.
     *
     * @param string $url URL of the search engine.
     * @param string|null $collection Collection of the search engine.
     * @param string|null $base_url Base URL of the search engine.
     */
    public function __construct(
        private readonly string $url,
        private ?string $collection = null,
        private ?string $base_url = null
    ) {
        if ($this->collection !== null) {
            $this->collection = urlencode($this->collection);
        }
        if ($this->base_url !== null) {
            $this->base_url = urlencode($this->base_url);
        }
    }

    /**
     * Query the search engine.
     *
     * @param string $query Query.
     * @return array Results.
     */
    public function query(string $query)
    {
        $url = $this->resolveUrl($query);

        $response = file_get_contents($url);
        $results = json_decode($response, true)['results'];

        return $results;
    }

    /**
     * Resolve the URL for the query.
     *
     * @param string $query Query.
     * @return string URL.
     */
    private function resolveUrl(string $query): string
    {
        // If collection or base URL is not set, return the URL without the
        // collection and base URL. This assumes that the URL should contain the
        // collection and base URL if needed.
        if (empty($this->collection) || empty($this->base_url)) {
            return sprintf(
                $this->url,
                urlencode($query),
            );
        }

        // If base URL is not set, return the URL without the base URL. This
        // assumes that the URL should contain the base URL if needed. Also,
        // the following order of parameters are expected: collection, text.
        if (empty($this->base_url)) {
            return sprintf(
                $this->url,
                $this->collection,
                urlencode($query),
            );
        }

        // If collection and base URL are set, return the URL with the
        // collection and base URL. The following order of parameters are
        // expected: collection, base_url, text.
        return sprintf(
            $this->url,
            $this->collection,
            $this->base_url,
            urlencode($query),
        );
    }
}
