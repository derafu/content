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
use Derafu\Content\Entity\ContentMonth;
use Derafu\Content\Entity\ContentTag;
use Derafu\Http\Request;
use Derafu\Renderer\Contract\RendererInterface;

/**
 * Blog controller.
 */
class BlogController
{
    /**
     * Recent posts limit.
     */
    private const RECENT_POSTS_LIMIT = 4;

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
        $filters = array_filter($request->all(), fn ($value) => $value !== '');
        $posts = $this->blogRegistry->filter($filters);
        $recentPosts = $this->blogRegistry->filter([
            'limit' => self::RECENT_POSTS_LIMIT,
        ]);
        $tags = $this->blogRegistry->tags();
        $months = $this->blogRegistry->months();

        return $this->renderer->render('blog/index.html.twig', [
            'filters' => $filters,
            'posts' => $posts,
            'recentPosts' => $recentPosts,
            'tags' => $tags,
            'months' => $months,
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
        $post = $this->blogRegistry->get($slug);
        $recentPosts = $this->blogRegistry->filter([
            'limit' => self::RECENT_POSTS_LIMIT,
        ]);
        $tags = $this->blogRegistry->tags();
        $months = $this->blogRegistry->months();

        return $this->renderer->render('blog/show.html.twig', [
            'post' => $post,
            'recentPosts' => $recentPosts,
            'tags' => $tags,
            'months' => $months,
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
        $filters = array_filter($request->all(), fn ($value) => $value !== '');
        $tags = $this->blogRegistry->tags();
        $contentTag = $tags[$tag] ?? new ContentTag($tag);
        $filters['tag'] = $contentTag->slug();
        $posts = $this->blogRegistry->filter($filters);
        $recentPosts = $this->blogRegistry->filter([
            'tag' => $contentTag->slug(),
            'limit' => self::RECENT_POSTS_LIMIT,
        ]);

        $months = $this->blogRegistry->months();

        return $this->renderer->render('blog/tag.html.twig', [
            'filters' => $filters,
            'posts' => $posts,
            'recentPosts' => $recentPosts,
            'tags' => $tags,
            'months' => $months,
            'tag' => $contentTag,
        ]);
    }

    /**
     * Month action.
     *
     * @param Request $request Request.
     * @param string $month Month.
     * @return string
     */
    public function month(Request $request, string $month): string
    {
        $filters = array_filter($request->all(), fn ($value) => $value !== '');
        $contentMonth = new ContentMonth($month);
        $filters['year'] = $contentMonth->year();
        $filters['month'] = $contentMonth->month();
        $posts = $this->blogRegistry->filter($filters);
        $recentPosts = $this->blogRegistry->filter([
            'year' => $contentMonth->year(),
            'month' => $contentMonth->month(),
            'limit' => self::RECENT_POSTS_LIMIT,
        ]);
        $tags = $this->blogRegistry->tags();
        $months = $this->blogRegistry->months();

        return $this->renderer->render('blog/month.html.twig', [
            'filters' => $filters,
            'posts' => $posts,
            'recentPosts' => $recentPosts,
            'tags' => $tags,
            'months' => $months,
            'month' => $contentMonth,
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
        $filters = array_filter($request->all(), fn ($value) => $value !== '');

        return $this->blogRegistry->filter($filters);
    }
}
