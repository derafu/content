<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Plugin\Blog;

use Derafu\Content\ContentTag;
use Derafu\Content\Contract\ContentServiceInterface;
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
     * Constructor.
     *
     * @param ContentServiceInterface $contentService Content service.
     * @param RendererInterface $renderer Renderer.
     * @param RouterInterface $router Router.
     */
    public function __construct(
        private readonly ContentServiceInterface $contentService,
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
        $plugin = $this->contentService->plugin('blog');
        assert($plugin instanceof BlogPlugin);

        $filters = array_filter($request->all(), fn ($value) => $value !== '');
        $posts = $plugin->registry()->filter($filters);
        $recentPosts = $plugin->registry()->filter([
            'limit' => $plugin->options()->get('blogSidebarCount'),
        ]);
        $tags = $plugin->registry()->tags();
        $archives = $plugin->registry()->archives();

        return $this->renderer->render('blog/index.html.twig', [
            'plugin' => $plugin,
            'filters' => $filters,
            'posts' => $posts,
            'recentPosts' => $recentPosts,
            'tags' => $tags,
            'archives' => $archives,
        ]);
    }

    /**
     * Show action.
     *
     * @param Request $request Request.
     * @param string $post Post.
     * @return string|array
     */
    public function show(Request $request, string $post): string|array
    {
        $plugin = $this->contentService->plugin('blog');
        assert($plugin instanceof BlogPlugin);

        $preferredFormat = $request->getPreferredFormat();
        $uri = str_replace('.' . $preferredFormat, '', $post);

        $post = $plugin->registry()->get($uri);

        if ($preferredFormat === 'json') {
            return [
                'data' => $post->toArray(),
            ];
        } elseif ($preferredFormat === 'pdf') {
            return $this->renderer->render('blog/show.pdf.twig', [
                'plugin' => $plugin,
                'post' => $post,
            ]);
        } else {
            $recentPosts = $plugin->registry()->filter([
                'limit' => $plugin->options()->get('blogSidebarCount'),
            ]);
            $tags = $plugin->registry()->tags();
            $archives = $plugin->registry()->archives();

            return $this->renderer->render('blog/show.html.twig', [
                'plugin' => $plugin,
                'post' => $post,
                'previous' => $plugin->registry()->previous($post->uri()),
                'next' => $plugin->registry()->next($post->uri()),
                'recentPosts' => $recentPosts,
                'tags' => $tags,
                'archives' => $archives,
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
        $plugin = $this->contentService->plugin('blog');
        assert($plugin instanceof BlogPlugin);

        $filters = array_filter($request->all(), fn ($value) => $value !== '');
        $tags = $plugin->registry()->tags();
        $contentTag = $tags[$tag] ?? new ContentTag($tag);
        $filters['tag'] = $contentTag->slug();
        $posts = $plugin->registry()->filter($filters);
        $recentPosts = $plugin->registry()->filter([
            'tag' => $contentTag->slug(),
            'limit' => $plugin->options()->get('blogSidebarCount'),
        ]);

        $archives = $plugin->registry()->archives();

        return $this->renderer->render('blog/tag.html.twig', [
            'plugin' => $plugin,
            'filters' => $filters,
            'posts' => $posts,
            'recentPosts' => $recentPosts,
            'tags' => $tags,
            'archives' => $archives,
            'tag' => $contentTag,
        ]);
    }

    /**
     * Archive action.
     *
     * @param Request $request Request.
     * @param string $archive Archive.
     * @return string
     */
    public function archive(Request $request, string $archive): string
    {
        $plugin = $this->contentService->plugin('blog');
        assert($plugin instanceof BlogPlugin);

        $filters = array_filter($request->all(), fn ($value) => $value !== '');
        $contentArchive = new BlogArchive($archive);
        $filters['year'] = $contentArchive->year();
        $filters['month'] = $contentArchive->month();
        $posts = $plugin->registry()->filter($filters);
        $recentPosts = $plugin->registry()->filter([
            'year' => $contentArchive->year(),
            'month' => $contentArchive->month(),
            'limit' => $plugin->options()->get('blogSidebarCount'),
        ]);
        $tags = $plugin->registry()->tags();
        $archives = $plugin->registry()->archives();

        return $this->renderer->render('blog/archive.html.twig', [
            'plugin' => $plugin,
            'filters' => $filters,
            'posts' => $posts,
            'recentPosts' => $recentPosts,
            'tags' => $tags,
            'archives' => $archives,
            'archive' => $contentArchive,
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
        $plugin = $this->contentService->plugin('blog');
        assert($plugin instanceof BlogPlugin);

        $filters = array_filter($request->all(), fn ($value) => $value !== '');
        $posts = $plugin->registry()->filter($filters);

        $response = new Response();
        $response->withHeader('Content-Type', 'application/rss+xml; charset=UTF-8');

        $xml = $this->renderer->render('blog/rss.xml.twig', [
            'plugin' => $plugin,
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
