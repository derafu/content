<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent\Plugin\Mcp;

use Closure;
use Derafu\Content\ContentAttachment;
use Derafu\Content\ContentAuthor;
use Derafu\Content\ContentBag;
use Derafu\Content\ContentConfig;
use Derafu\Content\ContentContext;
use Derafu\Content\ContentLoader;
use Derafu\Content\ContentService;
use Derafu\Content\ContentSplFileInfo;
use Derafu\Content\ContentTag;
use Derafu\Content\Exception\ContentNotFoundException;
use Derafu\Content\Plugin\Academy\AcademyCourse;
use Derafu\Content\Plugin\Academy\AcademyLesson;
use Derafu\Content\Plugin\Academy\AcademyModule;
use Derafu\Content\Plugin\Academy\AcademyPlugin;
use Derafu\Content\Plugin\Academy\AcademyRegistry;
use Derafu\Content\Plugin\Academy\AcademyTest;
use Derafu\Content\Plugin\Academy\AcademyTestOption;
use Derafu\Content\Plugin\Academy\AcademyTestQuestion;
use Derafu\Content\Plugin\Blog\BlogPlugin;
use Derafu\Content\Plugin\Blog\BlogPost;
use Derafu\Content\Plugin\Blog\BlogRegistry;
use Derafu\Content\Plugin\Docs\DocsDoc;
use Derafu\Content\Plugin\Docs\DocsPlugin;
use Derafu\Content\Plugin\Docs\DocsRegistry;
use Derafu\Content\Plugin\Faq\FaqPlugin;
use Derafu\Content\Plugin\Faq\FaqQuestion;
use Derafu\Content\Plugin\Faq\FaqRegistry;
use Derafu\Content\Plugin\Mcp\McpController;
use Derafu\Content\Plugin\Mcp\McpPlugin;
use Derafu\Content\Plugin\Mcp\Tool\AskTool;
use Derafu\Content\Plugin\Mcp\Tool\ContentSourceResolver;
use Derafu\Content\Plugin\Mcp\Tool\GetContentTool;
use Derafu\Content\Plugin\Mcp\Tool\ListContentTool;
use Derafu\Content\Plugin\Mcp\Tool\ListTagsTool;
use Derafu\Content\Plugin\Mcp\Tool\SearchContentTool;
use Derafu\Content\Plugin\Pages\PagesPage;
use Derafu\Content\Plugin\Pages\PagesPlugin;
use Derafu\Content\Plugin\Pages\PagesRegistry;
use Derafu\Content\Plugin\Search\Exception\SearchUpstreamException;
use Derafu\Content\Plugin\Search\OpenAiCompatibleLlmClient;
use Derafu\Content\Plugin\Search\SearchEngine;
use Derafu\Content\Plugin\Search\SearchPlugin;
use Derafu\Content\Plugin\Search\SearchResultsFilter;
use Derafu\Http\Request;
use Derafu\Routing\Contract\ParserInterface;
use Derafu\Routing\Contract\RequestContextInterface;
use Derafu\Routing\Contract\RouteMatchInterface;
use Derafu\Routing\Contract\RouterInterface;
use Derafu\Routing\Enum\UrlReferenceType;
use Derafu\Routing\Exception\RouteNotFoundException;
use Derafu\TestsContent\Support\ContentFixtures;
use Derafu\TestsContent\Support\FixtureHttpServer;
use Derafu\TestsContent\Support\FixturePluginLoader;
use Derafu\TestsContent\Support\RendererFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Drives the real MCP JSON-RPC protocol (initialize → tools/list →
 * tools/call) through McpController, backed by a real DocsPlugin loaded
 * from fixture content and a real (file-based) MCP session store, which
 * pins down the persistence of the session between requests. No mocks:
 * the only "fake" here is a minimal real RouterInterface implementation
 * (there is no HTTP server, so there is nothing to generate real URLs
 * against).
 */
#[CoversClass(McpController::class)]
#[CoversClass(McpPlugin::class)]
#[CoversClass(GetContentTool::class)]
#[CoversClass(ListContentTool::class)]
#[CoversClass(ListTagsTool::class)]
#[CoversClass(SearchContentTool::class)]
#[CoversClass(AskTool::class)]
#[CoversClass(ContentSourceResolver::class)]
#[UsesClass(DocsPlugin::class)]
#[UsesClass(DocsRegistry::class)]
#[UsesClass(DocsDoc::class)]
#[UsesClass(AcademyPlugin::class)]
#[UsesClass(AcademyRegistry::class)]
#[UsesClass(AcademyCourse::class)]
#[UsesClass(AcademyLesson::class)]
#[UsesClass(AcademyModule::class)]
#[UsesClass(AcademyTest::class)]
#[UsesClass(AcademyTestOption::class)]
#[UsesClass(AcademyTestQuestion::class)]
#[UsesClass(ContentAttachment::class)]
#[UsesClass(ContentService::class)]
#[UsesClass(ContentAuthor::class)]
#[UsesClass(ContentBag::class)]
#[UsesClass(ContentConfig::class)]
#[UsesClass(ContentContext::class)]
#[UsesClass(ContentLoader::class)]
#[UsesClass(ContentSplFileInfo::class)]
#[UsesClass(ContentTag::class)]
#[UsesClass(ContentNotFoundException::class)]
#[UsesClass(SearchPlugin::class)]
#[UsesClass(SearchUpstreamException::class)]
#[UsesClass(OpenAiCompatibleLlmClient::class)]
#[UsesClass(SearchEngine::class)]
#[UsesClass(SearchResultsFilter::class)]
#[UsesClass(BlogPlugin::class)]
#[UsesClass(BlogPost::class)]
#[UsesClass(BlogRegistry::class)]
#[UsesClass(FaqPlugin::class)]
#[UsesClass(FaqQuestion::class)]
#[UsesClass(FaqRegistry::class)]
#[UsesClass(PagesPage::class)]
#[UsesClass(PagesPlugin::class)]
#[UsesClass(PagesRegistry::class)]
final class McpControllerTest extends TestCase
{
    private static FixtureHttpServer $searchServer;

    private McpController $controller;

    public static function setUpBeforeClass(): void
    {
        self::$searchServer = FixtureHttpServer::start(19804);
    }

    public static function tearDownAfterClass(): void
    {
        self::$searchServer->stop();
    }

    protected function setUp(): void
    {
        $this->controller = $this->buildController(askEnabled: false);
    }

    /**
     * Builds a real McpController wired to a real DocsPlugin (fixture
     * content) and, when $askEnabled is true, a real SearchPlugin pointed
     * at the fixture HTTP server started for the whole test class — so
     * "search_content" and "ask" run against a real socket, not a mock.
     *
     * @param bool $askEnabled Whether to enable and configure the "ask"
     * tool.
     * @return McpController
     */
    private function buildController(
        bool $askEnabled,
        string $url = 'http://localhost',
        bool $allSources = false,
        string $searchScenario = 'results_ok'
    ): McpController {
        $context = ContentFixtures::contentContext(url: $url);

        $docsPlugin = new DocsPlugin(
            $context,
            ['path' => 'docs']
        );
        $docsPlugin->loadContent(new ContentLoader(ContentFixtures::contentPath()));

        $academyPlugin = new AcademyPlugin(
            $context,
            ['path' => 'academy']
        );
        $academyPlugin->loadContent(new ContentLoader(ContentFixtures::contentPath()));

        $mcpPlugin = new McpPlugin($context, [
            'session_path' => sys_get_temp_dir() . '/derafu-content-mcp-tests-' . uniqid(),
            'ask' => ['enabled' => $askEnabled],
        ]);

        $plugins = [
            'docs' => $docsPlugin,
            'academy' => $academyPlugin,
            'mcp' => $mcpPlugin,
        ];

        if ($allSources) {
            foreach (['faq' => FaqPlugin::class, 'blog' => BlogPlugin::class, 'pages' => PagesPlugin::class] as $name => $class) {
                $plugins[$name] = new $class($context, ['path' => $name]);
                $plugins[$name]->loadContent(new ContentLoader(ContentFixtures::contentPath()));
            }
        }

        if ($askEnabled) {
            $plugins['search'] = new SearchPlugin($context, [
                'url' => self::$searchServer->url() . '/?scenario=' . $searchScenario . '&text=%s',
                'llm_url' => self::$searchServer->url(),
                'llm_model' => 'fixture-model',
                'llm_completions_path' => '/?scenario=chat_ok',
            ]);
        }

        $contentService = new ContentService($context, new FixturePluginLoader($plugins));

        $router = $this->fakeRouter();

        return new McpController($contentService, $router, RendererFixture::create($router));
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0: array<array-key, mixed>, 1: string} The decoded JSON-RPC
     * response body and the Mcp-Session-Id header.
     */
    private function callMcp(
        array $payload,
        ?string $sessionId = null,
        ?McpController $controller = null,
        string $requestUrl = 'http://localhost/api/content/mcp'
    ): array {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json, text/event-stream',
        ];
        if ($sessionId !== null) {
            $headers['Mcp-Session-Id'] = $sessionId;
        }

        $request = new Request(
            'POST',
            $requestUrl,
            $headers,
            json_encode($payload, JSON_THROW_ON_ERROR)
        );

        $response = ($controller ?? $this->controller)->handle($request);

        $decoded = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($decoded);

        return [
            $decoded,
            $response->getHeaderLine('Mcp-Session-Id'),
        ];
    }

    /**
     * The `result` of the response of a tool call: whether it is an error and
     * the text of its first content.
     *
     * @param array<array-key, mixed> $body Decoded JSON-RPC response.
     * @return array{isError: bool, text: string}
     */
    private function toolResult(array $body): array
    {
        $result = $body['result'] ?? null;
        $this->assertIsArray($result);
        $content = $result['content'] ?? null;
        $this->assertIsArray($content);
        $first = $content[0] ?? null;
        $this->assertIsArray($first);
        $text = $first['text'] ?? null;
        $this->assertIsString($text);
        $isError = $result['isError'] ?? null;
        $this->assertIsBool($isError);

        return ['isError' => $isError, 'text' => $text];
    }

    /**
     * The text of a tool call, decoded: the payload that the tool returns as
     * JSON.
     *
     * @param array<array-key, mixed> $body Decoded JSON-RPC response.
     * @return array<array-key, mixed>
     */
    private function toolPayload(array $body): array
    {
        $payload = json_decode($this->toolResult($body)['text'], true);
        $this->assertIsArray($payload);

        return $payload;
    }

    /**
     * The tools that the response of `tools/list` has.
     *
     * @param array<array-key, mixed> $body Decoded JSON-RPC response.
     * @return list<array<string, mixed>>
     */
    private function tools(array $body): array
    {
        $result = $body['result'] ?? null;
        $this->assertIsArray($result);
        $tools = $result['tools'] ?? null;
        $this->assertIsArray($tools);

        $list = [];
        foreach ($tools as $tool) {
            $this->assertIsArray($tool);
            $list[] = $tool;
        }

        return $list;
    }

    private function initialize(?McpController $controller = null, string $requestUrl = 'http://localhost/api/content/mcp'): string
    {
        [, $sessionId] = $this->callMcp([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-06-18',
                'capabilities' => [],
                'clientInfo' => ['name' => 'phpunit', 'version' => '0.0.0'],
            ],
        ], null, $controller, $requestUrl);

        $this->assertNotSame('', $sessionId);

        return $sessionId;
    }

    public function testToolsListDoesNotIncludeAskByDefault(): void
    {
        $sessionId = $this->initialize();

        [$body] = $this->callMcp([
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/list',
        ], $sessionId);

        $names = array_column($this->tools($body), 'name');

        $this->assertContains('get_content', $names);
        $this->assertContains('list_content', $names);
        $this->assertContains('list_tags', $names);
        $this->assertContains('search_content', $names);
        $this->assertNotContains('ask', $names);
    }

    /**
     * The 4 tools that query the content index directly are all
     * read-only, non-destructive, idempotent and closed-world (they only
     * ever see this site's own indexed content) — an MCP client should
     * not have to fall back to its own conservative defaults for these.
     */
    public function testContentToolsAdvertiseReadOnlyIdempotentClosedWorldAnnotations(): void
    {
        $sessionId = $this->initialize();

        [$body] = $this->callMcp([
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/list',
        ], $sessionId);

        $tools = array_column($this->tools($body), null, 'name');

        foreach (['search_content', 'get_content', 'list_content', 'list_tags'] as $name) {
            $annotations = $tools[$name]['annotations'];
            $this->assertIsArray($annotations);

            $this->assertTrue($annotations['readOnlyHint'], "$name readOnlyHint");
            $this->assertFalse($annotations['destructiveHint'], "$name destructiveHint");
            $this->assertTrue($annotations['idempotentHint'], "$name idempotentHint");
            $this->assertFalse($annotations['openWorldHint'], "$name openWorldHint");
        }
    }

    public function testGetContentToolReturnsTheRealMarkdownBody(): void
    {
        $sessionId = $this->initialize();

        [$body] = $this->callMcp([
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => [
                'name' => 'get_content',
                'arguments' => ['source' => 'docs', 'uri' => 'guide/getting-started'],
            ],
        ], $sessionId);

        $this->assertFalse($this->toolResult($body)['isError']);
        $payload = $this->toolPayload($body);
        $this->assertSame('guide/getting-started', $payload['uri']);
        $this->assertIsString($payload['markdown']);
        $this->assertStringContainsString('getting started, child', $payload['markdown']);
    }

    /**
     * Regression test for a real production bug: AcademyRegistry::get()
     * used to be typed/asserted to always return a course, so calling
     * get_content with an academy lesson (or module) URI — a real
     * request an MCP client makes routinely — crashed with an uncaught
     * AssertionError/TypeError instead of a graceful tool error, which
     * surfaced to the client as a raw JSON-RPC -32603 "Internal error"
     * instead of a normal isError=true tool result.
     */
    public function testGetContentToolResolvesANestedAcademyLessonUri(): void
    {
        $sessionId = $this->initialize();

        [$body] = $this->callMcp([
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => [
                'name' => 'get_content',
                'arguments' => ['source' => 'academy', 'uri' => 'demo-course/module-one/lesson-one'],
            ],
        ], $sessionId);

        $this->assertFalse($this->toolResult($body)['isError']);
        $payload = $this->toolPayload($body);
        $this->assertSame('demo-course/module-one/lesson-one', $payload['uri']);
    }

    /**
     * @return array<string, array{string, string, string, string}>
     */
    public static function provideContentOfEverySource(): array
    {
        return [
            'a question of the faq' => ['faq', 'question-one', 'question', 'How is this package tested?'],
            'a post of the blog' => ['blog', '2026-01-15-first-post', 'post', 'First post'],
            'a page' => ['pages', 'about', 'page', 'About us'],
            'a course of the academy' => ['academy', 'demo-course', 'course', 'Demo Course'],
            'a module of the academy' => ['academy', 'demo-course/module-one', 'module', 'Module One'],
        ];
    }

    #[DataProvider('provideContentOfEverySource')]
    public function testGetContentToolGivesTheMarkdownOfEachKindOfContent(
        string $source,
        string $uri,
        string $category,
        string $title
    ): void {
        $controller = $this->buildController(askEnabled: false, allSources: true);
        $sessionId = $this->initialize($controller);

        [$body] = $this->callMcp([
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => ['name' => 'get_content', 'arguments' => ['source' => $source, 'uri' => $uri]],
        ], $sessionId, $controller);

        $this->assertFalse($this->toolResult($body)['isError']);
        $payload = $this->toolPayload($body);
        $this->assertSame($category, $payload['category']);
        $this->assertSame($title, $payload['title']);
        $this->assertIsString($payload['markdown']);
        $this->assertStringContainsString($title, $payload['markdown']);
        $this->assertIsString($payload['url']);
        $this->assertStringStartsWith('http://localhost', $payload['url']);
    }

    public function testGetContentToolOnUnknownUriReturnsAGracefulToolError(): void
    {
        $sessionId = $this->initialize();

        [$body] = $this->callMcp([
            'jsonrpc' => '2.0',
            'id' => 4,
            'method' => 'tools/call',
            'params' => [
                'name' => 'get_content',
                'arguments' => ['source' => 'docs', 'uri' => 'does-not-exist'],
            ],
        ], $sessionId);

        $this->assertTrue($this->toolResult($body)['isError']);
        $this->assertStringContainsString('not found', $this->toolResult($body)['text']);
    }

    public function testListContentToolFiltersByTag(): void
    {
        $sessionId = $this->initialize();

        [$body] = $this->callMcp([
            'jsonrpc' => '2.0',
            'id' => 5,
            'method' => 'tools/call',
            'params' => [
                'name' => 'list_content',
                'arguments' => ['source' => 'docs', 'tag' => 'guide'],
            ],
        ], $sessionId);

        $items = $this->toolPayload($body);
        $uris = array_column($items, 'uri');

        $this->assertContains('guide', $uris);
        $this->assertContains('guide/getting-started', $uris);
        $this->assertNotContains('index', $uris);
    }

    public function testListTagsToolAggregatesTagsWithCounts(): void
    {
        $sessionId = $this->initialize();

        [$body] = $this->callMcp([
            'jsonrpc' => '2.0',
            'id' => 6,
            'method' => 'tools/call',
            'params' => [
                'name' => 'list_tags',
                'arguments' => ['source' => 'docs'],
            ],
        ], $sessionId);

        $tags = $this->toolPayload($body);
        $byName = array_column($tags, 'count', 'name');

        $this->assertSame(2, $byName['guide']);
    }

    /**
     * Each HTTP request builds a new Server instance, so an in-memory session store would forget the
     * session between the initialize call and the very next one. This
     * asserts the session set up in setUp() (a real FileSessionStore on
     * disk) survives across two independent McpController::handle() calls.
     */
    public function testSessionSurvivesAcrossSeparateControllerInvocations(): void
    {
        $sessionId = $this->initialize();

        [$body] = $this->callMcp([
            'jsonrpc' => '2.0',
            'id' => 7,
            'method' => 'tools/list',
        ], $sessionId);

        $this->assertArrayHasKey('result', $body);
        $this->assertArrayNotHasKey('error', $body);
    }

    public function testSearchContentToolReturnsRealResultsFromTheConfiguredSearchEngine(): void
    {
        $controller = $this->buildController(askEnabled: true);
        $sessionId = $this->initialize($controller);

        [$body] = $this->callMcp([
            'jsonrpc' => '2.0',
            'id' => 8,
            'method' => 'tools/call',
            'params' => [
                'name' => 'search_content',
                'arguments' => ['query' => 'hola'],
            ],
        ], $sessionId, $controller);

        $this->assertFalse($this->toolResult($body)['isError']);
        $results = $this->toolPayload($body);
        $this->assertCount(2, $results);
        $this->assertIsArray($results[0]);
        $this->assertSame('Fixture result one', $results[0]['title']);
        $this->assertSame(0.91, $results[0]['score']);
    }

    /**
     * "all" must behave exactly like omitting source entirely — it is the
     * explicit, model-visible spelling of the same "search every source"
     * behavior, added because a real MCP client kept passing a specific
     * source even when it did not actually know where the answer was,
     * silently missing it instead of finding it via the broader search.
     */
    public function testSearchContentToolShortensALongPreviewAndLeavesOutATextThatIsNotOne(): void
    {
        $controller = $this->buildController(askEnabled: true, searchScenario: 'results_long_data');
        $sessionId = $this->initialize($controller);

        [$body] = $this->callMcp([
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => ['name' => 'search_content', 'arguments' => ['query' => 'hola']],
        ], $sessionId, $controller);

        $results = $this->toolPayload($body);
        $previews = array_column($results, 'preview', 'uri');

        $this->assertSame(303, mb_strlen($previews['guide']));
        $this->assertStringEndsWith('...', $previews['guide']);
        $this->assertStringStartsWith('word xxx', $previews['guide']);
        $this->assertSame('', $previews['index']);
        $this->assertSame('Short text.', $previews['guide/getting-started']);
    }

    public function testSearchContentToolSourceAllReturnsEveryResultJustLikeOmittingSource(): void
    {
        $controller = $this->buildController(askEnabled: true);
        $sessionId = $this->initialize($controller);

        [$body] = $this->callMcp([
            'jsonrpc' => '2.0',
            'id' => 8,
            'method' => 'tools/call',
            'params' => [
                'name' => 'search_content',
                'arguments' => ['query' => 'hola', 'source' => 'all'],
            ],
        ], $sessionId, $controller);

        $this->assertFalse($this->toolResult($body)['isError']);
        $results = $this->toolPayload($body);
        $this->assertCount(2, $results);
    }

    /**
     * A specific, non-matching source narrows the results to none — the
     * fixture search results carry a real "type" ("docs") for both
     * entries, so filtering by "academy" (a real source none of them
     * has) proves the filter branch actually excludes non-matching
     * results, not just that it happens to leave everything in.
     */
    public function testSearchContentToolWithASpecificSourceStillFilters(): void
    {
        $controller = $this->buildController(askEnabled: true);
        $sessionId = $this->initialize($controller);

        [$body] = $this->callMcp([
            'jsonrpc' => '2.0',
            'id' => 8,
            'method' => 'tools/call',
            'params' => [
                'name' => 'search_content',
                'arguments' => ['query' => 'hola', 'source' => 'academy'],
            ],
        ], $sessionId, $controller);

        $this->assertFalse($this->toolResult($body)['isError']);
        $results = $this->toolPayload($body);
        $this->assertCount(0, $results);
    }

    public function testToolsListIncludesAskWhenEnabled(): void
    {
        $controller = $this->buildController(askEnabled: true);
        $sessionId = $this->initialize($controller);

        [$body] = $this->callMcp([
            'jsonrpc' => '2.0',
            'id' => 9,
            'method' => 'tools/list',
        ], $sessionId, $controller);

        $names = array_column($this->tools($body), 'name');

        $this->assertContains('ask', $names);
    }

    /**
     * "ask" is still read-only/non-destructive, but — unlike the content
     * tools — it is not idempotent (an LLM's phrasing can vary between
     * identical calls) and it is open-world (it cannot give the same
     * closed-domain guarantee a direct content query can: it may still
     * surface pretrained knowledge or hallucinate beyond the retrieved
     * context despite being grounded on it).
     */
    public function testAskToolAnnotationsReflectItsLlmBackedNature(): void
    {
        $controller = $this->buildController(askEnabled: true);
        $sessionId = $this->initialize($controller);

        [$body] = $this->callMcp([
            'jsonrpc' => '2.0',
            'id' => 9,
            'method' => 'tools/list',
        ], $sessionId, $controller);

        $tools = array_column($this->tools($body), null, 'name');
        $annotations = $tools['ask']['annotations'];
        $this->assertIsArray($annotations);

        $this->assertTrue($annotations['readOnlyHint']);
        $this->assertFalse($annotations['destructiveHint']);
        $this->assertFalse($annotations['idempotentHint']);
        $this->assertTrue($annotations['openWorldHint']);
    }

    public function testAskToolReturnsTheRealLlmAnswerWhenEnabled(): void
    {
        $controller = $this->buildController(askEnabled: true);
        $sessionId = $this->initialize($controller);

        [$body] = $this->callMcp([
            'jsonrpc' => '2.0',
            'id' => 10,
            'method' => 'tools/call',
            'params' => [
                'name' => 'ask',
                'arguments' => ['question' => 'How do I cancel an invoice?'],
            ],
        ], $sessionId, $controller);

        $this->assertFalse($this->toolResult($body)['isError']);
        $this->assertSame(
            'Hello, this is a test answer.',
            $this->toolResult($body)['text']
        );
    }

    /**
     * The LLM backend answers, but with an error — "ask" must surface a graceful
     * tool error, not crash the whole MCP request.
     */
    public function testAskToolSurfacesAGracefulErrorWhenTheLlmBackendFails(): void
    {
        $mcpPlugin = new McpPlugin(ContentFixtures::contentContext(), [
            'session_path' => sys_get_temp_dir() . '/derafu-content-mcp-tests-' . uniqid(),
            'ask' => ['enabled' => true],
        ]);
        $searchPlugin = new SearchPlugin(ContentFixtures::contentContext(), [
            'url' => self::$searchServer->url() . '/?scenario=results_ok&text=%s',
            'llm_url' => self::$searchServer->url(),
            'llm_model' => 'fixture-model',
            'llm_completions_path' => '/?scenario=chat_error_detail',
        ]);

        $docsPlugin = new DocsPlugin(ContentFixtures::contentContext(), ['path' => 'docs']);
        $docsPlugin->loadContent(new ContentLoader(ContentFixtures::contentPath()));

        $contentService = ContentFixtures::contentService([
            'docs' => $docsPlugin,
            'mcp' => $mcpPlugin,
            'search' => $searchPlugin,
        ]);

        $router = $this->fakeRouter();
        $controller = new McpController($contentService, $router, RendererFixture::create($router));
        $sessionId = $this->initialize($controller);

        [$body] = $this->callMcp([
            'jsonrpc' => '2.0',
            'id' => 11,
            'method' => 'tools/call',
            'params' => [
                'name' => 'ask',
                'arguments' => ['question' => 'hola'],
            ],
        ], $sessionId, $controller);

        $this->assertTrue($this->toolResult($body)['isError']);
        $this->assertStringContainsString('Model not found', $this->toolResult($body)['text']);
    }

    /**
     * StreamableHttpTransport's default middleware only allows Host/Origin in ['localhost', '127.0.0.1',
     * '[::1]'] (DNS rebinding protection meant for locally-run MCP
     * servers), which silently rejected every real request to the live
     * production site — completely masked in this test suite because
     * every request here already targets "localhost". This pins the fix:
     * a request whose Host matches the site's own configured url() must
     * be allowed too.
     */
    public function testRequestFromTheConfiguredSiteHostIsAllowed(): void
    {
        $controller = $this->buildController(askEnabled: false, url: 'https://example.com');

        $sessionId = $this->initialize($controller, 'https://example.com/api/content/mcp');

        [$body] = $this->callMcp([
            'jsonrpc' => '2.0',
            'id' => 12,
            'method' => 'tools/list',
        ], $sessionId, $controller, 'https://example.com/api/content/mcp');

        $this->assertArrayHasKey('result', $body);
        $this->assertArrayNotHasKey('error', $body);
    }

    /**
     * The DNS rebinding protection itself must still work for a genuinely
     * unrelated host — the fix widens the allowlist to the site's own
     * configured host, it must not disable the protection altogether.
     */
    public function testRequestFromAnUntrustedHostIsRejected(): void
    {
        $controller = $this->buildController(askEnabled: false, url: 'https://example.com');

        $request = new Request(
            'POST',
            'http://evil.example.com/api/content/mcp',
            [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json, text/event-stream',
            ],
            json_encode([
                'jsonrpc' => '2.0',
                'id' => 13,
                'method' => 'initialize',
                'params' => [
                    'protocolVersion' => '2025-06-18',
                    'capabilities' => [],
                    'clientInfo' => ['name' => 'phpunit', 'version' => '0.0.0'],
                ],
            ], JSON_THROW_ON_ERROR)
        );

        $response = $controller->handle($request);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringContainsString('Invalid Host header', (string) $response->getBody());
    }

    private function fakeRouter(): RouterInterface
    {
        return new class () implements RouterInterface {
            public function addParser(ParserInterface $parser): static
            {
                return $this;
            }

            public function addRoute(string $name, string $path, string|array|Closure $handler, array $defaults = [], array $methods = [], array $roles = []): static
            {
                return $this;
            }

            public function match(?string $uri = null, ?string $method = null): RouteMatchInterface
            {
                throw new RouteNotFoundException($uri ?? '');
            }

            public function has(string $name): bool
            {
                return false;
            }

            public function generate(string $name, array $parameters = [], UrlReferenceType $referenceType = UrlReferenceType::ABSOLUTE_PATH): string
            {
                $doc = $parameters['doc'] ?? '';
                assert(is_string($doc));

                return 'http://localhost/' . $doc;
            }

            public function setContext(RequestContextInterface $context): static
            {
                return $this;
            }

            public function getContext(): ?RequestContextInterface
            {
                return null;
            }
        };
    }
}
