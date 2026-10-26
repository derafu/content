<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Plugin\Faq;

use Derafu\Content\Abstract\AbstractContentController;
use Derafu\Content\ContentFilters;
use Derafu\Content\ContentTag;
use Derafu\Content\Contract\ContentServiceInterface;
use Derafu\Http\Request;
use Derafu\Renderer\Contract\RendererInterface;
use Derafu\Routing\Contract\UrlGeneratorInterface;

/**
 * FAQ controller.
 */
class FaqController extends AbstractContentController
{
    /**
     * Constructor.
     *
     * @param ContentServiceInterface $contentService Content service.
     * @param RendererInterface $renderer Renderer.
     * @param UrlGeneratorInterface $router URL generator.
     */
    public function __construct(
        private readonly ContentServiceInterface $contentService,
        private readonly RendererInterface $renderer,
        private readonly UrlGeneratorInterface $router
    ) {
    }

    /**
     * Show action.
     *
     * @param Request $request Request.
     * @param string $question Question.
     * @return string|array<string, mixed>
     */
    public function show(Request $request, string $question): string|array
    {
        $plugin = $this->contentService->plugin('faq');
        assert($plugin instanceof FaqPlugin);

        $format = $this->getPreferredFormat($request);
        $uri = str_replace('.' . $format, '', $question);

        $faq = $plugin->registry()->get($uri);

        if ($format === 'json') {
            return $this->jsonResponse($faq, $this->renderer, $this->router, 'faq/show.md.twig', [
                'plugin' => $plugin,
                'faq' => $faq,
                'full' => false,
            ]);
        } else {
            return $this->renderer->render(
                'faq/show.' . $format . '.twig',
                [
                    'plugin' => $plugin,
                    'faq' => $faq,
                    'faqs' => $plugin->registry()->filterTree(),
                    'tags' => $plugin->registry()->tags(),
                    'full' => (bool) $request->query('full'),
                ]
            );
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

        $filters = ContentFilters::fromRequest($request->all());
        $tags = $plugin->registry()->tags();
        $contentTag = $tags[$tag] ?? new ContentTag($tag);
        $filters['tag'] = $contentTag->slug();
        $faqsFiltered = $plugin->registry()->filter($filters);
        $faqs = $plugin->registry()->filterTree();

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
