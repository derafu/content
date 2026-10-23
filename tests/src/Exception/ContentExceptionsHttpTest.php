<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent\Exception;

use Derafu\Content\Exception\ContentNotFoundException;
use Derafu\Content\Exception\PluginNotFoundException;
use Derafu\Content\Plugin\Search\Exception\SearchUpstreamException;
use Derafu\Http\Contract\HttpExceptionInterface;
use Derafu\Http\Enum\HttpStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The exceptions of the package say with which HTTP status they are answered,
 * so the application answers a missing content with 404 and a failing search
 * engine with 502, without having to know them.
 */
#[CoversClass(ContentNotFoundException::class)]
#[CoversClass(PluginNotFoundException::class)]
#[CoversClass(SearchUpstreamException::class)]
final class ContentExceptionsHttpTest extends TestCase
{
    /**
     * @return array<string, array{HttpExceptionInterface, HttpStatus, string, string}>
     */
    public static function provideExceptions(): array
    {
        return [
            'content not found' => [
                new ContentNotFoundException('Content not found.'),
                HttpStatus::NOT_FOUND,
                'Not Found',
                'https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/404',
            ],
            'plugin not found' => [
                new PluginNotFoundException('Plugin not found.'),
                HttpStatus::NOT_FOUND,
                'Not Found',
                'https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/404',
            ],
            'search upstream' => [
                new SearchUpstreamException('The search engine failed.'),
                HttpStatus::BAD_GATEWAY,
                'Bad Gateway',
                'https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/502',
            ],
        ];
    }

    #[DataProvider('provideExceptions')]
    public function testEachOneSaysItsStatusItsTitleAndWhereItIsExplained(
        HttpExceptionInterface $exception,
        HttpStatus $status,
        string $title,
        string $reference
    ): void {
        $this->assertSame($status, $exception->getStatus());
        $this->assertSame($title, $exception->getTitle());
        $this->assertSame($reference, $exception->getUriReference());
        $this->assertSame([], $exception->getContext());
    }

    /**
     * @return array<string, array{HttpExceptionInterface}>
     */
    public static function provideEachException(): array
    {
        return array_map(fn (array $case) => [$case[0]], self::provideExceptions());
    }

    #[DataProvider('provideEachException')]
    public function testTheHeadersThatAreGivenAreTheOnesItHas(HttpExceptionInterface $exception): void
    {
        $this->assertSame([], $exception->getHeaders());

        $exception->setHeaders(['Retry-After' => '10']);

        $this->assertSame(['Retry-After' => '10'], $exception->getHeaders());
    }

    public function testTheOnesThatWrapAnotherErrorKeepIt(): void
    {
        $previous = new RuntimeException('Connection refused.');

        $this->assertSame($previous, (new ContentNotFoundException('Not found.', $previous))->getPrevious());
        $this->assertSame($previous, (new SearchUpstreamException('Failed.', $previous))->getPrevious());
    }
}
