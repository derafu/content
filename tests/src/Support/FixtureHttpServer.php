<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent\Support;

use RuntimeException;

/**
 * Starts and stops a real PHP built-in HTTP server for the duration of a
 * test class, so HTTP clients (SearchEngine, OpenAiCompatibleLlmClient) can
 * be exercised over an actual socket instead of a mocked PSR-18 client.
 */
final class FixtureHttpServer
{
    private $process = null;

    private function __construct(private readonly int $port)
    {
    }

    /**
     * Start the fixture HTTP server on the given port and wait until it is
     * actually accepting connections.
     *
     * @param int $port TCP port to listen on.
     * @return self
     */
    public static function start(int $port): self
    {
        $server = new self($port);
        $server->boot();

        return $server;
    }

    /**
     * Base URL of the running fixture server.
     *
     * @return string
     */
    public function url(): string
    {
        return 'http://127.0.0.1:' . $this->port;
    }

    /**
     * Stop the fixture HTTP server.
     *
     * @return void
     */
    public function stop(): void
    {
        if ($this->process !== null) {
            proc_terminate($this->process);
            proc_close($this->process);
            $this->process = null;
        }
    }

    /**
     * Boot the PHP built-in server as a background process and poll the
     * port until a real TCP connection succeeds (bounded, so a failure to
     * boot fails fast instead of hanging the test suite).
     *
     * @return void
     */
    private function boot(): void
    {
        $router = dirname(__DIR__, 2) . '/fixtures/http/router.php';

        $command = sprintf(
            'exec php -S 127.0.0.1:%d %s',
            $this->port,
            escapeshellarg($router)
        );

        $this->process = proc_open(
            $command,
            [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes
        );

        if (!is_resource($this->process)) {
            throw new RuntimeException('Could not start the fixture HTTP server.');
        }

        $deadline = microtime(true) + 5;
        while (microtime(true) < $deadline) {
            $connection = @fsockopen('127.0.0.1', $this->port, timeout: 1);
            if ($connection !== false) {
                fclose($connection);

                return;
            }
            usleep(50000);
        }

        $this->stop();

        throw new RuntimeException(sprintf(
            'Fixture HTTP server did not start listening on port %d in time.',
            $this->port
        ));
    }
}
