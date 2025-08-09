<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Plugin\Search;

use Derafu\Content\Contract\ContentServiceInterface;
use Derafu\Http\Request;
use Derafu\Renderer\Contract\RendererInterface;
use InvalidArgumentException;

/**
 * Controller for the search plugin.
 */
class SearchController
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
     * Render the search page.
     *
     * @param Request $request Request.
     * @return string Rendered page.
     */
    public function index(Request $request): string
    {
        $query = $request->query('q', '');

        $results = $query ? $this->search($query) : [];

        return $this->renderer->render('search/index.html.twig', [
            'query' => $query,
            'results' => $results,
        ]);
    }

    /**
     * API endpoint to search the content.
     *
     * @param Request $request Request.
     * @return array Results.
     */
    public function api_index(Request $request): array
    {
        $query = $request->query('q', '');

        if (empty($query)) {
            throw new InvalidArgumentException('Query is required.', 400);
        }

        return $this->search($query);
    }

    /**
     * Search the content.
     *
     * @param string $query Query.
     * @return array Results.
     */
    private function search(string $query): array
    {
        $plugin = $this->contentService->plugin('search');
        assert($plugin instanceof SearchPlugin);

        $results = $plugin->engine()->query($query);

        return $results;
    }
}
