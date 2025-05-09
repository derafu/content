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

use Derafu\Content\Contract\FaqRegistryInterface;
use Derafu\Content\ValueObject\ContentTag;
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
     * @param FaqRegistryInterface $faqRegistry Faq registry.
     * @param RendererInterface $renderer Renderer.
     */
    public function __construct(
        private readonly FaqRegistryInterface $faqRegistry,
        private readonly RendererInterface $renderer
    ) {
    }

    /**
     * Show action.
     *
     * @param Request $request Request.
     * @param string $uri URI of the FAQ.
     * @return string|array
     */
    public function show(Request $request, string $uri): string|array
    {
        $preferredFormat = $request->getPreferredFormat();
        $uri = str_replace('.' . $preferredFormat, '', $uri);

        if ($preferredFormat === 'json') {
            return [
                'data' => $this->faqRegistry->get($uri)->toArray(),
            ];
        } else {
            return $this->renderer->render('faq/show.html.twig', [
                'faq' => $this->faqRegistry->get($uri),
                'faqs' => $this->faqRegistry->all(),
                'tags' => $this->faqRegistry->tags(),
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
        $tags = $this->faqRegistry->tags();
        $contentTag = $tags[$tag] ?? new ContentTag($tag);
        $filters['tag'] = $contentTag->slug();
        $faqsFiltered = $this->faqRegistry->filter($filters);
        $faqs = $this->faqRegistry->all();

        return $this->renderer->render('faq/tag.html.twig', [
            'filters' => $filters,
            'faqsFiltered' => $faqsFiltered,
            'faqs' => $faqs,
            'tags' => $tags,
            'tag' => $contentTag,
        ]);
    }
}
