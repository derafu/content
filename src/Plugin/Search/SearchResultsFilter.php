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

use Derafu\Content\Contract\ContentPluginInterface;
use Derafu\Content\Contract\ContentServiceInterface;
use Throwable;

/**
 * Filters raw upstream search engine results (Qdrant, in production)
 * against the local content registry, shared by every consumer of
 * SearchEngine::query() (SearchController, the MCP search_content tool).
 */
final class SearchResultsFilter
{
    /**
     * Drop any result whose backing content item is not searchable(), or
     * is no longer allowed()/unlisted() — the upstream index can be
     * stale: an item indexed before becoming draft/unlisted, or removed
     * from the registry entirely, must not leak back through search. Any
     * lookup failure (unknown source, uri no longer found) is treated
     * the same way as "not searchable": silently dropped.
     *
     * @param ContentServiceInterface $contentService Content service.
     * @param array<int, array<string, mixed>> $results Raw engine results.
     * @return array<int, array<string, mixed>> Filtered results.
     */
    public static function filter(
        ContentServiceInterface $contentService,
        array $results
    ): array {
        return array_values(array_filter(
            $results,
            function (array $result) use ($contentService): bool {
                try {
                    $plugin = $contentService->plugin((string) ($result['type'] ?? ''));
                    if (!$plugin instanceof ContentPluginInterface) {
                        return false;
                    }

                    $item = $plugin->registry()->get((string) ($result['uri'] ?? ''));
                } catch (Throwable) {
                    return false;
                }

                return $item->searchable() && $item->allowed() && !$item->unlisted();
            }
        ));
    }
}
