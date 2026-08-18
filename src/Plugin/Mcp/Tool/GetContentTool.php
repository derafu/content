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
use Derafu\Content\Exception\ContentNotFoundException;
use Derafu\Routing\Contract\RouterInterface;
use Derafu\Routing\Enum\UrlReferenceType;
use Mcp\Capability\Attribute\Schema;
use Mcp\Exception\ToolCallException;

/**
 * MCP tool that fetches a single content item, with its full Markdown body
 * and metadata. This is the canonical way for an agent to read a specific
 * doc/post/question/lesson, and it returns the exact same Markdown body the
 * website serves for the ".md" format of the item.
 */
class GetContentTool
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
     * Fetch a single content item.
     *
     * @param string $source Content source of the item.
     * @param string $uri URI of the item within its source (e.g. "introduccion/que-es-libredte").
     * @return array<string, mixed>
     */
    public function __invoke(
        #[Schema(enum: ['academy', 'blog', 'docs', 'faq', 'pages'])]
        string $source,
        string $uri
    ): array {
        $plugin = ContentSourceResolver::resolve($this->contentService, $source);

        try {
            $item = $plugin->registry()->get($uri);
        } catch (ContentNotFoundException $e) {
            throw new ToolCallException(sprintf(
                'Content "%s" was not found in source "%s".',
                $uri,
                $source
            ), previous: $e);
        }

        $route = $item->route();

        return [
            'id' => $item->id(),
            'source' => $item->type(),
            'category' => $item->category(),
            'uri' => $item->uri(),
            'title' => $item->title(),
            'description' => $item->description(),
            'tags' => array_map(fn ($tag) => $tag->name(), $item->tags()),
            'authors' => array_map(fn ($author) => $author->name(), $item->authors()),
            'date' => $item->date()->format('Y-m-d'),
            'last_update' => $item->last_update()->format('Y-m-d'),
            'time_minutes' => $item->time(),
            'url' => $this->router->generate(
                $route->name,
                $route->params,
                UrlReferenceType::ABSOLUTE_URL
            ),
            'markdown' => $item->data(),
        ];
    }
}
