<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Plugin\Sitemap;

use Derafu\Content\Contract\ContentServiceInterface;
use Derafu\Http\Response;
use Derafu\Renderer\Contract\RendererInterface;

/**
 * Controller for the sitemap plugin.
 */
class SitemapController
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
     * Render the XML sitemap of every indexable content item.
     *
     * @return Response
     */
    public function index(): Response
    {
        // Ensure the plugin is enabled.
        $this->contentService->plugin('sitemap');

        $items = $this->contentService->allContent(['indexable' => true]);

        $xml = $this->renderer->render('sitemap/sitemap.xml.twig', [
            'items' => $items,
        ]);

        $response = new Response();
        $response->withHeader('Content-Type', 'application/xml; charset=UTF-8');
        $response->getBody()->write($xml);

        return $response;
    }
}
