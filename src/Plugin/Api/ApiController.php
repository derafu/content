<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Plugin\Api;

use Derafu\Content\Contract\ContentServiceInterface;
use Derafu\Http\Request;
use Derafu\Routing\Contract\RouterInterface;
use Derafu\Routing\Enum\UrlReferenceType;

class ApiController
{
    /**
     * Constructor.
     *
     * @param ContentServiceInterface $contentService Content service.
     * @param RouterInterface $router Router.
     */
    public function __construct(
        private readonly ContentServiceInterface $contentService,
        private readonly RouterInterface $router
    ) {
    }

    /**
     * API index action.
     *
     * @param Request $request Request.
     * @return array
     */
    public function index(Request $request): array
    {
        $plugin = $this->contentService->plugin('api');
        assert($plugin instanceof ApiPlugin);

        $knowledge = $plugin->knowledge(
            $this->contentService->plugins(),
            $request->all()
        );

        $data = [];

        foreach ($knowledge as $content) {
            if (empty($content->data())) {
                continue;
            }

            $route = $content->route();

            $data[] = [
                'id' => $content->id(),
                'checksum' => $content->checksum(),
                'type' => $content->type(),
                'category' => $content->category(),
                'uri' => $content->uri(),
                'link' => $this->router->generate(
                    $route->name,
                    $route->params,
                    UrlReferenceType::ABSOLUTE_URL
                ) . '.json',
                'image' => $content->image(),
                'title' => $content->title(),
                'description' => $content->description(),
                'authors' => $content->authors(),
                'tags' => $content->tags(),
                'date' => $content->date()->format('Y-m-d'),
                'last_update' => $content->last_update()->format('Y-m-d'),
                'time' => $content->time(),
            ];
        }

        return [
            'meta' => [
                'count' => count($data),
                'url' => $this->router->generate(
                    'homepage',
                    [],
                    UrlReferenceType::ABSOLUTE_URL
                ),
                'generated' => date('Y-m-d H:i:s'),
            ],
            'data' => $data,
        ];
    }
}
