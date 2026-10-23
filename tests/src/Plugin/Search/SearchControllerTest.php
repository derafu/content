<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent\Plugin\Search;

use Derafu\Content\ContentAuthor;
use Derafu\Content\ContentBag;
use Derafu\Content\ContentConfig;
use Derafu\Content\ContentContext;
use Derafu\Content\ContentLoader;
use Derafu\Content\ContentService;
use Derafu\Content\ContentSplFileInfo;
use Derafu\Content\ContentTag;
use Derafu\Content\Exception\ContentNotFoundException;
use Derafu\Content\Plugin\Docs\DocsDoc;
use Derafu\Content\Plugin\Docs\DocsPlugin;
use Derafu\Content\Plugin\Docs\DocsRegistry;
use Derafu\Content\Plugin\Search\OpenAiCompatibleLlmClient;
use Derafu\Content\Plugin\Search\SearchController;
use Derafu\Content\Plugin\Search\SearchEngine;
use Derafu\Content\Plugin\Search\SearchPlugin;
use Derafu\Content\Plugin\Search\SearchResultsFilter;
use Derafu\Http\Request;
use Derafu\TestsContent\Support\ContentFixtures;
use Derafu\TestsContent\Support\FixtureHttpServer;
use Derafu\TestsContent\Support\RendererFixture;
use Derafu\TestsContent\Support\RouterFixture;
use Derafu\Translation\Contract\TranslatableInterface;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

/**
 * Exercises SearchController against a real fixture HTTP server standing
 * in for the upstream search engine and a real Docs registry, so the
 * post-filter (searchable()/allowed()/unlisted() applied to the raw engine
 * results) runs for real instead of being asserted against a hand-built
 * array.
 */
#[CoversClass(SearchController::class)]
#[UsesClass(SearchPlugin::class)]
#[UsesClass(DocsPlugin::class)]
#[UsesClass(DocsRegistry::class)]
#[UsesClass(DocsDoc::class)]
#[UsesClass(ContentService::class)]
#[UsesClass(ContentAuthor::class)]
#[UsesClass(ContentBag::class)]
#[UsesClass(ContentConfig::class)]
#[UsesClass(ContentContext::class)]
#[UsesClass(ContentLoader::class)]
#[UsesClass(ContentSplFileInfo::class)]
#[UsesClass(ContentTag::class)]
#[UsesClass(ContentNotFoundException::class)]
#[UsesClass(SearchEngine::class)]
#[UsesClass(OpenAiCompatibleLlmClient::class)]
#[UsesClass(SearchResultsFilter::class)]
final class SearchControllerTest extends TestCase
{
    private static FixtureHttpServer $server;

    public static function setUpBeforeClass(): void
    {
        self::$server = FixtureHttpServer::start(19802);
    }

    public static function tearDownAfterClass(): void
    {
        self::$server->stop();
    }

    /**
     * @param array<string, mixed> $searchOptions Options of the search plugin that are added.
     */
    private function controller(string $scenario = 'results_mixed_searchability', array $searchOptions = []): SearchController
    {
        $docsPlugin = new DocsPlugin(ContentFixtures::contentContext(), ['path' => 'docs']);
        $docsPlugin->loadContent(new ContentLoader(ContentFixtures::contentPath()));

        $searchPlugin = new SearchPlugin(ContentFixtures::contentContext(), [
            'url' => self::$server->url() . '/?scenario=' . $scenario . '&text=%s',
            ...$searchOptions,
        ]);

        $contentService = ContentFixtures::contentService([
            'search' => $searchPlugin,
            'docs' => $docsPlugin,
        ]);

        return new SearchController($contentService, RendererFixture::create(RouterFixture::create()));
    }

    /**
     * The fixture engine returns 5 raw results: one fully visible doc
     * ("guide"), one searchable:false, one draft (a stale index entry
     * that later became draft), one unlisted, and one whose uri no
     * longer exists in the registry at all — only "guide" must survive.
     */
    public function testApiIndexFiltersOutNonSearchableAndNoLongerAllowedResults(): void
    {
        $request = new Request('GET', 'http://localhost/api/content/search.json?q=hola');

        $results = $this->controller()->api_index($request);

        $uris = array_column($results, 'uri');

        $this->assertSame(['guide'], $uris);
    }

    public function testAResultWhoseTypeOrUriIsNotATextIsDropped(): void
    {
        $request = new Request('GET', 'http://localhost/api/content/search.json?q=hola');

        $results = $this->controller('results_bad_types')->api_index($request);

        $this->assertSame(['guide'], array_column($results, 'uri'));
    }

    public function testAQueryThatIsNotATextIsAnErrorWithStatus400(): void
    {
        $request = (new Request('GET', 'http://localhost/api/content/search.json'))->withQueryParams(['q' => ['a']]);

        $exception = null;
        try {
            $this->controller()->api_index($request);
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(InvalidArgumentException::class, $exception);
        $this->assertInstanceOf(TranslatableInterface::class, $exception);
        $this->assertSame(400, $exception->getCode());
        $this->assertSame('The query "q" must be a text, array given.', $exception->getMessage());
    }

    public function testTheLlmEndpointWithoutAConfiguredLlmIsAnError(): void
    {
        $request = new Request('GET', 'http://localhost/api/content/search/llm?q=hola');

        $exception = null;
        try {
            $this->controller()->llm_query($request);
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertInstanceOf(TranslatableInterface::class, $exception);
        $this->assertSame('The "search" plugin has no LLM configured: set "llm_url" and "llm_model".', $exception->getMessage());
    }

    /**
     * @return array<string, mixed>
     */
    private static function llm(string $scenario): array
    {
        return [
            'llm_url' => self::$server->url(),
            'llm_model' => 'test-model',
            'llm_completions_path' => '/v1/chat/completions?scenario=' . $scenario,
        ];
    }

    public function testTheLlmEndpointAnswersWithTheTextOfTheModel(): void
    {
        $request = new Request('GET', 'http://localhost/api/content/search/llm?q=hola');

        $answer = $this->controller('results_ok', self::llm('chat_ok'))->llm_query($request);

        $this->assertSame(
            ['choices' => [['delta' => ['content' => 'Hello, this is a test answer.']]]],
            $answer
        );
    }

    public function testTheLlmEndpointCanAnswerWithTheHtmlOfTheTemplate(): void
    {
        $request = (new Request('GET', 'http://localhost/api/content/search/llm'))
            ->withQueryParams(['q' => 'hola', 'html' => '1']);

        $answer = $this->controller('results_ok', self::llm('chat_ok'))->llm_query($request);

        $this->assertIsArray($answer['choices']);
        $this->assertIsArray($answer['choices'][0]);
        $this->assertIsArray($answer['choices'][0]['delta']);
        $content = $answer['choices'][0]['delta']['content'];
        $this->assertIsString($content);
        $this->assertStringContainsString('Hello, this is a test answer.', $content);
        $this->assertStringContainsString('<', $content);
    }

    public function testTheLlmEndpointNeedsAQuery(): void
    {
        $exception = null;
        try {
            $this->controller('results_ok', self::llm('chat_ok'))
                ->llm_query(new Request('GET', 'http://localhost/api/content/search/llm'));
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(InvalidArgumentException::class, $exception);
        $this->assertSame(400, $exception->getCode());
        $this->assertSame('Query is required.', $exception->getMessage());
    }

    public function testTheApiNeedsAQuery(): void
    {
        $exception = null;
        try {
            $this->controller('results_ok')->api_index(new Request('GET', 'http://localhost/api/content/search.json'));
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(InvalidArgumentException::class, $exception);
        $this->assertSame(400, $exception->getCode());
    }

    public function testThePageShowsTheResultsOfTheQuery(): void
    {
        $request = (new Request('GET', 'http://localhost/search'))->withQueryParams(['q' => 'hola']);

        $html = $this->controller('results_ok')->index($request);

        $this->assertStringContainsString('hola', $html);
    }

    public function testThePageWithoutQueryHasNoResults(): void
    {
        $html = $this->controller('results_ok')->index(new Request('GET', 'http://localhost/search'));

        $this->assertStringNotContainsString('Results for:', $html);
    }
}
