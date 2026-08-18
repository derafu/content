<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent\Plugin\Pages;

use Derafu\Content\ContentBag;
use Derafu\Content\ContentConfig;
use Derafu\Content\ContentContext;
use Derafu\Content\ContentLoader;
use Derafu\Content\ContentSplFileInfo;
use Derafu\Content\Exception\ContentNotFoundException;
use Derafu\Content\Plugin\Pages\PagesPage;
use Derafu\Content\Plugin\Pages\PagesPlugin;
use Derafu\Content\Plugin\Pages\PagesRegistry;
use Derafu\TestsContent\Support\ContentFixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the Pages plugin against real fixture pages on disk, no mocks.
 *
 * This is exactly the class of test that would have caught, on the very
 * first run, the two real bugs found this session while wiring the Pages
 * plugin for the first time:
 *
 *   1. PagesRegistry did not override get()/previous()/next() with the
 *      covariant PagesPageInterface return type, which is a PHP fatal
 *      error the moment the registry is actually used.
 *   2. PagesPlugin::loadContent() passed the raw Options object for
 *      "include"/"exclude" instead of calling ->all() on it, which is a
 *      TypeError the moment content is actually loaded.
 *
 * Neither of those bugs could ever surface just by reading the code; they
 * only appear once loadContent()+get() actually run.
 */
#[CoversClass(PagesPlugin::class)]
#[CoversClass(PagesRegistry::class)]
#[CoversClass(PagesPage::class)]
#[UsesClass(ContentBag::class)]
#[UsesClass(ContentConfig::class)]
#[UsesClass(ContentContext::class)]
#[UsesClass(ContentLoader::class)]
#[UsesClass(ContentSplFileInfo::class)]
#[UsesClass(ContentNotFoundException::class)]
final class PagesRegistryTest extends TestCase
{
    private PagesPlugin $plugin;

    protected function setUp(): void
    {
        $this->plugin = new PagesPlugin(
            ContentFixtures::contentContext(),
            ['path' => 'pages']
        );
        $this->plugin->loadContent(new ContentLoader(ContentFixtures::contentPath()));
    }

    public function testAllFixturePagesAreLoaded(): void
    {
        $slugs = array_keys($this->plugin->registry()->all());

        sort($slugs);
        $this->assertSame(['about', 'bienvenida', 'borrador'], $slugs);
    }

    public function testGetReturnsAPagesPageWithoutAFatalCovarianceError(): void
    {
        $page = $this->plugin->registry()->get('about');

        $this->assertInstanceOf(PagesPage::class, $page);
        $this->assertSame('pages', $page->type());
        $this->assertSame('page', $page->category());
    }

    public function testPreviousAndNextReturnPagesPageInstances(): void
    {
        $registry = $this->plugin->registry();
        $about = $registry->get('about');

        // Calling next()/previous() must not trigger the covariance fatal
        // error either. "borrador" is a draft, excluded from the allowed
        // list, so among the allowed pages "about" is last (previous:
        // "bienvenida", no next).
        $this->assertNull($registry->next($about->uri()));
        $this->assertInstanceOf(PagesPage::class, $registry->previous($about->uri()));
        $this->assertSame('bienvenida', $registry->previous($about->uri())->uri());
    }

    public function testDraftPageIsNotAllowedOutsideALocalEnvironment(): void
    {
        // all() returns every loaded item regardless of allowed(); get()
        // itself already refuses to return a draft outside a local
        // environment (see the next assertion), which is the behavior
        // being tested here.
        $draft = $this->plugin->registry()->all()['borrador'];

        $this->assertTrue($draft->draft());
        $this->assertFalse($draft->allowed());

        $this->expectException(ContentNotFoundException::class);
        $this->plugin->registry()->get('borrador');
    }

    public function testUnknownUriThrowsContentNotFoundException(): void
    {
        $this->expectException(ContentNotFoundException::class);

        $this->plugin->registry()->get('no-existe');
    }
}
