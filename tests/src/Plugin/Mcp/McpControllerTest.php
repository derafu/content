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
use Derafu\Content\Plugin\Mcp\McpController;
use Derafu\Content\Plugin\Mcp\McpPlugin;
use Derafu\Content\Plugin\Mcp\Tool\AskTool;
use Derafu\Content\Plugin\Mcp\Tool\ContentSourceResolver;
use Derafu\Content\Plugin\Mcp\Tool\GetContentTool;
use Derafu\Content\Plugin\Mcp\Tool\ListContentTool;
use Derafu\Content\Plugin\Mcp\Tool\ListTagsTool;
use Derafu\Content\Plugin\Mcp\Tool\SearchContentTool;
use Derafu\Content\Plugin\Search\Exception\SearchUpstreamException;
use Derafu\Content\Plugin\Search\OpenAiCompatibleLlmClient;
use Derafu\Content\Plugin\Search\SearchEngine;
use Derafu\Content\Plugin\Search\SearchPlugin;
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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Drives the real MCP JSON-RPC protocol (initialize → tools/list →
 * tools/call) through McpController, backed by a real DocsPlugin loaded
 * from fixture content and a real (file-based) MCP session store — the
 * exact chain that surfaced the session-persistence bug during this
 * session's manual smoke testing, now pinned down permanently. No mocks:
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
    private function buildController(bool $askEnabled, string $url = 'http://localhost'): McpController
    {
        $context = ContentFixtures::contentContext(url: $url);

        $docsPlugin = new DocsPlugin(
            $context,
            ['path' => 'docs']
        );
        $docsPlugin->loadContent(new ContentLoader(ContentFixtures::contentPath()));

        $mcpPlugin = new McpPlugin($context, [
            'session_path' => sys_get_temp_dir() . '/derafu-content-mcp-tests-' . uniqid(),
            'ask' => ['enabled' => $askEnabled],
        ]);

        $plugins = [
            'docs' => $docsPlugin,
            'mcp' => $mcpPlugin,
        ];

        if ($askEnabled) {
            $plugins['search'] = new SearchPlugin($context, [
                'url' => self::$searchServer->url() . '/?scenario=results_ok&text=%s',
                'llm_url' => self::$searchServer->url(),
                'llm_model' => 'fixture-model',
                'llm_completions_path' => '/?scenario=chat_ok',
            ]);
        }

        $contentService = new ContentService($context, new FixturePluginLoader($plugins));

        return new McpController($contentService, $this->fakeRouter());
    }

    /**
     * @return array{0: array<string,mixed>, 1: string} The decoded JSON-RPC
     * response body and the Mcp-Session-Id header.
     */
    private function callMcp(
        array $payload,
        ?string $sessionId = null,
        ?McpController $controller = null,
        string $requestUrl = 'http://localhost/api/mcp'
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

        return [
            json_decode((string) $response->getBody(), true),
            $response->getHeaderLine('Mcp-Session-Id'),
        ];
    }

    private function initialize(?McpController $controller = null, string $requestUrl = 'http://localhost/api/mcp'): string
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

        $names = array_column($body['result']['tools'], 'name');

        $this->assertContains('get_content', $names);
        $this->assertContains('list_content', $names);
        $this->assertContains('list_tags', $names);
        $this->assertContains('search_content', $names);
        $this->assertNotContains('ask', $names);
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
                'arguments' => ['source' => 'docs', 'uri' => 'guia/primeros-pasos'],
            ],
        ], $sessionId);

        $this->assertFalse($body['result']['isError']);
        $payload = json_decode($body['result']['content'][0]['text'], true);
        $this->assertSame('guia/primeros-pasos', $payload['uri']);
        $this->assertStringContainsString('primeros pasos, hijo', $payload['markdown']);
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
                'arguments' => ['source' => 'docs', 'uri' => 'no-existe'],
            ],
        ], $sessionId);

        $this->assertTrue($body['result']['isError']);
        $this->assertStringContainsString('not found', $body['result']['content'][0]['text']);
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
                'arguments' => ['source' => 'docs', 'tag' => 'guia'],
            ],
        ], $sessionId);

        $items = json_decode($body['result']['content'][0]['text'], true);
        $uris = array_column($items, 'uri');

        $this->assertContains('guia', $uris);
        $this->assertContains('guia/primeros-pasos', $uris);
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

        $tags = json_decode($body['result']['content'][0]['text'], true);
        $byName = array_column($tags, 'count', 'name');

        $this->assertSame(2, $byName['guia']);
    }

    /**
     * The exact bug found this session: each HTTP request builds a new
     * Server instance, so an in-memory session store would forget the
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

        $this->assertFalse($body['result']['isError']);
        $results = json_decode($body['result']['content'][0]['text'], true);
        $this->assertCount(2, $results);
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

        $this->assertFalse($body['result']['isError']);
        $results = json_decode($body['result']['content'][0]['text'], true);
        $this->assertCount(2, $results);
    }

    /**
     * A specific source still filters, distinct from "all" — the fixture
     * search results carry no "type" field, so filtering by any concrete
     * source narrows the real (non-mocked) results down to none, proving
     * the filter branch still runs when a specific source is requested.
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
                'arguments' => ['query' => 'hola', 'source' => 'docs'],
            ],
        ], $sessionId, $controller);

        $this->assertFalse($body['result']['isError']);
        $results = json_decode($body['result']['content'][0]['text'], true);
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

        $names = array_column($body['result']['tools'], 'name');

        $this->assertContains('ask', $names);
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
                'arguments' => ['question' => '¿Cómo anulo un DTE?'],
            ],
        ], $sessionId, $controller);

        $this->assertFalse($body['result']['isError']);
        $this->assertSame(
            'Hola, esta es una respuesta de prueba.',
            $body['result']['content'][0]['text']
        );
    }

    /**
     * Reproduces this session's real chat.derafu.ai outage: the LLM
     * backend answers, but with an error — "ask" must surface a graceful
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

        $controller = new McpController($contentService, $this->fakeRouter());
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

        $this->assertTrue($body['result']['isError']);
        $this->assertStringContainsString('Model not found', $body['result']['content'][0]['text']);
    }

    /**
     * The exact bug found this session: StreamableHttpTransport's default
     * middleware only allows Host/Origin in ['localhost', '127.0.0.1',
     * '[::1]'] (DNS rebinding protection meant for locally-run MCP
     * servers), which silently rejected every real request to the live
     * production site — completely masked in this test suite because
     * every request here already targets "localhost". This pins the fix:
     * a request whose Host matches the site's own configured url() must
     * be allowed too.
     */
    public function testRequestFromTheConfiguredSiteHostIsAllowed(): void
    {
        $controller = $this->buildController(askEnabled: false, url: 'https://www.libredte.cl');

        $sessionId = $this->initialize($controller, 'https://www.libredte.cl/api/mcp');

        [$body] = $this->callMcp([
            'jsonrpc' => '2.0',
            'id' => 12,
            'method' => 'tools/list',
        ], $sessionId, $controller, 'https://www.libredte.cl/api/mcp');

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
        $controller = $this->buildController(askEnabled: false, url: 'https://www.libredte.cl');

        $request = new Request(
            'POST',
            'http://evil.example.com/api/mcp',
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

            public function generate(string $name, array $parameters = [], UrlReferenceType $referenceType = UrlReferenceType::ABSOLUTE_PATH): string
            {
                return 'http://localhost/' . ($parameters['doc'] ?? '');
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
