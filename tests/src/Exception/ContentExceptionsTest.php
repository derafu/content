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

use Closure;
use Derafu\Content\ContentAttachment;
use Derafu\Content\ContentConfig;
use Derafu\Content\ContentContext;
use Derafu\Content\ContentService;
use Derafu\Content\Plugin\Academy\AcademyTest;
use Derafu\Content\Plugin\Docs\DocsDoc;
use Derafu\Content\Plugin\Docs\DocsOpenApiSpec;
use Derafu\Content\Plugin\Search\SearchController;
use Derafu\Content\Plugin\Search\SearchPlugin;
use Derafu\Content\RemoteContentFetcher;
use Derafu\Http\Request;
use Derafu\TestsContent\Support\ContentFixtures;
use Derafu\TestsContent\Support\RendererFixture;
use Derafu\Translation\Contract\TranslatableInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * The errors that the package raises with the exceptions of PHP are
 * translatable, and say what they said before.
 */
#[CoversClass(ContentService::class)]
#[CoversClass(ContentAttachment::class)]
#[CoversClass(DocsDoc::class)]
#[CoversClass(DocsOpenApiSpec::class)]
#[CoversClass(AcademyTest::class)]
#[CoversClass(SearchController::class)]
#[CoversClass(SearchPlugin::class)]
#[CoversClass(RemoteContentFetcher::class)]
#[UsesClass(ContentConfig::class)]
#[UsesClass(ContentContext::class)]
final class ContentExceptionsTest extends TestCase
{
    /**
     * @return array<string, array{Closure, class-string<Throwable>, string}>
     */
    public static function failuresProvider(): array
    {
        return [
            'unknown plugin' => [
                fn () => ContentFixtures::contentService([])->plugin('missing'),
                \InvalidArgumentException::class,
                'Plugin "missing" not found. Available plugins: .',
            ],
            'attachment that is not a file' => [
                fn () => new ContentAttachment('/does/not/exist.pdf', new DocsDoc(ContentFixtures::contentPath() . '/docs/guia.md')),
                \InvalidArgumentException::class,
                'Path  must be a readable attachment.',
            ],
            'content that is not a file' => [
                fn () => new DocsDoc('/does/not/exist.md'),
                \InvalidArgumentException::class,
                'Path  must be a readable file content.',
            ],
            'invalid openapi' => [
                fn () => DocsOpenApiSpec::fromRaw("openapi: 3.0.3\n  bad indent: ["),
                \RuntimeException::class,
                'Invalid OpenAPI document: ',
            ],
            'invalid academy test' => [
                fn () => AcademyTest::fromJson('{not json'),
                \RuntimeException::class,
                'Invalid academy test JSON: ',
            ],
            'search without a query' => [
                fn () => (new SearchController(ContentFixtures::contentService([]), RendererFixture::create()))
                    ->api_index(new Request('GET', 'http://localhost/api/content/search.json')),
                \InvalidArgumentException::class,
                'Query is required.',
            ],
            'llm without a model' => [
                fn () => (new SearchPlugin(ContentFixtures::contentContext(), ['url' => 'http://localhost/?q=%s', 'llm_url' => 'http://localhost/llm']))->llm(),
                \InvalidArgumentException::class,
                'The "search" plugin has "llm_url" configured but no "llm_model". Set "llm_model" to the model name your LLM backend expects.',
            ],
            'remote content that can not be fetched' => [
                fn () => (new RemoteContentFetcher())->fetch('http://127.0.0.1:19899/'),
                \RuntimeException::class,
                'Remote content at http://127.0.0.1:19899/ could not be fetched: ',
            ],
        ];
    }

    /**
     * @param class-string<Throwable> $class
     */
    #[DataProvider('failuresProvider')]
    public function testTheFailureIsATranslatableErrorThatSaysTheSame(Closure $failure, string $class, string $message): void
    {
        $exception = null;
        try {
            $failure();
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertInstanceOf($class, $exception);
        $this->assertInstanceOf(TranslatableInterface::class, $exception);
        $this->assertStringStartsWith($message, $exception->getMessage());
    }
}
