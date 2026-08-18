<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent\Plugin\Academy;

use Derafu\Content\Abstract\AbstractContentController;
use Derafu\Content\ContentAttachment;
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
use Derafu\Content\Plugin\Academy\AcademyController;
use Derafu\Content\Plugin\Academy\AcademyCourse;
use Derafu\Content\Plugin\Academy\AcademyLesson;
use Derafu\Content\Plugin\Academy\AcademyModule;
use Derafu\Content\Plugin\Academy\AcademyPlugin;
use Derafu\Content\Plugin\Academy\AcademyRegistry;
use Derafu\Http\Request;
use Derafu\Routing\ValueObject\RequestContext;
use Derafu\TestsContent\Support\ContentFixtures;
use Derafu\TestsContent\Support\RendererFixture;
use Derafu\TestsContent\Support\RouterFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Exercises every AcademyController action against real fixture content
 * with a real Twig render, through the real course/module/lesson layout —
 * none of the 5 actions (index, course, module, lesson, tag) had ever been
 * rendered end to end before this test, only AcademyRegistry directly.
 */
#[CoversClass(AcademyController::class)]
#[CoversClass(AbstractContentController::class)]
#[UsesClass(AcademyPlugin::class)]
#[UsesClass(AcademyRegistry::class)]
#[UsesClass(AcademyCourse::class)]
#[UsesClass(AcademyModule::class)]
#[UsesClass(AcademyLesson::class)]
#[UsesClass(ContentService::class)]
#[UsesClass(ContentAttachment::class)]
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
final class AcademyControllerTest extends TestCase
{
    private AcademyController $controller;

    protected function setUp(): void
    {
        $plugin = new AcademyPlugin(
            ContentFixtures::contentContext(),
            ['path' => 'academy']
        );
        $plugin->loadContent(new ContentLoader(ContentFixtures::contentPath()));

        $router = RouterFixture::create();
        $router->setContext(new RequestContext(pathInfo: '/academy'));

        $this->controller = new AcademyController(
            ContentFixtures::contentService(['academy' => $plugin]),
            RendererFixture::create($router)
        );
    }

    public function testIndexListsTheCourse(): void
    {
        $html = $this->controller->index();

        $this->assertStringContainsString('Curso Demo', $html);
    }

    public function testCourseActionRendersTheCoursePage(): void
    {
        $request = new Request('GET', 'http://localhost/academy/curso-demo');

        $html = $this->controller->course($request, 'curso-demo');

        $this->assertIsString($html);
        $this->assertStringContainsString('Curso Demo', $html);
        $this->assertStringContainsString('Módulo Uno', $html);
    }

    public function testModuleActionRendersTheModulePage(): void
    {
        $request = new Request('GET', 'http://localhost/academy/curso-demo/modulo-uno');

        $html = $this->controller->module($request, 'curso-demo', 'modulo-uno');

        $this->assertIsString($html);
        $this->assertStringContainsString('Módulo Uno', $html);
        $this->assertStringContainsString('Lección Uno', $html);
        $this->assertStringContainsString('Lección Dos', $html);
    }

    public function testLessonActionRendersTheLessonPageWithItsAttachmentTest(): void
    {
        $request = new Request('GET', 'http://localhost/academy/curso-demo/modulo-uno/leccion-uno');

        $html = $this->controller->lesson($request, 'curso-demo', 'modulo-uno', 'leccion-uno');

        $this->assertIsString($html);
        $this->assertStringContainsString('Lección Uno', $html);
    }

    public function testLessonActionJsonFormatIncludesTheAttachmentBackedTestField(): void
    {
        $request = new Request('GET', 'http://localhost/academy/curso-demo/modulo-uno/leccion-uno.json');

        $result = $this->controller->lesson($request, 'curso-demo', 'modulo-uno', 'leccion-uno.json');

        $this->assertIsArray($result);
        $this->assertSame('leccion-uno', $result['data']['slug']);
    }

    public function testUnknownModuleBubblesAsContentNotFoundException(): void
    {
        $request = new Request('GET', 'http://localhost/academy/curso-demo/no-existe');

        $this->expectException(ContentNotFoundException::class);

        $this->controller->module($request, 'curso-demo', 'no-existe');
    }

    public function testUnknownLessonBubblesAsContentNotFoundException(): void
    {
        $request = new Request('GET', 'http://localhost/academy/curso-demo/modulo-uno/no-existe');

        $this->expectException(ContentNotFoundException::class);

        $this->controller->lesson($request, 'curso-demo', 'modulo-uno', 'no-existe');
    }

    public function testTagActionFiltersCoursesByTag(): void
    {
        $request = new Request('GET', 'http://localhost/academy/tags/demo');

        $html = $this->controller->tag($request, 'demo');

        $this->assertStringContainsString('Curso Demo', $html);
    }
}
