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
use Derafu\Translation\Exception\Core\TranslatableRuntimeException as RuntimeException;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException as InvalidArgumentException;

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
        $query = $this->query($request);

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
     * @return array<int, array<string, mixed>> Results.
     */
    public function api_index(Request $request): array
    {
        $query = $this->query($request);

        if (empty($query)) {
            throw new InvalidArgumentException('Query is required.', 400);
        }

        return $this->search($query);
    }

    /**
     * LLM proxy endpoint to handle Open WebUI requests.
     *
     * @param Request $request Request.
     * @return array<string, mixed> JSON response.
     */
    public function llm_query(Request $request): array
    {
        $query = $this->query($request);
        $html = $request->query('html', false);

        if (empty($query)) {
            throw new InvalidArgumentException('Query is required.', 400);
        }

        $plugin = $this->contentService->plugin('search');
        assert($plugin instanceof SearchPlugin);

        $llm = $plugin->llm();
        if ($llm === null) {
            throw new RuntimeException('The "search" plugin has no LLM configured: set "llm_url" and "llm_model".');
        }

        $llmResponse = $llm->query($query);

        return [
            'choices' => [
                [
                    'delta' => [
                        'content' => $html
                            ? $this->renderer->render('search/llm-response.html.twig', [
                                'content' => $llmResponse,
                            ])
                            : $llmResponse
                        ,
                    ],
                ],
            ],
        ];
    }

    /**
     * Search the content.
     *
     * @param string $query Query.
     * @return array<int, array<string, mixed>> Results.
     */
    private function search(string $query): array
    {
        $plugin = $this->contentService->plugin('search');
        assert($plugin instanceof SearchPlugin);

        $results = $plugin->engine()->query($query);

        return SearchResultsFilter::filter($this->contentService, $results);
    }

    /**
     * Reads the query of the request.
     *
     * @param Request $request Request.
     * @return string The query, empty if the request has none.
     * @throws InvalidArgumentException If the query is not a text.
     */
    private function query(Request $request): string
    {
        $query = $request->query('q', '');

        if (!is_string($query)) {
            throw new InvalidArgumentException([
                'The query "q" must be a text, {type} given.',
                'type' => get_debug_type($query),
            ], 400);
        }

        return $query;
    }
}
