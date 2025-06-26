<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Plugin\Docs;

use Derafu\Content\ContentTag;
use Derafu\Content\Contract\ContentServiceInterface;
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
     * @param ContentServiceInterface $contentService Content service.
     * @param RendererInterface $renderer Renderer.
     */
    public function __construct(
        private readonly ContentServiceInterface $contentService,
        private readonly RendererInterface $renderer
    ) {
    }

    /**
     * Show action.
     *
     * @param Request $request Request.
     * @param string $doc Doc.
     * @return string|array
     */
    public function show(Request $request, string $doc): string|array
    {
        $plugin = $this->contentService->plugin('docs');
        assert($plugin instanceof DocsPlugin);

        $preferredFormat = $request->getPreferredFormat();
        $uri = str_replace('.' . $preferredFormat, '', $doc);

        $doc = $plugin->registry()->get($uri);

        if ($preferredFormat === 'json') {
            return [
                'data' => $doc->toArray(),
            ];
        } elseif ($preferredFormat === 'pdf') {
            return $this->renderer->render('docs/show.pdf.twig', [
                'plugin' => $plugin,
                'doc' => $doc,
            ]);
        } else {
            return $this->renderer->render('docs/show.html.twig', [
                'plugin' => $plugin,
                'doc' => $doc,
                'previous' => $plugin->registry()->previous($doc->uri()),
                'next' => $plugin->registry()->next($doc->uri()),
                'docs' => $plugin->registry()->all(),
                'tags' => $plugin->registry()->tags(),
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
        $plugin = $this->contentService->plugin('docs');
        assert($plugin instanceof DocsPlugin);

        $filters = array_filter($request->all(), fn ($value) => $value !== '');
        $tags = $plugin->registry()->tags();
        $contentTag = $tags[$tag] ?? new ContentTag($tag);
        $filters['tag'] = $contentTag->slug();
        $docsFiltered = $plugin->registry()->filter($filters);
        $docs = $plugin->registry()->all();

        return $this->renderer->render('docs/tag.html.twig', [
            'plugin' => $plugin,
            'filters' => $filters,
            'docsFiltered' => $docsFiltered,
            'docs' => $docs,
            'tags' => $tags,
            'tag' => $contentTag,
        ]);
    }
}
