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

use Derafu\Content\Contract\ContentItemInterface;
use Derafu\Content\Contract\ContentServiceInterface;
use Derafu\Routing\Contract\RouterInterface;
use Derafu\Routing\Enum\UrlReferenceType;
use Mcp\Capability\Attribute\Schema;

/**
 * MCP tool to browse/filter the items of a content source, without fetching
 * the full body of each one. Use get_content to fetch a specific item found
 * here.
 */
class ListContentTool
{
    /**
     * Constructor.
     *
     * @param ContentServiceInterface $contentService Content service.
     * @param RouterInterface $router Router.
     */
    public function __construct(
        private readonly ContentServiceInterface $contentService,
        private readonly RouterInterface $router
    ) {
    }

    /**
     * Browse the items of a content source.
     *
     * @param string $source Content source to browse.
     * @param string|null $tag Only items with this tag (slug or name).
     * @param string|null $category Only items of this category (e.g. "lesson" for academy).
     * @param string|null $search Free-text search over title, description and body.
     * @param int $limit Maximum number of items to return (1-100).
     * @param int $page Page number, used together with limit.
     * @return array<int, array<string, mixed>>
     */
    public function __invoke(
        #[Schema(enum: ['academy', 'blog', 'docs', 'faq', 'pages'])]
        string $source,
        ?string $tag = null,
        ?string $category = null,
        ?string $search = null,
        int $limit = 20,
        int $page = 1
    ): array {
        $plugin = ContentSourceResolver::resolve($this->contentService, $source);

        $items = $plugin->registry()->filter([
            'tag' => $tag,
            'category' => $category,
            'search' => $search,
            'limit' => max(1, min(100, $limit)),
            'page' => max(1, $page),
        ]);

        return array_map(
            fn (ContentItemInterface $item) => $this->summarize($item),
            $items
        );
    }

    /**
     * Summarize a content item for a listing.
     *
     * @param ContentItemInterface $item Content item.
     * @return array<string, mixed>
     */
    private function summarize(ContentItemInterface $item): array
    {
        $route = $item->route();

        return [
            'id' => $item->id(),
            'uri' => $item->uri(),
            'title' => $item->title(),
            'description' => $item->description(),
            'tags' => array_map(fn ($tag) => $tag->name(), $item->tags()),
            'date' => $item->date()->format('Y-m-d'),
            'url' => $this->router->generate(
                $route->name,
                $route->params,
                UrlReferenceType::ABSOLUTE_URL
            ),
        ];
    }
}
