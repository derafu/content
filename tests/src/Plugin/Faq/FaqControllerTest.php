<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent\Plugin\Faq;

use Derafu\Content\Abstract\AbstractContentController;
use Derafu\Content\ContentAuthor;
use Derafu\Content\ContentBag;
use Derafu\Content\ContentConfig;
use Derafu\Content\ContentContext;
use Derafu\Content\ContentHtmlTag;
use Derafu\Content\ContentHtmlTags;
use Derafu\Content\ContentLoader;
use Derafu\Content\ContentService;
use Derafu\Content\ContentSplFileInfo;
use Derafu\Content\ContentTag;
use Derafu\Content\Exception\ContentNotFoundException;
use Derafu\Content\Plugin\Faq\FaqController;
use Derafu\Content\Plugin\Faq\FaqPlugin;
use Derafu\Content\Plugin\Faq\FaqQuestion;
use Derafu\Content\Plugin\Faq\FaqRegistry;
use Derafu\Http\Request;
use Derafu\Routing\ValueObject\RequestContext;
use Derafu\TestsContent\Support\ContentFixtures;
use Derafu\TestsContent\Support\RendererFixture;
use Derafu\TestsContent\Support\RouterFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Exercises FaqController end to end with a real Twig render, never
 * before rendered in this session (only FaqRegistry directly).
 */
#[CoversClass(FaqController::class)]
#[CoversClass(AbstractContentController::class)]
#[UsesClass(FaqPlugin::class)]
#[UsesClass(FaqRegistry::class)]
#[UsesClass(FaqQuestion::class)]
#[UsesClass(ContentService::class)]
#[UsesClass(ContentAuthor::class)]
#[UsesClass(ContentBag::class)]
#[UsesClass(ContentConfig::class)]
#[UsesClass(ContentContext::class)]
#[UsesClass(ContentHtmlTag::class)]
#[UsesClass(ContentHtmlTags::class)]
#[UsesClass(ContentLoader::class)]
#[UsesClass(ContentSplFileInfo::class)]
#[UsesClass(ContentTag::class)]
#[UsesClass(ContentNotFoundException::class)]
final class FaqControllerTest extends TestCase
{
    private FaqController $controller;

    protected function setUp(): void
    {
        $plugin = new FaqPlugin(
            ContentFixtures::contentContext(),
            ['path' => 'faq']
        );
        $plugin->loadContent(new ContentLoader(ContentFixtures::contentPath()));

        $router = RouterFixture::create();
        $router->setContext(new RequestContext(pathInfo: '/faq/pregunta-uno'));

        $this->controller = new FaqController(
            ContentFixtures::contentService(['faq' => $plugin]),
            RendererFixture::create($router)
        );
    }

    public function testShowRendersARealQuestion(): void
    {
        $request = new Request('GET', 'http://localhost/faq/pregunta-uno');

        $html = $this->controller->show($request, 'pregunta-uno');

        $this->assertIsString($html);
        $this->assertStringContainsString('¿Cómo se prueba este paquete?', $html);
    }

    public function testShowJsonFormatReturnsTheFullArray(): void
    {
        $request = new Request('GET', 'http://localhost/faq/pregunta-uno.json');

        $result = $this->controller->show($request, 'pregunta-uno.json');

        $this->assertIsArray($result);
        $this->assertSame('pregunta-uno', $result['data']['uri']);
    }

    public function testUnknownQuestionBubblesAsContentNotFoundException(): void
    {
        $request = new Request('GET', 'http://localhost/faq/no-existe');

        $this->expectException(ContentNotFoundException::class);

        $this->controller->show($request, 'no-existe');
    }

    public function testTagActionFiltersQuestions(): void
    {
        $request = new Request('GET', 'http://localhost/faq/tags/tests');

        $html = $this->controller->tag($request, 'tests');

        $this->assertStringContainsString('¿Cómo se prueba este paquete?', $html);
    }
}
