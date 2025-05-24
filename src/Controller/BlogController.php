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
use Derafu\Content\ValueObject\ContentMonth;
use Derafu\Content\ValueObject\ContentTag;
use Derafu\Http\Request;
use Derafu\Http\Response;
use Derafu\Renderer\Contract\RendererInterface;
use Derafu\Routing\Contract\RouterInterface;
use Derafu\Routing\Enum\UrlReferenceType;

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
     * @param RouterInterface $router Router.
     */
    public function __construct(
        private readonly BlogRegistryInterface $blogRegistry,
        private readonly RendererInterface $renderer,
        private readonly RouterInterface $router
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
     * @param Request $request Request.
     * @param string $uri URI of the blog post.
     * @return string|array
     */
    public function show(Request $request, string $uri): string|array
    {
        $preferredFormat = $request->getPreferredFormat();
        $uri = str_replace('.' . $preferredFormat, '', $uri);

        $post = $this->blogRegistry->get($uri);

        if ($preferredFormat === 'json') {
            return [
                'data' => $post->toArray(),
            ];
        } elseif ($preferredFormat === 'pdf') {
            return $this->renderer->render('blog/show.pdf.twig', [
                'post' => $post,
            ]);
        } else {
            $recentPosts = $this->blogRegistry->filter([
                'limit' => self::RECENT_POSTS_LIMIT,
            ]);
            $tags = $this->blogRegistry->tags();
            $months = $this->blogRegistry->months();

            return $this->renderer->render('blog/show.html.twig', [
                'post' => $post,
                'previous' => $this->blogRegistry->previous($post->uri()),
                'next' => $this->blogRegistry->next($post->uri()),
                'recentPosts' => $recentPosts,
                'tags' => $tags,
                'months' => $months,
            ]);
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
     * RSS action.
     *
     * @param Request $request Request.
     * @return Response
     */
    public function rss(Request $request): Response
    {
        $filters = array_filter($request->all(), fn ($value) => $value !== '');
        $posts = $this->blogRegistry->filter($filters);

        $response = new Response();
        $response->withHeader('Content-Type', 'application/rss+xml; charset=UTF-8');

        $xml = $this->renderer->render('blog/rss.xml.twig', [
            'posts' => $posts,
        ]);

        $url = rtrim($this->router->generate('homepage', [], UrlReferenceType::ABSOLUTE_URL), '/');

        $xml = preg_replace_callback(
            '#<(img|a)\b[^>]*(src|href)=["\'](/[^"\']*)["\']#i',
            fn ($matches) => preg_replace(
                '#(src|href)=["\']/#',
                $matches[2] . '="' . $url . '/',
                $matches[0]
            ),
            $xml
        );

        $response->getBody()->write($xml);

        return $response;
    }
}
