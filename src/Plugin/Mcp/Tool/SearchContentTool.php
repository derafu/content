<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Plugin\Mcp\Tool;

use Derafu\Content\Contract\ContentServiceInterface;
use Derafu\Content\Plugin\Search\SearchPlugin;
use InvalidArgumentException;
use Mcp\Capability\Attribute\Schema;
use Mcp\Exception\ToolCallException;
use Throwable;

/**
 * MCP tool that searches the content indexed in the semantic search engine
 * (Qdrant, through the "search" plugin), the same engine used by the
 * `/api/search.json` endpoint and the website's search page.
 */
class SearchContentTool
{
    /**
     * Constructor.
     *
     * @param ContentServiceInterface $contentService Content service.
     */
    public function __construct(
        private readonly ContentServiceInterface $contentService
    ) {
    }

    /**
     * Search the indexed content of the website.
     *
     * @param string $query Natural language search query.
     * @param string|null $source Restrict the results to a single content source.
     * @param int $limit Maximum number of results to return (1-50).
     * @return array<int, array<string, mixed>>
     */
    public function __invoke(
        string $query,
        #[Schema(enum: ['academy', 'blog', 'docs', 'faq', 'pages'])]
        ?string $source = null,
        int $limit = 10
    ): array {
        $plugin = $this->getSearchPlugin();

        try {
            $results = $plugin->engine()->query($query);
        } catch (Throwable $e) {
            throw new ToolCallException(sprintf(
                'The search engine request failed: %s',
                $e->getMessage()
            ), previous: $e);
        }

        if ($source !== null) {
            $results = array_values(array_filter(
                $results,
                fn (array $result) => ($result['type'] ?? null) === $source
            ));
        }

        $limit = max(1, min(50, $limit));
        $results = array_slice($results, 0, $limit);

        return array_map(
            fn (array $result) => [
                'source' => $result['type'] ?? null,
                'uri' => $result['uri'] ?? null,
                'title' => $result['title'] ?? null,
                'url' => $result['url'] ?? null,
                'score' => $result['score'] ?? null,
                'preview' => $this->preview((string) ($result['data'] ?? '')),
            ],
            $results
        );
    }

    /**
     * Build a short preview of the content, so results stay compact. Use
     * get_content to fetch the full Markdown body of an item.
     *
     * @param string $data Full text of the content item.
     * @param int $maxLength Maximum length of the preview.
     * @return string
     */
    private function preview(string $data, int $maxLength = 300): string
    {
        $data = trim($data);

        if (mb_strlen($data) <= $maxLength) {
            return $data;
        }

        return mb_substr($data, 0, $maxLength) . '...';
    }

    /**
     * Get the "search" plugin.
     *
     * @return SearchPlugin
     * @throws ToolCallException If the "search" plugin is not configured.
     */
    private function getSearchPlugin(): SearchPlugin
    {
        try {
            $plugin = $this->contentService->plugin('search');
        } catch (InvalidArgumentException $e) {
            throw new ToolCallException(
                'The search engine is not configured for this site.',
                previous: $e
            );
        }

        assert($plugin instanceof SearchPlugin);

        return $plugin;
    }
}
