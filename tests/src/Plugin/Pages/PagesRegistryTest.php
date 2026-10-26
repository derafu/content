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

use Derafu\Content\Abstract\AbstractContentItem;
use Derafu\Content\ContentBag;
use Derafu\Content\ContentConfig;
use Derafu\Content\ContentContext;
use Derafu\Content\ContentLoader;
use Derafu\Content\ContentSplFileInfo;
use Derafu\Content\Exception\ContentNotFoundException;
use Derafu\Content\Plugin\Docs\DocsDoc;
use Derafu\Content\Plugin\Pages\PagesPage;
use Derafu\Content\Plugin\Pages\PagesPlugin;
use Derafu\Content\Plugin\Pages\PagesRegistry;
use Derafu\TestsContent\Support\ContentFixtures;
use Derafu\TestsContent\Support\RouterFixture;
use Derafu\Translation\Contract\TranslatableInterface;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the Pages plugin against real fixture pages on disk, no mocks:
 * the registry returns PagesPageInterface from get()/previous()/next(), and
 * loadContent() reads "include"/"exclude" as arrays. Neither can be checked
 * by reading the code; they only appear once loadContent()+get() run.
 */
#[CoversClass(PagesPlugin::class)]
#[CoversClass(PagesRegistry::class)]
#[CoversClass(PagesPage::class)]
#[UsesClass(AbstractContentItem::class)]
#[UsesClass(DocsDoc::class)]
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
        $this->assertSame(['about', 'draft', 'welcome'], $slugs);
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
        // error either. "draft" is a draft, excluded from the allowed
        // list, so among the allowed pages "about" is last (previous:
        // "welcome", no next).
        $this->assertNull($registry->next($about->uri()));
        $this->assertInstanceOf(PagesPage::class, $registry->previous($about->uri()));
        $this->assertSame('welcome', $registry->previous($about->uri())->uri());
    }

    public function testDraftPageIsNotAllowedOutsideALocalEnvironment(): void
    {
        // all() returns every loaded item regardless of allowed(); get()
        // itself already refuses to return a draft outside a local
        // environment (see the next assertion), which is the behavior
        // being tested here.
        $draft = $this->plugin->registry()->all()['draft'];

        $this->assertTrue($draft->draft());
        $this->assertFalse($draft->allowed());

        $this->expectException(ContentNotFoundException::class);
        $this->plugin->registry()->get('draft');
    }

    public function testUnknownUriThrowsContentNotFoundException(): void
    {
        $this->expectException(ContentNotFoundException::class);

        $this->plugin->registry()->get('does-not-exist');
    }

    public function testTheFirstPageHasNoPreviousAndItsNextIsTheSecond(): void
    {
        $registry = $this->plugin->registry();

        $this->assertNull($registry->previous('welcome'));
        $this->assertSame('about', $registry->next('welcome')?->uri());
    }

    public function testPagesLinkToTheirConfiguredRouteWithoutACollection(): void
    {
        $page = $this->plugin->registry()->get('about');

        $this->assertSame(
            ['self' => ['href' => '/pages/about']],
            $page->links(RouterFixture::create())
        );
    }

    public function testPagesReturnTheirAssignedParent(): void
    {
        $parent = new PagesPage(ContentFixtures::contentPath('pages-routing/about.md'));
        $child = new PagesPage(ContentFixtures::contentPath('pages-routing/about/team.md'));
        $parent->addChild($child);

        $this->assertNull($parent->parent());
        $this->assertSame($parent, $child->parent());
        $this->assertSame('about/team café', $child->uri());
    }

    public function testAPageReportsAnInvalidParentWithATranslatableException(): void
    {
        $page = new PagesPage(ContentFixtures::contentPath('pages/about.md'));
        $page->setParent(new DocsDoc(ContentFixtures::contentPath('docs/guide.md')));

        try {
            $page->parent();
            $this->fail('A page must not silently lose an incompatible parent.');
        } catch (LogicException $exception) {
            $this->assertInstanceOf(TranslatableInterface::class, $exception);
            $this->assertStringContainsString(DocsDoc::class, $exception->getMessage());
        }
    }
}
