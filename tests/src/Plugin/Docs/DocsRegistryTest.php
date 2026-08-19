<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent\Plugin\Docs;

use Derafu\Content\ContentAttachment;
use Derafu\Content\ContentBag;
use Derafu\Content\ContentConfig;
use Derafu\Content\ContentContext;
use Derafu\Content\ContentLoader;
use Derafu\Content\ContentSplFileInfo;
use Derafu\Content\ContentTag;
use Derafu\Content\Exception\ContentNotFoundException;
use Derafu\Content\Plugin\Docs\DocsDoc;
use Derafu\Content\Plugin\Docs\DocsOpenApiEndpoint;
use Derafu\Content\Plugin\Docs\DocsOpenApiParameter;
use Derafu\Content\Plugin\Docs\DocsOpenApiResponse;
use Derafu\Content\Plugin\Docs\DocsOpenApiSpec;
use Derafu\Content\Plugin\Docs\DocsOpenApiTag;
use Derafu\Content\Plugin\Docs\DocsPlugin;
use Derafu\Content\Plugin\Docs\DocsRegistry;
use Derafu\TestsContent\Support\ContentFixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the Docs plugin against real fixture docs on disk, no mocks,
 * including the "missing index file" hierarchy footgun documented for
 * every nesting-capable content type.
 */
#[CoversClass(DocsPlugin::class)]
#[CoversClass(DocsRegistry::class)]
#[CoversClass(DocsDoc::class)]
#[UsesClass(ContentAttachment::class)]
#[UsesClass(ContentBag::class)]
#[UsesClass(ContentConfig::class)]
#[UsesClass(ContentContext::class)]
#[UsesClass(ContentLoader::class)]
#[UsesClass(ContentSplFileInfo::class)]
#[UsesClass(ContentTag::class)]
#[UsesClass(ContentNotFoundException::class)]
#[UsesClass(DocsOpenApiSpec::class)]
#[UsesClass(DocsOpenApiEndpoint::class)]
#[UsesClass(DocsOpenApiParameter::class)]
#[UsesClass(DocsOpenApiResponse::class)]
#[UsesClass(DocsOpenApiTag::class)]
final class DocsRegistryTest extends TestCase
{
    private DocsPlugin $plugin;

    protected function setUp(): void
    {
        $this->plugin = new DocsPlugin(
            ContentFixtures::contentContext(),
            ['path' => 'docs']
        );
        $this->plugin->loadContent(new ContentLoader(ContentFixtures::contentPath()));
    }

    public function testTopLevelDocsAreLoaded(): void
    {
        $all = $this->plugin->registry()->all();

        $this->assertArrayHasKey('index', $all);
        $this->assertArrayHasKey('guia', $all);
    }

    public function testChildDocIsReachableThroughItsParentUri(): void
    {
        $doc = $this->plugin->registry()->get('guia/primeros-pasos');

        $this->assertSame('Primeros pasos', $doc->title());
        $this->assertSame('guia', $doc->parent()?->slug());
        $this->assertSame(2, $doc->level());
    }

    /**
     * The single most common cause of "my content doesn't show up": a
     * subdirectory needs a file with the *same name* as the directory to
     * represent that level itself. "docs/huerfano/hijo-perdido.md" exists
     * on disk but "docs/huerfano.md" does not, so the whole subdirectory
     * — including its child — must be silently skipped by the loader.
     */
    public function testDirectoryWithoutAMatchingIndexFileIsSilentlySkipped(): void
    {
        $all = $this->plugin->registry()->all();

        $this->assertArrayNotHasKey('huerfano', $all);

        $this->expectException(ContentNotFoundException::class);
        $this->plugin->registry()->get('huerfano/hijo-perdido');
    }

    public function testGetWithFileExtensionSuffixStillResolves(): void
    {
        $doc = $this->plugin->registry()->get('guia/primeros-pasos.md');

        $this->assertSame('primeros-pasos', $doc->slug());
    }

    public function testFilterBySearchMatchesTitleDescriptionAndBody(): void
    {
        $matches = $this->plugin->registry()->filter(['search' => 'primeros pasos']);

        $this->assertCount(1, $matches);
        $this->assertSame('primeros-pasos', $matches[0]->slug());
    }

    public function testUnknownUriThrowsContentNotFoundException(): void
    {
        $this->expectException(ContentNotFoundException::class);

        $this->plugin->registry()->get('no-existe');
    }

    public function testOpenapiFieldResolvesAndParsesTheLocalAttachment(): void
    {
        $doc = $this->plugin->registry()->get('api');

        $spec = $doc->openapiSpec();

        $this->assertInstanceOf(DocsOpenApiSpec::class, $spec);
        $this->assertSame('Fixture API', $spec->title());
        $this->assertCount(3, $spec->endpoints());
        $this->assertSame('GET', $spec->endpoints()[0]->method());
        $this->assertSame('/widgets', $spec->endpoints()[0]->path());
    }

    public function testDocWithoutOpenapiFieldHasNoSpec(): void
    {
        $doc = $this->plugin->registry()->get('guia');

        $this->assertNull($doc->openapiSpec());
    }
}
