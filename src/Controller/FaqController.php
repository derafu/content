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
use Derafu\Renderer\Contract\RendererInterface;

/**
 * Faq controller.
 */
final class FaqController
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
     * Index action.
     *
     * @return string
     */
    public function index(): string
    {
        $faqs = $this->faqRegistry->getFaqs();

        return $this->renderer->render('faq/index.html.twig', [
            'faqs' => $faqs,
        ]);
    }

    /**
     * Show action.
     *
     * @param string $slug Slug of the faq.
     * @return string
     */
    public function show(string $slug): string
    {
        $faq = $this->faqRegistry->getFaq($slug);

        return $this->renderer->render('faq/show.html.twig', [
            'faq' => $faq,
        ]);
    }
}
