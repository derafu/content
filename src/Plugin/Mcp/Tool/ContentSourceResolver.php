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

use Derafu\Content\Contract\ContentPluginInterface;
use Derafu\Content\Contract\ContentServiceInterface;
use InvalidArgumentException;
use Mcp\Exception\ToolCallException;

/**
 * Resolves a content source name (academy, blog, docs, faq) to its plugin,
 * turning invalid input into a clean MCP tool error instead of an unhandled
 * exception.
 */
final class ContentSourceResolver
{
    /**
     * Resolve a content source name to its plugin.
     *
     * @param ContentServiceInterface $contentService Content service.
     * @param string $source Content source name.
     * @return ContentPluginInterface
     * @throws ToolCallException If the source does not exist or is not a
     * content source.
     */
    public static function resolve(
        ContentServiceInterface $contentService,
        string $source
    ): ContentPluginInterface {
        try {
            $plugin = $contentService->plugin($source);
        } catch (InvalidArgumentException $e) {
            throw new ToolCallException(
                sprintf('Unknown content source "%s".', $source),
                previous: $e
            );
        }

        if (!$plugin instanceof ContentPluginInterface) {
            throw new ToolCallException(sprintf(
                '"%s" is not a content source.',
                $source
            ));
        }

        return $plugin;
    }
}
