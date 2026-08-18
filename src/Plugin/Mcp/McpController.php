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
use Derafu\Routing\Contract\RouterInterface;
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
     */
    public function __construct(
        private readonly ContentServiceInterface $contentService,
        private readonly RouterInterface $router
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
                . '(docs, blog, FAQ and academy). Use search_content to find '
                . 'relevant material, get_content to fetch the full Markdown '
                . 'body of a specific item, list_content to browse a source '
                . 'and list_tags to discover available tags. Prefer those '
                . 'tools over "ask" when precision matters, since "ask" only '
                . 'reflects the underlying content as well as its LLM '
                . 'backend allows.'
            )
            ->addTool(
                [new SearchContentTool($this->contentService), '__invoke'],
                name: 'search_content',
                description: 'Semantic search across the indexed content of '
                    . 'the website (docs, blog, faq, academy). Returns the '
                    . 'best matching items ranked by relevance, with a short '
                    . 'preview of each. Use get_content to fetch the full '
                    . 'body of a result.',
            )
            ->addTool(
                [new GetContentTool($this->contentService, $this->router), '__invoke'],
                name: 'get_content',
                description: 'Fetch a single content item by source and '
                    . 'URI, returning its full Markdown body plus metadata '
                    . '(title, tags, dates, authors).',
            )
            ->addTool(
                [new ListContentTool($this->contentService, $this->router), '__invoke'],
                name: 'list_content',
                description: 'Browse/filter the items of a content source '
                    . '(academy, blog, docs or faq), optionally by tag, '
                    . 'category or free-text search. Does not return the '
                    . 'full body of each item, use get_content for that.',
            )
            ->addTool(
                [new ListTagsTool($this->contentService), '__invoke'],
                name: 'list_tags',
                description: 'List the tags used in a content source, with '
                    . 'how many items use each one.',
            )
        ;

        if ($plugin->askEnabled()) {
            $builder->addTool(
                [new AskTool($this->contentService), '__invoke'],
                name: 'ask',
                description: 'Ask a natural-language question and get a '
                    . 'conversational answer generated by an LLM grounded '
                    . 'on the indexed content. Less reliable than '
                    . 'search_content/get_content because it depends on the '
                    . 'quality of the LLM backend; prefer those tools when '
                    . 'precision matters.',
            );
        }

        return $builder->build();
    }
}
