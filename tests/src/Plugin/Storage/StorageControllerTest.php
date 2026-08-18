<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent\Plugin\Storage;

use Derafu\Content\ContentAttachment;
use Derafu\Content\ContentAuthor;
use Derafu\Content\ContentBag;
use Derafu\Content\ContentConfig;
use Derafu\Content\ContentContext;
use Derafu\Content\ContentLoader;
use Derafu\Content\ContentService;
use Derafu\Content\ContentSplFileInfo;
use Derafu\Content\ContentTag;
use Derafu\Content\Exception\ContentNotFoundException;
use Derafu\Content\Plugin\Academy\AcademyCourse;
use Derafu\Content\Plugin\Academy\AcademyLesson;
use Derafu\Content\Plugin\Academy\AcademyModule;
use Derafu\Content\Plugin\Academy\AcademyPlugin;
use Derafu\Content\Plugin\Academy\AcademyRegistry;
use Derafu\Content\Plugin\Storage\StorageController;
use Derafu\Content\Plugin\Storage\StoragePlugin;
use Derafu\Http\Request;
use Derafu\TestsContent\Support\ContentFixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Exercises StorageController — never exercised at all this session — by
 * actually downloading the real quiz.json attachment of the Academy
 * fixture lesson through the full attachment-resolution path.
 */
#[CoversClass(StorageController::class)]
#[UsesClass(StoragePlugin::class)]
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
#[UsesClass(ContentLoader::class)]
#[UsesClass(ContentSplFileInfo::class)]
#[UsesClass(ContentTag::class)]
#[UsesClass(ContentNotFoundException::class)]
final class StorageControllerTest extends TestCase
{
    private StorageController $controller;

    protected function setUp(): void
    {
        $plugin = new AcademyPlugin(
            ContentFixtures::contentContext(),
            ['path' => 'academy']
        );
        $plugin->loadContent(new ContentLoader(ContentFixtures::contentPath()));

        $contentService = ContentFixtures::contentService([
            'storage' => new StoragePlugin(ContentFixtures::contentContext()),
            'academy' => $plugin,
        ]);

        $this->controller = new StorageController($contentService);
    }

    public function testDownloadsTheRealAttachmentFile(): void
    {
        $request = new Request('GET', 'http://localhost/academy/curso-demo/modulo-uno/leccion-uno/_attachments/quiz.json');

        $response = $this->controller->download(
            $request,
            'academy',
            'curso-demo/modulo-uno/leccion-uno',
            'quiz.json'
        );

        $this->assertStringContainsString('"questions"', (string) $response->getBody());
    }

    public function testUnknownContentUriThrowsContentNotFoundException(): void
    {
        $request = new Request('GET', 'http://localhost/academy/no-existe/_attachments/quiz.json');

        $this->expectException(ContentNotFoundException::class);

        $this->controller->download($request, 'academy', 'no-existe', 'quiz.json');
    }

    public function testUnknownAttachmentThrowsContentNotFoundException(): void
    {
        $request = new Request('GET', 'http://localhost/academy/curso-demo/modulo-uno/leccion-uno/_attachments/no-existe.json');

        $this->expectException(ContentNotFoundException::class);

        $this->controller->download(
            $request,
            'academy',
            'curso-demo/modulo-uno/leccion-uno',
            'no-existe.json'
        );
    }
}
