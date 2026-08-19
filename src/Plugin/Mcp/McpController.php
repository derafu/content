<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Plugin\Mcp;

use Derafu\Content\Contract\ContentServiceInterface;
use Derafu\Content\Plugin\Mcp\Tool\AskTool;
use Derafu\Content\Plugin\Mcp\Tool\GetContentTool;
use Derafu\Content\Plugin\Mcp\Tool\ListContentTool;
use Derafu\Content\Plugin\Mcp\Tool\ListTagsTool;
use Derafu\Content\Plugin\Mcp\Tool\SearchContentTool;
use Derafu\Http\Request;
use Derafu\Renderer\Contract\RendererInterface;
use Derafu\Routing\Contract\RouterInterface;
use Mcp\Schema\ToolAnnotations;
use Mcp\Server;
use Mcp\Server\Session\FileSessionStore;
use Mcp\Server\Transport\Http\Middleware\CorsMiddleware;
use Mcp\Server\Transport\Http\Middleware\DnsRebindingProtectionMiddleware;
use Mcp\Server\Transport\Http\Middleware\ProtocolVersionMiddleware;
use Mcp\Server\Transport\StreamableHttpTransport;
use Psr\Http\Message\ResponseInterface;

/**
 * Controller for the MCP plugin.
 *
 * Exposes the content of the website as an MCP server, using the Streamable
 * HTTP transport (JSON-RPC 2.0 over a plain PSR-7 request/response cycle, no
 * event loop involved), so it fits the same stateless request model used by
 * every other controller of this package.
 *
 * This controller does not handle authentication nor rate limiting. If the
 * endpoint needs to be protected, that must be done with the middlewares of
 * the HTTP stack (derafu/http), before this controller is reached.
 */
class McpController
{
    /**
     * Constructor.
     *
     * @param ContentServiceInterface $contentService Content service.
     * @param RouterInterface $router Router.
     * @param RendererInterface $renderer Renderer.
     */
    public function __construct(
        private readonly ContentServiceInterface $contentService,
        private readonly RouterInterface $router,
        private readonly RendererInterface $renderer
    ) {
    }

    /**
     * Handle a MCP JSON-RPC request.
     *
     * @param Request $request Request.
     * @return ResponseInterface
     */
    public function handle(Request $request): ResponseInterface
    {
        $plugin = $this->contentService->plugin('mcp');
        assert($plugin instanceof McpPlugin);

        $server = $this->buildServer($plugin);

        // StreamableHttpTransport's default middleware stack rejects any
        // Host/Origin outside ['localhost', '127.0.0.1', '[::1]'] (DNS
        // rebinding protection meant for locally-run MCP servers) and no
        // longer sets Access-Control-Allow-Origin at all. Both are wrong
        // for this plugin's public-by-design content API: the real site
        // host has to be explicitly allowed, and CORS restored to '*' so
        // browser-based MCP clients can reach it, same as any other public
        // read endpoint this package serves (get_content, list_content, etc.
        // carry no credentials, so a wildcard origin is safe here).
        $transport = new StreamableHttpTransport($request, middleware: [
            new CorsMiddleware(['*']),
            new DnsRebindingProtectionMiddleware($this->allowedHosts()),
            new ProtocolVersionMiddleware(),
        ]);

        return $server->run($transport);
    }

    /**
     * Hosts allowed to reach this MCP server: the site's own configured
     * host (from ContentConfig::url()) plus the local dev/test hosts the
     * SDK allows by default, so local development keeps working unchanged.
     *
     * @return list<string>
     */
    private function allowedHosts(): array
    {
        $configuredHost = parse_url(
            $this->contentService->context()->config()->url(),
            PHP_URL_HOST
        );

        return array_values(array_unique(array_filter([
            $configuredHost,
            'localhost',
            '127.0.0.1',
            '[::1]',
        ])));
    }

    /**
     * Build the MCP server, registering the tools of the plugin.
     *
     * @param McpPlugin $plugin Plugin.
     * @return Server
     */
    private function buildServer(McpPlugin $plugin): Server
    {
        $builder = Server::builder()
            ->setServerInfo($plugin->serverName(), $plugin->serverVersion())
            ->setSession(new FileSessionStore(
                $plugin->sessionPath(),
                $plugin->sessionTtl()
            ))
            ->setInstructions(
                'Search and fetch the published content of this website '
                . '(docs, blog, FAQ and academy). Typical flow: start with '
                . 'search_content (source: "all" unless you already know '
                . 'which one has the answer) to find where something lives, '
                . 'then get_content with the exact source+uri it returned to '
                . 'read the full body. list_content/list_tags are for '
                . 'browsing a source you have already picked, not for '
                . 'finding where an answer is. Prefer these tools over "ask" '
                . 'when precision matters, since "ask" only reflects the '
                . 'underlying content as well as its LLM backend allows.'
            )
            ->addTool(
                [new SearchContentTool($this->contentService), '__invoke'],
                name: 'search_content',
                description: 'Semantic search across the indexed content of '
                    . 'the website (docs, blog, faq, academy, pages). This is '
                    . 'the tool to use when you do not already know which '
                    . 'source has the answer — pass source: "all" (the '
                    . 'default) to search every source at once. Only pass a '
                    . 'specific source when you are already confident that is '
                    . 'the right one: restricting to the wrong source does '
                    . 'not error, it just silently misses the real answer '
                    . 'if it lives elsewhere. Returns the best matching items '
                    . 'ranked by relevance, with a short preview of each and '
                    . 'the source+uri each came from. Use get_content with '
                    . 'that source+uri to fetch the full body of a result.',
                annotations: $this->contentToolAnnotations(),
            )
            ->addTool(
                [new GetContentTool($this->contentService, $this->router, $this->renderer), '__invoke'],
                name: 'get_content',
                description: 'Fetch a single content item\'s full Markdown '
                    . 'body plus metadata (title, tags, dates, authors), '
                    . 'given its exact source and URI. Requires already '
                    . 'knowing both — it does not search or guess across '
                    . 'sources. Get them from search_content or list_content '
                    . 'first; do not call this speculatively with a guessed '
                    . 'source.',
                annotations: $this->contentToolAnnotations(),
            )
            ->addTool(
                [new ListContentTool($this->contentService, $this->router), '__invoke'],
                name: 'list_content',
                description: 'Browse/filter the items of one specific '
                    . 'content source (academy, blog, docs or faq) you have '
                    . 'already chosen — e.g. "every academy lesson tagged '
                    . 'X". Not for finding which source has an answer to a '
                    . 'question; use search_content for that instead. Does '
                    . 'not return the full body of each item, use '
                    . 'get_content for that.',
                annotations: $this->contentToolAnnotations(),
            )
            ->addTool(
                [new ListTagsTool($this->contentService), '__invoke'],
                name: 'list_tags',
                description: 'List the tags used within one specific '
                    . 'content source you have already chosen, with how '
                    . 'many items use each one — useful to narrow a '
                    . 'subsequent list_content call.',
                annotations: $this->contentToolAnnotations(),
            )
        ;

        if ($plugin->askEnabled()) {
            $builder->addTool(
                [new AskTool($this->contentService), '__invoke'],
                name: 'ask',
                description: 'Ask a natural-language question and get a '
                    . 'conversational answer generated by an LLM grounded '
                    . 'on the indexed content across every source. Less '
                    . 'reliable than search_content/get_content because it '
                    . 'depends on the quality of the LLM backend; prefer '
                    . 'those tools when precision matters.',
                annotations: new ToolAnnotations(
                    readOnlyHint: true,
                    destructiveHint: false,
                    // Unlike the content tools, an LLM answer is not
                    // guaranteed to be the same for the same question (model
                    // sampling), and it cannot be given the same closed-world
                    // guarantee a direct content query can: it may still
                    // surface pretrained knowledge or hallucinate beyond the
                    // retrieved context despite being grounded on it.
                    idempotentHint: false,
                    openWorldHint: true,
                ),
            );
        }

        return $builder->build();
    }

    /**
     * Annotations shared by every tool that queries the content index
     * directly (search_content, get_content, list_content, list_tags):
     * they only read, never write; repeating one with the same arguments
     * has no additional effect beyond the first call; and their domain is
     * closed (the site's own indexed content), unlike "ask", which goes
     * through an LLM.
     *
     * @return ToolAnnotations
     */
    private function contentToolAnnotations(): ToolAnnotations
    {
        return new ToolAnnotations(
            readOnlyHint: true,
            destructiveHint: false,
            idempotentHint: true,
            openWorldHint: false,
        );
    }
}
