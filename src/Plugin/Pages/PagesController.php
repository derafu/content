<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Plugin\Pages;

use Derafu\Content\Abstract\AbstractContentController;
use Derafu\Content\Contract\ContentServiceInterface;
use Derafu\Http\Request;
use Derafu\Renderer\Contract\RendererInterface;

/**
 * Controller for the pages plugin.
 */
class PagesController extends AbstractContentController
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
     * @param string $page Page.
     * @return string|array
     */
    public function show(Request $request, string $page): string|array
    {
        $plugin = $this->contentService->plugin('pages');
        assert($plugin instanceof PagesPlugin);

        $format = $this->getPreferredFormat($request);
        $uri = str_replace('.' . $format, '', $page);

        $item = $plugin->registry()->get($uri);

        if ($format === 'json') {
            return [
                'data' => $item->toArray(),
            ];
        }

        return $this->renderer->render(
            'pages/show.' . $format . '.twig',
            [
                'plugin' => $plugin,
                'page' => $item,
            ]
        );
    }
}
