<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent\Plugin\Blog;

use Derafu\Content\Abstract\AbstractContentController;
use Derafu\Content\ContentBag;
use Derafu\Content\ContentConfig;
use Derafu\Content\ContentContext;
use Derafu\Content\ContentLoader;
use Derafu\Content\ContentService;
use Derafu\Content\ContentSplFileInfo;
use Derafu\Content\ContentTag;
use Derafu\Content\Plugin\Blog\BlogController;
use Derafu\Content\Plugin\Blog\BlogPlugin;
use Derafu\Content\Plugin\Blog\BlogPost;
use Derafu\Content\Plugin\Blog\BlogRegistry;
use Derafu\File\Directory;
use Derafu\Http\Request;
use Derafu\Http\Response;
use Derafu\Renderer\Contract\EngineInterface;
use Derafu\Renderer\Contract\RendererInterface;
use Derafu\Routing\ValueObject\RequestContext;
use Derafu\TestsContent\Support\ContentFixtures;
use Derafu\TestsContent\Support\RendererFixture;
use Derafu\TestsContent\Support\RouterFixture;
use Derafu\Translation\Contract\TranslatableInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

/**
 * The feed takes the name and the URL of the website from the configuration
 * of the content, not from a `homepage` route that every website using the
 * package would have to define: the router of these tests has none.
 *
 * It uses a post of its own, with root-relative links and images, because the
 * fixture posts have none, so what makes them absolute was never checked.
 */
#[\PHPUnit\Framework\Attributes\UsesClass(\Derafu\Content\Provider\ContentTemplatePathProvider::class)]
#[CoversClass(BlogController::class)]
#[CoversClass(AbstractContentController::class)]
#[UsesClass(BlogPlugin::class)]
#[UsesClass(BlogRegistry::class)]
#[UsesClass(BlogPost::class)]
#[UsesClass(ContentService::class)]
#[UsesClass(ContentBag::class)]
#[UsesClass(ContentConfig::class)]
#[UsesClass(ContentContext::class)]
#[UsesClass(ContentLoader::class)]
#[UsesClass(ContentSplFileInfo::class)]
#[UsesClass(ContentTag::class)]
final class BlogRssTest extends TestCase
{
    private string $contentPath;

    protected function setUp(): void
    {
        $this->contentPath = sys_get_temp_dir() . '/blog-rss-test-' . uniqid();
        mkdir($this->contentPath . '/blog', 0777, true);

        file_put_contents(
            $this->contentPath . '/blog/2026-03-01-post-with-links.md',
            <<<'MARKDOWN'
            ---
            title: "Post with links"
            description: "A post with links and an image relative to the root."
            ---
            Read the [guide](/docs/guide) and look at the image: ![logo](/img/logo.png).
            Filler content to exceed the minimum indexable length of one hundred
            characters in total, like the other test posts.
            MARKDOWN
        );
    }

    protected function tearDown(): void
    {
        Directory::remove($this->contentPath);
    }

    /**
     * @param array<string, mixed> $feedOptions Options of the feed.
     * @param array<string, mixed> $query Query parameters of the request.
     * @param array<string, string> $headers Headers of the request.
     */
    private function respond(
        string $url,
        array $feedOptions = [],
        array $query = [],
        array $headers = []
    ): Response {
        $context = ContentFixtures::contentContext('My Site', $url);

        $plugin = new BlogPlugin($context, ['path' => 'blog', 'feedOptions' => $feedOptions]);
        $plugin->loadContent(new ContentLoader($this->contentPath));

        $router = RouterFixture::create();
        $router->setContext(new RequestContext(pathInfo: '/blog/rss.xml'));

        $controller = new BlogController(
            new ContentService($context, new \Derafu\TestsContent\Support\FixturePluginLoader(['blog' => $plugin])),
            RendererFixture::create($router),
            $router
        );

        $request = (new Request('GET', 'http://localhost/blog/rss.xml'))->withQueryParams($query);
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $controller->rss($request);
    }

    /**
     * @param array<string, mixed> $feedOptions Options of the feed.
     * @param array<string, mixed> $query Query parameters of the request.
     */
    private function rss(string $url, array $feedOptions = [], array $query = []): string
    {
        return (string) $this->respond($url, $feedOptions, $query)->getBody();
    }

    public function testTheTitleOfTheFeedUsesTheTitleOfTheWebsite(): void
    {
        $xml = $this->rss('https://example.com');

        $this->assertStringContainsString('<title>Blog of My Site</title>', $xml);
    }

    public function testRootRelativeLinksAndImagesBecomeAbsoluteWithTheConfiguredUrl(): void
    {
        $xml = $this->rss('https://example.com');

        $this->assertStringContainsString('href="https://example.com/docs/guide"', $xml);
        $this->assertStringContainsString('src="https://example.com/img/logo.png"', $xml);
    }

    public function testATrailingSlashInTheConfiguredUrlDoesNotDoubleTheSlash(): void
    {
        $xml = $this->rss('https://example.com/');

        $this->assertStringContainsString('href="https://example.com/docs/guide"', $xml);
        $this->assertStringNotContainsString('https://example.com//', $xml);
    }

    /**
     * Creates posts "Post 01" ... "Post NN", published on consecutive days of
     * April 2026, all after the post created by setUp(). The first three have
     * the "demo" tag.
     */
    private function createPosts(int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $number = sprintf('%02d', $i);
            $tags = $i <= 3 ? 'tags: ["demo"]' . "\n" : '';

            file_put_contents(
                $this->contentPath . '/blog/2026-04-' . $number . '-post-' . $number . '.md',
                "---\ntitle: \"Post {$number}\"\nlast_update: 2026-05-{$number}\n{$tags}---\n"
                . "Filler content of post {$number} to exceed the minimum "
                . "indexable length of one hundred characters in total, like the other test posts.\n"
            );
        }
    }

    /**
     * @return array<string> The titles of the items of the feed, in order.
     */
    private function itemTitles(string $xml): array
    {
        preg_match_all('#<item>\s*<title>(.*?)</title>#s', $xml, $matches);

        return $matches[1];
    }

    public function testByDefaultTheFeedHasTheTenLatestPostsNewestFirst(): void
    {
        $this->createPosts(12);

        $titles = $this->itemTitles($this->rss('https://example.com'));

        $this->assertSame(
            ['Post 12', 'Post 11', 'Post 10', 'Post 09', 'Post 08', 'Post 07', 'Post 06', 'Post 05', 'Post 04', 'Post 03'],
            $titles
        );
    }

    public function testTheLimitOptionOfTheFeedIsUsed(): void
    {
        $this->createPosts(12);

        $titles = $this->itemTitles($this->rss('https://example.com', ['limit' => 3]));

        $this->assertSame(['Post 12', 'Post 11', 'Post 10'], $titles);
    }

    public function testTheLimitCanBeRequestedButNeverAboveTheMaximum(): void
    {
        $this->createPosts(12);
        $options = ['limit' => 2, 'maxLimit' => 4];

        $this->assertCount(4, $this->itemTitles($this->rss('https://example.com', $options, ['limit' => '100'])));
        $this->assertCount(3, $this->itemTitles($this->rss('https://example.com', $options, ['limit' => '3'])));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidLimitsProvider(): array
    {
        return [
            'text' => ['abc'],
            'zero' => ['0'],
            'negative' => ['-5'],
            'decimal' => ['2.5'],
            'empty' => [''],
        ];
    }

    #[DataProvider('invalidLimitsProvider')]
    public function testAnInvalidRequestedLimitFallsBackToTheDefaultOne(string $limit): void
    {
        $this->createPosts(12);

        $titles = $this->itemTitles($this->rss('https://example.com', ['limit' => 3], ['limit' => $limit]));

        $this->assertCount(3, $titles);
    }

    public function testTheTagFilterIsAccepted(): void
    {
        $this->createPosts(12);

        $titles = $this->itemTitles($this->rss('https://example.com', [], ['tag' => 'demo']));

        $this->assertSame(['Post 03', 'Post 02', 'Post 01'], $titles);
    }

    /**
     * Anything else in the query string is ignored: it must not let a reader
     * ask the feed for arbitrary filters.
     */
    public function testOtherQueryParametersAreIgnored(): void
    {
        $this->createPosts(12);

        $titles = $this->itemTitles($this->rss(
            'https://example.com',
            ['limit' => 3],
            ['search' => 'zzz-nothing-matches-this', 'author' => 'nobody', 'category' => 'x', 'page' => '2']
        ));

        $this->assertSame(['Post 12', 'Post 11', 'Post 10'], $titles);
    }

    public function testTheLastBuildDateIsTheDateOfTheNewestPostNotTheCurrentTime(): void
    {
        $this->createPosts(12);

        $xml = $this->rss('https://example.com');

        if (preg_match('#<lastBuildDate>(.*?)</lastBuildDate>#', $xml, $build) !== 1) {
            $this->fail('The feed has no lastBuildDate.');
        }
        if (preg_match('#<item>.*?<pubDate>(.*?)</pubDate>#s', $xml, $newest) !== 1) {
            $this->fail('The feed has no posts.');
        }
        $this->assertSame($newest[1], $build[1]);
    }

    public function testTheLastBuildDateIsTheNewestPostEvenWhenListedOldestFirst(): void
    {
        $this->createPosts(12);

        $xml = $this->rss('https://example.com', ['sortPosts' => 'ascending']);

        if (preg_match('#<lastBuildDate>(.*?)</lastBuildDate>#', $xml, $build) !== 1) {
            $this->fail('The feed has no lastBuildDate.');
        }
        preg_match_all('#<pubDate>(.*?)</pubDate>#', $xml, $dates);

        $this->assertSame(end($dates[1]), $build[1]);
    }

    public function testAFeedWithoutPostsHasNoLastBuildDate(): void
    {
        $this->createPosts(2);

        $xml = $this->rss('https://example.com', [], ['tag' => 'a-tag-nobody-uses']);

        $this->assertSame([], $this->itemTitles($xml));
        $this->assertStringNotContainsString('<lastBuildDate>', $xml);
        $this->assertNotFalse(simplexml_load_string($xml), 'The empty feed is not well-formed XML.');
    }

    public function testSortPostsAscendingListsTheSelectedPostsOldestFirst(): void
    {
        $this->createPosts(12);

        $titles = $this->itemTitles($this->rss('https://example.com', ['limit' => 3, 'sortPosts' => 'ascending']));

        // The three newest posts are still the ones selected.
        $this->assertSame(['Post 10', 'Post 11', 'Post 12'], $titles);
    }

    public function testTheLanguageIsOnlyWrittenWhenItIsConfigured(): void
    {
        $this->assertStringNotContainsString('<language>', $this->rss('https://example.com'));
        $this->assertStringContainsString(
            '<language>es-CL</language>',
            $this->rss('https://example.com', ['language' => 'es-CL'])
        );
    }

    public function testTitleDescriptionAndCopyrightOfTheFeedCanBeConfigured(): void
    {
        $xml = $this->rss('https://example.com', [
            'title' => 'News & more',
            'description' => 'The latest of the site',
            'copyright' => '© 2026 My Site',
        ]);

        $this->assertStringContainsString('<title>News &amp; more</title>', $xml);
        $this->assertStringContainsString('<description>The latest of the site</description>', $xml);
        $this->assertStringContainsString('<copyright>© 2026 My Site</copyright>', $xml);
    }

    public function testThereIsNoCopyrightWhenItIsNotConfigured(): void
    {
        $this->assertStringNotContainsString('<copyright>', $this->rss('https://example.com'));
    }

    private function lastModifiedOf(string $date): string
    {
        $timestamp = strtotime($date . ' UTC');
        $this->assertNotFalse($timestamp);

        return gmdate('D, d M Y H:i:s', $timestamp) . ' GMT';
    }

    public function testTheFeedSaysWhenItWasLastModified(): void
    {
        $this->createPosts(12);

        $response = $this->respond('https://example.com');

        // Posts 12 ... 03 are in the feed: the newest update is Post 12's.
        $this->assertSame($this->lastModifiedOf('2026-05-12'), $response->getHeaderLine('Last-Modified'));
    }

    /**
     * Editing a post that is already in the feed (and not only publishing a
     * new one) changes what the reader sees, so it must change the date.
     */
    public function testLastModifiedFollowsTheUpdateOfAnyPostInTheFeed(): void
    {
        $this->createPosts(12);
        file_put_contents(
            $this->contentPath . '/blog/2026-04-05-post-05.md',
            "---\ntitle: \"Post 05\"\nlast_update: 2026-06-01\n---\n"
            . "Edited filler content of post 05 to exceed the minimum "
            . "indexable length of one hundred characters in total, like the other test posts.\n"
        );

        $response = $this->respond('https://example.com');

        $this->assertSame($this->lastModifiedOf('2026-06-01'), $response->getHeaderLine('Last-Modified'));
    }

    public function testAPostOutsideTheFeedDoesNotChangeLastModified(): void
    {
        $this->createPosts(12);
        file_put_contents(
            $this->contentPath . '/blog/2026-04-01-post-01.md',
            "---\ntitle: \"Post 01\"\nlast_update: 2026-08-30\n---\n"
            . "Edited filler content of post 01 to exceed the minimum "
            . "indexable length of one hundred characters in total, like the other test posts.\n"
        );

        // Post 01 is not among the ten latest, so editing it changes nothing.
        $response = $this->respond('https://example.com');

        $this->assertSame($this->lastModifiedOf('2026-05-12'), $response->getHeaderLine('Last-Modified'));
    }

    public function testLastModifiedIsNeverInTheFuture(): void
    {
        $this->createPosts(2);
        file_put_contents(
            $this->contentPath . '/blog/2026-04-02-post-02.md',
            "---\ntitle: \"Post 02\"\nlast_update: 2099-01-01\n---\n"
            . "Filler content of post 02 to exceed the minimum indexable "
            . "length of one hundred characters in total, like the other test posts.\n"
        );

        $response = $this->respond('https://example.com');

        $header = $response->getHeaderLine('Last-Modified');
        $this->assertNotSame('', $header, 'The feed has no Last-Modified.');
        $this->assertLessThanOrEqual(time(), strtotime($header));
    }

    public function testTheFeedCanBeCachedForAFewMinutes(): void
    {
        $this->createPosts(3);

        $response = $this->respond('https://example.com');

        $this->assertSame('public, max-age=300', $response->getHeaderLine('Cache-Control'));
    }

    public function testItAnswers304WithoutBodyWhenTheReaderAlreadyHasThatVersion(): void
    {
        $this->createPosts(12);
        $lastModified = $this->lastModifiedOf('2026-05-12');

        $response = $this->respond('https://example.com', [], [], ['If-Modified-Since' => $lastModified]);

        $this->assertSame(304, $response->getStatusCode());
        $this->assertSame('', (string) $response->getBody());
        $this->assertSame($lastModified, $response->getHeaderLine('Last-Modified'));
        $this->assertSame('public, max-age=300', $response->getHeaderLine('Cache-Control'));
    }

    public function testItAnswers304WhenTheVersionOfTheReaderIsNewerThanTheFeed(): void
    {
        $this->createPosts(12);

        $response = $this->respond(
            'https://example.com',
            [],
            [],
            ['If-Modified-Since' => $this->lastModifiedOf('2026-09-01')]
        );

        $this->assertSame(304, $response->getStatusCode());
    }

    public function testItAnswersTheFeedWhenTheVersionOfTheReaderIsOlder(): void
    {
        $this->createPosts(12);

        $response = $this->respond(
            'https://example.com',
            [],
            [],
            ['If-Modified-Since' => $this->lastModifiedOf('2026-05-11')]
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(10, $this->itemTitles((string) $response->getBody()));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function malformedDatesProvider(): array
    {
        return [
            'text' => ['yesterday'],
            'iso format' => ['2026-09-01T00:00:00Z'],
            'empty' => [''],
            'garbage' => ['not a date at all'],
        ];
    }

    #[DataProvider('malformedDatesProvider')]
    public function testAMalformedIfModifiedSinceIsIgnored(string $value): void
    {
        $this->createPosts(12);

        $response = $this->respond('https://example.com', [], [], ['If-Modified-Since' => $value]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(10, $this->itemTitles((string) $response->getBody()));
    }

    public function testAFeedWithoutPostsHasNoLastModifiedAndIsNeverAnswered304(): void
    {
        $this->createPosts(2);

        $response = $this->respond(
            'https://example.com',
            [],
            ['tag' => 'a-tag-nobody-uses'],
            ['If-Modified-Since' => $this->lastModifiedOf('2030-01-01')]
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertFalse($response->hasHeader('Last-Modified'));
    }

    public function testTheTagIsPartOfWhatTheDateIsCalculatedFrom(): void
    {
        $this->createPosts(12);

        // With the tag "demo" only Posts 01 to 03 are in the feed.
        $response = $this->respond('https://example.com', [], ['tag' => 'demo']);

        $this->assertSame($this->lastModifiedOf('2026-05-03'), $response->getHeaderLine('Last-Modified'));
    }

    /**
     * @param array<string, mixed> $feedOptions Options of the feed.
     * @param array<string, mixed> $query Query parameters of the request.
     */
    private function etag(string $url = 'https://example.com', array $feedOptions = [], array $query = []): string
    {
        return $this->respond($url, $feedOptions, $query)->getHeaderLine('ETag');
    }

    public function testTheFeedHasAWeakETag(): void
    {
        $this->createPosts(3);

        $this->assertMatchesRegularExpression('#^W/"[0-9a-f]{32}"$#', $this->etag());
    }

    public function testTheETagIsTheSameWhileNothingChanges(): void
    {
        $this->createPosts(12);
        $etag = $this->etag();

        $this->assertNotSame('', $etag, 'The feed has no ETag.');
        $this->assertSame($etag, $this->etag());
    }

    /**
     * Everything that makes the feed different must make the ETag different,
     * including what Last-Modified can not see: it only knows about the posts.
     *
     * @return array<string, array{array<string, mixed>, array<string, mixed>, string}>
     */
    public static function whatChangesTheFeedProvider(): array
    {
        return [
            'another tag' => [[], ['tag' => 'demo'], 'https://example.com'],
            'another number of posts' => [[], ['limit' => '3'], 'https://example.com'],
            'another order' => [['sortPosts' => 'ascending'], [], 'https://example.com'],
            'another title of the feed' => [['title' => 'Another title'], [], 'https://example.com'],
            'another description of the feed' => [['description' => 'Another description'], [], 'https://example.com'],
            'a language' => [['language' => 'es-CL'], [], 'https://example.com'],
            'a copyright' => [['copyright' => '© Otro'], [], 'https://example.com'],
            'another url of the website' => [[], [], 'https://otro.example.com'],
        ];
    }

    /**
     * @param array<string, mixed> $feedOptions
     * @param array<string, mixed> $query
     */
    #[DataProvider('whatChangesTheFeedProvider')]
    public function testTheETagChangesWhenTheFeedIsDifferent(array $feedOptions, array $query, string $url): void
    {
        $this->createPosts(12);

        $this->assertNotSame($this->etag(), $this->etag($url, $feedOptions, $query));
    }

    public function testTheETagChangesWhenAPostOfTheFeedIsEdited(): void
    {
        $this->createPosts(12);
        $before = $this->etag();

        // The date of the update is the same: only the text changes, which
        // Last-Modified would not notice.
        file_put_contents(
            $this->contentPath . '/blog/2026-04-12-post-12.md',
            "---\ntitle: \"Post 12\"\nlast_update: 2026-05-12\n---\n"
            . "Corrected text of post 12, with the same dates, to exceed the minimum "
            . "indexable length of one hundred characters in total, like the other test posts.\n"
        );

        $this->assertNotSame($before, $this->etag());
    }

    public function testTheETagDoesNotChangeWhenAPostOutsideTheFeedIsEdited(): void
    {
        $this->createPosts(12);
        $before = $this->etag();
        $this->assertNotSame('', $before, 'The feed has no ETag.');

        file_put_contents(
            $this->contentPath . '/blog/2026-04-01-post-01.md',
            "---\ntitle: \"Post 01\"\nlast_update: 2026-05-01\n---\n"
            . "Corrected text of post 01, which is not among the last ten, to exceed the "
            . "minimum indexable length of one hundred characters in total, like the other test posts.\n"
        );

        $this->assertSame($before, $this->etag());
    }

    public function testTheETagChangesWhenAPostIsPublished(): void
    {
        $this->createPosts(5);
        $before = $this->etag();

        file_put_contents(
            $this->contentPath . '/blog/2026-04-20-new-post.md',
            "---\ntitle: \"New post\"\nlast_update: 2026-05-20\n---\n"
            . "Filler content of the new post to exceed the minimum indexable length of "
            . "one hundred characters in total, like the other test posts.\n"
        );

        $this->assertNotSame($before, $this->etag());
    }

    public function testItAnswers304WithoutBodyWhenTheETagMatches(): void
    {
        $this->createPosts(12);
        $etag = $this->etag();

        $response = $this->respond('https://example.com', [], [], ['If-None-Match' => $etag]);

        $this->assertSame(304, $response->getStatusCode());
        $this->assertSame('', (string) $response->getBody());
        $this->assertSame($etag, $response->getHeaderLine('ETag'));
        $this->assertSame($this->lastModifiedOf('2026-05-12'), $response->getHeaderLine('Last-Modified'));
        $this->assertSame('public, max-age=300', $response->getHeaderLine('Cache-Control'));
    }

    /**
     * `If-None-Match` is compared weakly for a GET: the `W/` prefix does not
     * matter, and a list or `*` are valid.
     *
     * @return array<string, array{callable(string): string}>
     */
    public static function equivalentIfNoneMatchProvider(): array
    {
        return [
            'without the weak prefix' => [fn (string $etag): string => substr($etag, 2)],
            'in a list' => [fn (string $etag): string => '"other", ' . $etag . ', "another"'],
            'any' => [fn (string $etag): string => '*'],
        ];
    }

    /**
     * @param callable(string): string $header
     */
    #[DataProvider('equivalentIfNoneMatchProvider')]
    public function testItAnswers304ForEveryWayOfSendingTheSameETag(callable $header): void
    {
        $this->createPosts(12);

        $response = $this->respond('https://example.com', [], [], ['If-None-Match' => $header($this->etag())]);

        $this->assertSame(304, $response->getStatusCode());
    }

    public function testItAnswersTheFeedWhenTheETagIsNotTheCurrentOne(): void
    {
        $this->createPosts(12);

        $response = $this->respond('https://example.com', [], [], ['If-None-Match' => 'W/"0123456789abcdef0123456789abcdef"']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(10, $this->itemTitles((string) $response->getBody()));
    }

    public function testTheETagOfAnotherFeedDoesNotMatch(): void
    {
        $this->createPosts(12);
        $etagOfTheTagFeed = $this->etag('https://example.com', [], ['tag' => 'demo']);
        $this->assertNotSame('', $etagOfTheTagFeed, 'The feed has no ETag.');

        $response = $this->respond('https://example.com', [], [], ['If-None-Match' => $etagOfTheTagFeed]);

        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * When the reader sends both, only `If-None-Match` counts: a reader that
     * knows the ETag of an older version must get the new one even if the
     * date it sends would have matched.
     */
    public function testIfNoneMatchTakesPrecedenceOverIfModifiedSince(): void
    {
        $this->createPosts(12);

        $notTheCurrentETag = $this->respond(
            'https://example.com',
            [],
            [],
            [
                'If-None-Match' => 'W/"0123456789abcdef0123456789abcdef"',
                'If-Modified-Since' => $this->lastModifiedOf('2026-05-12'),
            ]
        );
        $currentETagOldDate = $this->respond(
            'https://example.com',
            [],
            [],
            [
                'If-None-Match' => $this->etag(),
                'If-Modified-Since' => $this->lastModifiedOf('2026-01-01'),
            ]
        );

        $this->assertSame(200, $notTheCurrentETag->getStatusCode());
        $this->assertSame(304, $currentETagOldDate->getStatusCode());
    }

    public function testTheETagMakesAConfigurationChangeVisibleToAReaderThatKeepsTheDate(): void
    {
        $this->createPosts(12);
        $before = $this->respond('https://example.com');

        // Same posts, so the same Last-Modified, but another title of the feed.
        $after = $this->respond(
            'https://example.com',
            ['title' => 'A new title'],
            [],
            ['If-Modified-Since' => $before->getHeaderLine('Last-Modified'), 'If-None-Match' => $before->getHeaderLine('ETag')]
        );

        $this->assertSame($before->getHeaderLine('Last-Modified'), $after->getHeaderLine('Last-Modified'));
        $this->assertSame(200, $after->getStatusCode());
        $this->assertStringContainsString('<title>A new title</title>', (string) $after->getBody());
    }

    /**
     * Runs in its own process: PHP keeps the compiled patterns (with the JIT
     * that they had when they were compiled) for the whole process, and the
     * JIT does not use the limit of backtracking.
     *
     * The renderer answers with a feed and, right after, lowers the limits of
     * PCRE, so only the rewrite of the links of the controller is affected.
     */
    #[RunInSeparateProcess]
    public function testAFeedThatPcreCanNotProcessIsAnErrorThatSaysWhich(): void
    {
        $context = ContentFixtures::contentContext('My Site', 'https://example.com');
        $plugin = new BlogPlugin($context, ['path' => 'blog']);
        $plugin->loadContent(new ContentLoader($this->contentPath));

        $router = RouterFixture::create();
        $router->setContext(new RequestContext(pathInfo: '/blog/rss.xml'));

        $renderer = new class () implements RendererInterface {
            public function render(string $template, array $data = [], array $options = []): string
            {
                ini_set('pcre.jit', '0');
                ini_set('pcre.backtrack_limit', '1');

                return '<rss><description><img src="/img/logo.png"></description></rss>';
            }

            public function renderFromString(string $content, array $data = [], array $options = []): string
            {
                return '';
            }

            public function getEngine(string $name): EngineInterface
            {
                throw new RuntimeException('There are no engines.');
            }

            public function addEngine(string $name, EngineInterface $engine): static
            {
                return $this;
            }
        };

        $controller = new BlogController(
            new ContentService($context, new \Derafu\TestsContent\Support\FixturePluginLoader(['blog' => $plugin])),
            $renderer,
            $router
        );

        $jit = (string) ini_get('pcre.jit');
        $limit = (string) ini_get('pcre.backtrack_limit');
        $exception = null;
        try {
            $controller->rss(new Request('GET', 'http://localhost/blog/rss.xml'));
        } catch (Throwable $e) {
            $exception = $e;
        } finally {
            ini_set('pcre.jit', $jit);
            ini_set('pcre.backtrack_limit', $limit);
        }

        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertInstanceOf(TranslatableInterface::class, $exception);
        $this->assertSame(
            'The content "blog/rss.xml.twig" could not be processed: Backtrack limit exhausted',
            $exception->getMessage()
        );
    }
}
