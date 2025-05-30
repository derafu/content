<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Plugin\Faq;

use Derafu\Content\ContentTag;
use Derafu\Content\Contract\ContentServiceInterface;
use Derafu\Http\Request;
use Derafu\Renderer\Contract\RendererInterface;

/**
 * FAQ controller.
 */
class FaqController
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
     * @param string $question Question.
     * @return string|array
     */
    public function show(Request $request, string $question): string|array
    {
        $plugin = $this->contentService->plugin('faq');
        assert($plugin instanceof FaqPlugin);

        $preferredFormat = $request->getPreferredFormat();
        $uri = str_replace('.' . $preferredFormat, '', $question);

        $faq = $plugin->registry()->get($uri);

        if ($preferredFormat === 'json') {
            return [
                'data' => $faq->toArray(),
            ];
        } elseif ($preferredFormat === 'pdf') {
            return $this->renderer->render('faq/show.pdf.twig', [
                'plugin' => $plugin,
                'faq' => $faq,
            ]);
        } else {
            return $this->renderer->render('faq/show.html.twig', [
                'plugin' => $plugin,
                'faq' => $faq,
                'faqs' => $plugin->registry()->all(),
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
        $plugin = $this->contentService->plugin('faq');
        assert($plugin instanceof FaqPlugin);

        $filters = array_filter($request->all(), fn ($value) => $value !== '');
        $tags = $plugin->registry()->tags();
        $contentTag = $tags[$tag] ?? new ContentTag($tag);
        $filters['tag'] = $contentTag->slug();
        $faqsFiltered = $plugin->registry()->filter($filters);
        $faqs = $plugin->registry()->all();

        return $this->renderer->render('faq/tag.html.twig', [
            'plugin' => $plugin,
            'filters' => $filters,
            'faqsFiltered' => $faqsFiltered,
            'faqs' => $faqs,
            'tags' => $tags,
            'tag' => $contentTag,
        ]);
    }
}
