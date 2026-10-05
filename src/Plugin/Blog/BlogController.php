<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Plugin\Blog;

use DateTimeImmutable;
use DateTimeZone;
use Derafu\Config\Contract\OptionsInterface;
use Derafu\Content\Abstract\AbstractContentController;
use Derafu\Content\ContentTag;
use Derafu\Content\Contract\ContentItemInterface;
use Derafu\Content\Contract\ContentServiceInterface;
use Derafu\Http\Enum\HttpStatus;
use Derafu\Http\Request;
use Derafu\Http\Response;
use Derafu\Renderer\Contract\RendererInterface;
use Derafu\Routing\Contract\RouterInterface;

/**
 * Blog controller.
 */
class BlogController extends AbstractContentController
{
    /**
     * Seconds a reader or a cache may keep the feed without asking again.
     */
    private const FEED_MAX_AGE = 300;

    /**
     * Version of the format of the feed, part of its ETag. Raise it when the
     * way the feed is written changes, so readers do not keep an old copy.
     */
    private const FEED_FORMAT_VERSION = 1;

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

        $format = $this->getPreferredFormat($request);
        $uri = str_replace('.' . $format, '', $post);

        $post = $plugin->registry()->get($uri);

        if ($format === 'json') {
            return $this->jsonResponse($post, $this->renderer, $this->router, 'blog/show.md.twig', [
                'plugin' => $plugin,
                'post' => $post,
            ]);
        } else {
            $recentPosts = $plugin->registry()->filter([
                'limit' => $plugin->options()->get('blogSidebarCount'),
            ]);
            $tags = $plugin->registry()->tags();
            $archives = $plugin->registry()->archives();

            return $this->renderer->render(
                'blog/show.' . $format . '.twig',
                [
                    'plugin' => $plugin,
                    'post' => $post,
                    'previous' => $plugin->registry()->previous($post->uri()),
                    'next' => $plugin->registry()->next($post->uri()),
                    'recentPosts' => $recentPosts,
                    'tags' => $tags,
                    'archives' => $archives,
                ]
            );
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

        $options = $plugin->options()->get('feedOptions');

        // The only filter a reader can ask for is the tag. Anything else in
        // the query string is ignored, so the feed can not be asked for
        // arbitrary filters.
        $filters = [];
        $tag = $request->query('tag');
        if (is_string($tag) && $tag !== '') {
            $filters['tag'] = $tag;
        }

        // The posts of the feed are the latest ones, whatever the order in
        // which the registry returns them.
        $posts = $plugin->registry()->filter($filters);
        usort($posts, fn ($a, $b) => $b->date() <=> $a->date());
        $posts = array_slice($posts, 0, $this->feedLimit($request, $options));

        // The feed is as recent as its newest post, which does not change
        // between requests, unlike the current time.
        $lastBuildDate = isset($posts[0]) ? $posts[0]->date() : null;

        if ($options->get('sortPosts') === 'ascending') {
            $posts = array_reverse($posts);
        }

        $response = new Response();
        $response->withHeader('Content-Type', 'application/rss+xml; charset=UTF-8');
        $response->withHeader('Cache-Control', 'public, max-age=' . self::FEED_MAX_AGE);

        // A reader that already has the current version of the feed does not
        // need it again. This is decided before rendering, which is the
        // expensive part.
        $etag = $this->etag($plugin, $options, $filters, $posts);
        $response->withHeader('ETag', $etag);

        $lastModified = $this->lastModified($posts);
        if ($lastModified !== null) {
            $response->withHeader('Last-Modified', gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
        }

        if ($this->isNotModified($request, $etag, $lastModified)) {
            return $response->withHttpStatus(HttpStatus::NOT_MODIFIED);
        }

        $xml = $this->renderer->render('blog/rss.xml.twig', [
            'plugin' => $plugin,
            'posts' => $posts,
            'feed' => [...$options->all(), 'lastBuildDate' => $lastBuildDate],
        ]);

        $url = rtrim($plugin->context()->config()->url(), '/');

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

    /**
     * Weak ETag of the feed.
     *
     * It is calculated from what the feed is made of, without rendering it:
     * the content of every post in it (so an edit changes it, even if the date
     * of the update is the same), their order, the options of the feed, the
     * tag that was asked for and the title and URL of the website. It is weak
     * because it says the feed means the same, not that it is the same bytes.
     *
     * A change in a template, or in a translation, is not part of it: when
     * the format of the feed changes in this package, raise FEED_FORMAT_VERSION.
     *
     * @param BlogPlugin $plugin Plugin of the blog.
     * @param OptionsInterface $options Options of the feed.
     * @param array<string, string> $filters Filters asked for by the reader.
     * @param array<ContentItemInterface> $posts Posts of the feed, in order.
     * @return string
     */
    private function etag(
        BlogPlugin $plugin,
        OptionsInterface $options,
        array $filters,
        array $posts
    ): string {
        $config = $plugin->context()->config();

        return 'W/"' . hash('xxh128', serialize([
            self::FEED_FORMAT_VERSION,
            $options->all(),
            $filters,
            $config->title(),
            $config->url(),
            array_map(
                fn (ContentItemInterface $post) => [$post->uri(), $post->checksum()],
                $posts
            ),
        ])) . '"';
    }

    /**
     * Whether the reader already has the current version of the feed.
     *
     * When the reader sends `If-None-Match` only that counts, and
     * `If-Modified-Since` is ignored (RFC 9110, section 13.1.3): a reader that
     * knows the ETag of an older version must get the new one, even if the
     * date it also sends would match. Otherwise the date decides.
     *
     * @param Request $request Request.
     * @param string $etag Current ETag of the feed.
     * @param int|null $lastModified Timestamp of the last update of the feed.
     * @return bool
     */
    private function isNotModified(Request $request, string $etag, ?int $lastModified): bool
    {
        $ifNoneMatch = trim($request->header('If-None-Match'));
        if ($ifNoneMatch !== '') {
            return $this->matchesETag($ifNoneMatch, $etag);
        }

        return $lastModified !== null && $this->isNotModifiedSince($request, $lastModified);
    }

    /**
     * Whether an `If-None-Match` header holds the ETag of the feed.
     *
     * The header can be a list or `*`, and the comparison is weak: the `W/`
     * prefix is not considered (RFC 9110, section 8.8.3.2).
     *
     * @param string $header Value of the header.
     * @param string $etag Current ETag of the feed.
     * @return bool
     */
    private function matchesETag(string $header, string $etag): bool
    {
        $opaque = fn (string $tag): string => str_starts_with($tag, 'W/') ? substr($tag, 2) : $tag;

        foreach (explode(',', $header) as $candidate) {
            $candidate = trim($candidate);

            if ($candidate === '*' || $opaque($candidate) === $opaque($etag)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Moment of the last update of the posts of the feed.
     *
     * It is the most recent update of any of them, not just the date of the
     * newest one: editing a post that is already in the feed changes what a
     * reader sees. It is never in the future.
     *
     * @param array<ContentItemInterface> $posts Posts of the feed.
     * @return int|null Timestamp, or `null` if the feed has no posts.
     */
    private function lastModified(array $posts): ?int
    {
        if ($posts === []) {
            return null;
        }

        $timestamp = max(array_map(
            fn (ContentItemInterface $post) => $post->last_update()->getTimestamp(),
            $posts
        ));

        return min($timestamp, time());
    }

    /**
     * Whether the reader already has the version of the feed of that moment.
     *
     * It is decided by the `If-Modified-Since` header, which must be a date in
     * the HTTP format. Any other value is ignored.
     *
     * @param Request $request Request.
     * @param int $lastModified Timestamp of the last update of the feed.
     * @return bool
     */
    private function isNotModifiedSince(Request $request, int $lastModified): bool
    {
        $since = DateTimeImmutable::createFromFormat(
            'D, d M Y H:i:s \G\M\T',
            $request->header('If-Modified-Since'),
            new DateTimeZone('UTC')
        );

        if ($since === false || (DateTimeImmutable::getLastErrors()['warning_count'] ?? 0) > 0) {
            return false;
        }

        return $lastModified <= $since->getTimestamp();
    }

    /**
     * Number of posts of the feed.
     *
     * It is the `limit` option of the feed, unless the reader asks for another
     * one with `?limit=`: only a whole number of at least 1 is accepted, and
     * never more than the `maxLimit` option. Any other value is ignored.
     *
     * @param Request $request Request.
     * @param OptionsInterface $options Options of the feed.
     * @return int
     */
    private function feedLimit(Request $request, OptionsInterface $options): int
    {
        $limit = max(1, (int) $options->get('limit'));

        $requested = $request->query('limit');
        if (is_string($requested) && ctype_digit($requested) && (int) $requested >= 1) {
            $limit = (int) $requested;
        }

        return min($limit, max(1, (int) $options->get('maxLimit')));
    }
}
