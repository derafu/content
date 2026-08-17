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
use Derafu\Content\Contract\ContentTagInterface;
use Mcp\Capability\Attribute\Schema;

/**
 * MCP tool to discover the tags used in a content source, so an agent can
 * narrow down list_content/search_content without guessing tag names.
 */
class ListTagsTool
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
     * List the tags used in a content source.
     *
     * @param string $source Content source.
     * @return array<int, array<string, mixed>>
     */
    public function __invoke(
        #[Schema(enum: ['academy', 'blog', 'docs', 'faq'])]
        string $source
    ): array {
        $plugin = ContentSourceResolver::resolve($this->contentService, $source);

        return array_map(
            fn (ContentTagInterface $tag) => [
                'name' => $tag->name(),
                'slug' => $tag->slug(),
                'count' => $tag->count(),
            ],
            array_values($plugin->registry()->tags())
        );
    }
}
