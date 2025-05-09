<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Controller;

use Derafu\Content\Contract\DocsRegistryInterface;
use Derafu\Content\ValueObject\ContentTag;
use Derafu\Http\Request;
use Derafu\Renderer\Contract\RendererInterface;

/**
 * Docs controller.
 */
class DocsController
{
    /**
     * Constructor.
     *
     * @param DocsRegistryInterface $docsRegistry Docs registry.
     * @param RendererInterface $renderer Renderer.
     */
    public function __construct(
        private readonly DocsRegistryInterface $docsRegistry,
        private readonly RendererInterface $renderer
    ) {
    }

    /**
     * Show action.
     *
     * @param Request $request Request.
     * @param string $uri URI of the doc.
     * @return string|array
     */
    public function show(Request $request, string $uri): string|array
    {
        $preferredFormat = $request->getPreferredFormat();
        $uri = str_replace('.' . $preferredFormat, '', $uri);

        if ($preferredFormat === 'json') {
            return [
                'data' => $this->docsRegistry->get($uri)->toArray(),
            ];
        } else {
            return $this->renderer->render('docs/show.html.twig', [
                'doc' => $this->docsRegistry->get($uri),
                'docs' => $this->docsRegistry->all(),
                'tags' => $this->docsRegistry->tags(),
            ]);
        }
    }

    /**
     * Tag action.
     *
     * @param Request $request Request.
     * @param string $tag Tag.
     * @return string
     */
    public function tag(Request $request, string $tag): string
    {
        $filters = array_filter($request->all(), fn ($value) => $value !== '');
        $tags = $this->docsRegistry->tags();
        $contentTag = $tags[$tag] ?? new ContentTag($tag);
        $filters['tag'] = $contentTag->slug();
        $docsFiltered = $this->docsRegistry->filter($filters);
        $docs = $this->docsRegistry->all();

        return $this->renderer->render('docs/tag.html.twig', [
            'filters' => $filters,
            'docsFiltered' => $docsFiltered,
            'docs' => $docs,
            'tags' => $tags,
            'tag' => $contentTag,
        ]);
    }
}
