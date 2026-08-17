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

use Derafu\Content\Abstract\AbstractPlugin;
use Derafu\Content\Contract\PluginInterface;

/**
 * Plugin that exposes the content of the website as an MCP (Model Context
 * Protocol) server.
 *
 * This lets AI agents (Claude Code, Claude Desktop, Cursor, etc.) search and
 * fetch the content directly as tools, instead of relying on stale training
 * data or scraping HTML.
 *
 * This plugin does not handle authentication nor rate limiting, that is not
 * its responsibility. If the MCP endpoint needs to be protected, that must be
 * done at the HTTP stack level (e.g. with the middlewares of derafu/http).
 */
class McpPlugin extends AbstractPlugin implements PluginInterface
{
    /**
     * Schema of the options of the plugin.
     *
     * @var array
     */
    private const OPTIONS_SCHEMA = [
        // Name of the plugin.
        'name' => [
            'types' => 'string',
            'required' => true,
            'default' => 'mcp',
        ],

        // Name announced to MCP clients during the initialize handshake.
        'server_name' => [
            'types' => 'string',
            'required' => false,
            'default' => 'derafu-content',
        ],

        // Version announced to MCP clients during the initialize handshake.
        'server_version' => [
            'types' => 'string',
            'required' => false,
            'default' => '1.0.0',
        ],

        // Directory where MCP sessions are persisted between requests.
        //
        // A MCP session is created on "initialize" and referenced by every
        // following call through the "Mcp-Session-Id" header. Since each HTTP
        // request builds a new Server instance (this package has no
        // long-running process), the session state must survive on disk
        // between requests instead of staying in memory. Defaults to a
        // subdirectory of the system temp dir; point it at something under
        // the website's "var/" directory for a more durable/cleanable
        // location.
        'session_path' => [
            'types' => ['string', 'null'],
            'required' => false,
        ],

        // Time to live, in seconds, of a persisted MCP session.
        'session_ttl' => [
            'types' => 'int',
            'required' => false,
            'default' => 3600,
        ],

        // Configuration of the "ask" tool (LLM-backed conversational answers).
        //
        // Disabled by default: unlike search_content/get_content, which only
        // read from the indexed content, "ask" depends on the quality of
        // whatever LLM is on the other end of the "search" plugin. A bad
        // answer there makes the whole MCP server look bad, so it is opt-in.
        'ask' => [
            'types' => 'array',
            'required' => false,
            'default' => [
                'enabled' => false,
            ],
            'schema' => [
                'enabled' => [
                    'types' => 'boolean',
                    'required' => false,
                    'default' => false,
                ],
            ],
        ],
    ];

    /**
     * Get the name announced to MCP clients during the initialize handshake.
     *
     * @return string
     */
    public function serverName(): string
    {
        return $this->options->get('server_name');
    }

    /**
     * Get the version announced to MCP clients during the initialize
     * handshake.
     *
     * @return string
     */
    public function serverVersion(): string
    {
        return $this->options->get('server_version');
    }

    /**
     * Get the directory where MCP sessions are persisted between requests.
     *
     * @return string
     */
    public function sessionPath(): string
    {
        return $this->options->get('session_path')
            ?? sys_get_temp_dir() . '/derafu-content-mcp-sessions'
        ;
    }

    /**
     * Get the time to live, in seconds, of a persisted MCP session.
     *
     * @return int
     */
    public function sessionTtl(): int
    {
        return $this->options->get('session_ttl');
    }

    /**
     * Whether the "ask" tool (LLM-backed conversational answers) is enabled.
     *
     * @return bool
     */
    public function askEnabled(): bool
    {
        return (bool) $this->options->get('ask.enabled', false);
    }

    /**
     * {@inheritDoc}
     */
    protected static function getSchema(): array
    {
        return self::OPTIONS_SCHEMA;
    }
}
