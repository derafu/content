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

use Derafu\Content\Contract\BlogRegistryInterface;
use Derafu\Http\Request;
use Derafu\Renderer\Contract\RendererInterface;

/**
 * Blog controller.
 */
final class BlogController
{
    /**
     * Constructor.
     *
     * @param BlogRegistryInterface $blogRegistry Blog registry.
     * @param RendererInterface $renderer Renderer.
     */
    public function __construct(
        private readonly BlogRegistryInterface $blogRegistry,
        private readonly RendererInterface $renderer
    ) {
    }

    /**
     * Index action.
     *
     * @param Request $request Request.
     * @return string
     */
    public function index(Request $request): string
    {
        $filters = array_filter($request->all(), fn($value) => $value !== '');
        $posts = $this->blogRegistry->getPosts($filters);
        $tags = $this->blogRegistry->getTags();
        $recentPosts = $this->blogRegistry->getPosts(['limit' => 5]);

        return $this->renderer->render('blog/index.html.twig', [
            'filters' => $filters,
            'posts' => $posts,
            'tags' => $tags,
            'recentPosts' => $recentPosts,
        ]);
    }

    /**
     * Show action.
     *
     * @param string $slug Slug of the blog post.
     * @return string
     */
    public function show(string $slug): string
    {
        $post = $this->blogRegistry->getPost($slug);
        $tags = $this->blogRegistry->getTags();
        $recentPosts = $this->blogRegistry->getPosts(['limit' => 5]);

        return $this->renderer->render('blog/show.html.twig', [
            'post' => $post,
            'tags' => $tags,
            'recentPosts' => $recentPosts,
        ]);
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
        $filters = array_filter($request->all(), fn($value) => $value !== '');
        $filters['tag'] = $tag;
        $posts = $this->blogRegistry->getPosts($filters);
        $tags = $this->blogRegistry->getTags();
        $recentPosts = $this->blogRegistry->getPosts(['limit' => 5, 'tag' => $tag]);

        return $this->renderer->render('blog/index.html.twig', [
            'filters' => $filters,
            'posts' => $posts,
            'tags' => $tags,
            'recentPosts' => $recentPosts,
        ]);
    }

    /**
     * API index action.
     *
     * @param Request $request Request.
     * @return array
     */
    public function api_index(Request $request): array
    {
        $filters = array_filter($request->all(), fn($value) => $value !== '');

        return $this->blogRegistry->getPosts($filters);
    }
}
