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

use Derafu\Content\Contract\AcademyRegistryInterface;
use Derafu\Content\Contract\BlogRegistryInterface;
use Derafu\Content\Contract\DocsRegistryInterface;
use Derafu\Content\Contract\FaqRegistryInterface;
use Derafu\Http\Request;
use Derafu\Routing\Contract\RouterInterface;
use Derafu\Routing\Enum\UrlReferenceType;

/**
 * Main controller for all content types.
 */
class ContentController
{
    /**
     * Constructor.
     *
     * @param AcademyRegistryInterface $academyRegistry Academy registry.
     * @param BlogRegistryInterface $blogRegistry Blog registry.
     * @param DocsRegistryInterface $docsRegistry Docs registry.
     * @param FaqRegistryInterface $faqRegistry Faq registry.
     */
    public function __construct(
        private readonly RouterInterface $router,
        private readonly AcademyRegistryInterface $academyRegistry,
        private readonly BlogRegistryInterface $blogRegistry,
        private readonly DocsRegistryInterface $docsRegistry,
        private readonly FaqRegistryInterface $faqRegistry,
    ) {
    }

    /**
     * API index action.
     *
     * @param Request $request Request.
     * @return array
     */
    public function api_index(Request $request): array
    {
        $filters = array_filter($request->all(), fn ($value) => $value !== '');

        $knowledge = [
            ...$this->academyRegistry->filter($filters),
            ...$this->blogRegistry->filter($filters),
            ...$this->docsRegistry->filter($filters),
            ...$this->faqRegistry->filter($filters),
        ];

        $data = [];

        foreach ($knowledge as $content) {
            if (empty($content->data())) {
                continue;
            }

            $route = $content->route();

            $data[] = [
                'type' => $content->type(),
                'category' => $content->category(),
                'checksum' => $content->checksum(),
                'uri' => $content->uri(),
                'link' => $this->router->generate(
                    $route->name,
                    $route->params,
                    UrlReferenceType::ABSOLUTE_URL
                ) . '.json',
                'image' => $content->image(),
                'title' => $content->title(),
                'summary' => $content->summary(),
                'author' => $content->author(),
                'tags' => $content->tags(),
                'published' => $content->published()->format('Y-m-d'),
                'time' => $content->time(),
            ];
        }

        return [
            'meta' => [
                'count' => count($data),
                'url' => $this->router->generate('homepage', [], UrlReferenceType::ABSOLUTE_URL),
                'generated' => date('Y-m-d H:i:s'),
            ],
            'data' => $data,
        ];
    }
}
