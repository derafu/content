<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent\Abstract;

use Derafu\Content\Abstract\AbstractContentItem;
use Derafu\Content\Abstract\AbstractContentRegistry;
use Derafu\Content\ContentBag;
use Derafu\Content\ContentConfig;
use Derafu\Content\ContentContext;
use Derafu\Content\ContentLoader;
use Derafu\Content\ContentSplFileInfo;
use Derafu\Content\Plugin\Docs\DocsDoc;
use Derafu\Content\Plugin\Docs\DocsPlugin;
use Derafu\Content\Plugin\Docs\DocsRegistry;
use Derafu\TestsContent\Support\ContentFixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Exercises AbstractContentItem::data()/image()'s asset_base_url /
 * link_base_url resolution directly against the real "guia" fixture (which
 * has a root-relative frontmatter image, a root-relative Markdown image in
 * its body, and a root-relative Markdown link to its child doc).
 *
 * asset_base_url and link_base_url are deliberately independent: the .md
 * export passes both (so the body is self-contained when consumed outside
 * this website, e.g. by an AI agent), while the PDF export passes only
 * link_base_url, leaving images root-relative so derafu-renderer's own
 * disk-based image resolution keeps working.
 */
#[CoversClass(AbstractContentItem::class)]
#[UsesClass(AbstractContentRegistry::class)]
#[UsesClass(ContentBag::class)]
#[UsesClass(ContentConfig::class)]
#[UsesClass(ContentContext::class)]
#[UsesClass(ContentLoader::class)]
#[UsesClass(ContentSplFileInfo::class)]
#[UsesClass(DocsPlugin::class)]
#[UsesClass(DocsDoc::class)]
#[UsesClass(DocsRegistry::class)]
final class AbstractContentItemUrlResolutionTest extends TestCase
{
    private const BASE_URL = 'http://localhost';

    private DocsDoc $guia;

    protected function setUp(): void
    {
        $plugin = new DocsPlugin(
            ContentFixtures::contentContext(url: self::BASE_URL),
            ['path' => 'docs']
        );
        $plugin->loadContent(new ContentLoader(ContentFixtures::contentPath()));

        $doc = $plugin->registry()->get('guia');
        assert($doc instanceof DocsDoc);
        $this->guia = $doc;
    }

    public function testDataWithoutOptionsLeavesRootRelativePathsUntouched(): void
    {
        $markdown = $this->guia->data();

        $this->assertStringContainsString(
            '![Diagrama de la guía](/img/content/docs/guia/diagrama.png)',
            $markdown
        );
        $this->assertStringContainsString(
            '[este artículo hijo](/docs/guia/primeros-pasos)',
            $markdown
        );
    }

    public function testDataWithBothOptionsResolvesImageAndLinkToAbsoluteUrls(): void
    {
        $markdown = $this->guia->data([
            'asset_base_url' => self::BASE_URL,
            'link_base_url' => self::BASE_URL,
        ]);

        $this->assertStringContainsString(
            '![Diagrama de la guía](http://localhost/img/content/docs/guia/diagrama.png)',
            $markdown
        );
        $this->assertStringContainsString(
            '[este artículo hijo](http://localhost/docs/guia/primeros-pasos)',
            $markdown
        );
    }

    /**
     * This is exactly what the .pdf.twig templates pass: only
     * link_base_url, so the link becomes clickable but the image stays
     * root-relative for derafu-renderer's disk-based resolution.
     */
    public function testDataWithOnlyLinkBaseUrlResolvesLinkButLeavesImageUntouched(): void
    {
        $markdown = $this->guia->data(['link_base_url' => self::BASE_URL]);

        $this->assertStringContainsString(
            '![Diagrama de la guía](/img/content/docs/guia/diagrama.png)',
            $markdown
        );
        $this->assertStringContainsString(
            '[este artículo hijo](http://localhost/docs/guia/primeros-pasos)',
            $markdown
        );
    }

    /**
     * The .pdf.twig templates never pass asset_base_url, but this proves
     * the symmetrical case too: only images resolve, links stay untouched.
     */
    public function testDataWithOnlyAssetBaseUrlResolvesImageButLeavesLinkUntouched(): void
    {
        $markdown = $this->guia->data(['asset_base_url' => self::BASE_URL]);

        $this->assertStringContainsString(
            '![Diagrama de la guía](http://localhost/img/content/docs/guia/diagrama.png)',
            $markdown
        );
        $this->assertStringContainsString(
            '[este artículo hijo](/docs/guia/primeros-pasos)',
            $markdown
        );
    }

    public function testImageWithoutOptionsReturnsTheRootRelativePath(): void
    {
        $this->assertSame(
            '/img/content/docs/guia/cover.png',
            $this->guia->image()
        );
    }

    public function testImageWithAssetBaseUrlResolvesToAnAbsoluteUrl(): void
    {
        $this->assertSame(
            'http://localhost/img/content/docs/guia/cover.png',
            $this->guia->image(['asset_base_url' => self::BASE_URL])
        );
    }
}
