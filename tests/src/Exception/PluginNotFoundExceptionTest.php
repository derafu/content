<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent\Exception;

use Derafu\Content\ContentConfig;
use Derafu\Content\ContentContext;
use Derafu\Content\ContentService;
use Derafu\Content\Exception\PluginNotFoundException;
use Derafu\Http\Contract\HttpExceptionInterface;
use Derafu\Http\Enum\HttpStatus;
use Derafu\TestsContent\Support\ContentFixtures;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * Asking for a plugin that the site did not enable is a 404 (there is nothing
 * there), not a 500, and it is still the invalid argument that the callers
 * (like the tools of MCP) already catch.
 */
#[CoversClass(PluginNotFoundException::class)]
#[CoversClass(ContentService::class)]
#[UsesClass(ContentConfig::class)]
#[UsesClass(ContentContext::class)]
final class PluginNotFoundExceptionTest extends TestCase
{
    public function testAPluginThatIsNotEnabledIsANotFound(): void
    {
        $exception = $this->failure();

        $this->assertInstanceOf(PluginNotFoundException::class, $exception);
        $this->assertInstanceOf(HttpExceptionInterface::class, $exception);
        $this->assertSame(HttpStatus::NOT_FOUND, $exception->getStatus());
        $this->assertSame('Not Found', $exception->getTitle());
        $this->assertSame([], $exception->getContext());
        $this->assertSame([], $exception->getHeaders());
    }

    public function testItIsStillTheInvalidArgumentThatTheCallersCatch(): void
    {
        $exception = $this->failure();

        $this->assertInstanceOf(InvalidArgumentException::class, $exception);
        $this->assertSame('Plugin "missing" not found. Available plugins: .', $exception->getMessage());
    }

    private function failure(): Throwable
    {
        try {
            ContentFixtures::contentService([])->plugin('missing');
        } catch (Throwable $e) {
            return $e;
        }

        $this->fail('The unknown plugin was not an error.');
    }
}
