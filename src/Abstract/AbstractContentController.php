<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Abstract;

use Derafu\Content\Contract\ContentItemInterface;
use Derafu\Http\Request;
use Derafu\Renderer\Contract\RendererInterface;

abstract class AbstractContentController
{
    protected function getPreferredFormat(Request $request): string
    {
        $preferredFormat = $request->getPreferredFormat();
        return match ($preferredFormat) {
            'json' => 'json',
            'pdf' => 'pdf',
            'markdown' => 'md',
            default => 'html',
        };
    }

    /**
     * Builds the ".json" response body for a content item, with its
     * "data" key holding the same fully rendered Markdown the ".md"
     * format of this same item would return (video/test/openapi and
     * everything else the "*.md.twig" templates add), instead of the
     * item's raw, unprocessed body.
     *
     * Without this, an item whose real content lives in a "special"
     * frontmatter field (a lesson's "test", a doc's "openapi", a video)
     * exports as JSON with next to nothing in "data" — bad for a
     * pipeline that indexes this JSON externally (e.g. into Qdrant): it
     * ends up with barely anything to search on for exactly the content
     * that has the most to say.
     *
     * @param ContentItemInterface $item Content item.
     * @param RendererInterface $renderer Renderer.
     * @param string $template The item's own "*.md.twig" template.
     * @param array<string, mixed> $vars Variables the template needs
     * (whatever its own ".md" action already passes it, e.g. "plugin",
     * the item itself under its own variable name, "course"/"module" for
     * academy, "full").
     * @return array<string, mixed>
     */
    protected function jsonResponse(
        ContentItemInterface $item,
        RendererInterface $renderer,
        string $template,
        array $vars
    ): array {
        $data = $item->toArray();
        $data['data'] = $renderer->render($template, $vars);

        return ['data' => $data];
    }
}
