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

use DateTime;
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

    /**
     * "borrador-padre" is draft, its child "hijo-visible" is not. allowed()
     * must still cascade: a non-draft item under a draft ancestor is not
     * allowed either, and get() must refuse it the same way it refuses the
     * draft section itself — otherwise the child stays reachable by a
     * direct URI even though the section that contains it is hidden.
     */
    public function testDraftSectionMakesItsNonDraftChildNotAllowedToo(): void
    {
        $child = $this->plugin->registry()->all()['borrador-padre']->children()['hijo-visible'];

        $this->assertFalse($child->draft());
        $this->assertFalse($child->allowed());

        $this->expectException(ContentNotFoundException::class);
        $this->plugin->registry()->get('borrador-padre/hijo-visible');
    }

    /**
     * "seccion-visible" is not draft, but two of its children are hidden
     * for different reasons: "nota-borrador" is draft, "nota-no-listada"
     * is unlisted. visibleChildren() is what a sidebar/nested menu must
     * use instead of children(): the parent itself stays visible, but
     * neither hidden reason must leak a child through it.
     */
    public function testVisibleChildrenExcludesADraftChildOfAnAllowedItem(): void
    {
        $section = $this->plugin->registry()->all()['seccion-visible'];

        $this->assertCount(2, $section->children());
        $this->assertCount(0, $section->visibleChildren());
    }

    /**
     * Same fixture, isolating the unlisted case specifically: unlike
     * draft (which makes allowed() false), an unlisted child is still
     * allowed() — it must be visibleChildren() itself that also checks
     * unlisted(), the same way the registry's matches() already does for
     * the top level.
     */
    public function testVisibleChildrenExcludesAnUnlistedChildOfAnAllowedItem(): void
    {
        $section = $this->plugin->registry()->all()['seccion-visible'];
        $unlistedChild = $section->children()['nota-no-listada'];

        $this->assertTrue($unlistedChild->allowed());
        $this->assertTrue($unlistedChild->unlisted());
        $this->assertArrayNotHasKey('nota-no-listada', $section->visibleChildren());
    }

    /**
     * filterTree() is the tree-preserving counterpart of filter(): it must
     * drop "borrador-padre" (draft) from the top level while keeping the
     * real hierarchy of the docs that remain, unlike filter()/flatten()
     * which return a flat list.
     */
    public function testFilterTreeExcludesADraftTopLevelSectionButKeepsHierarchy(): void
    {
        $tree = $this->plugin->registry()->filterTree();

        $this->assertArrayHasKey('index', $tree);
        $this->assertArrayHasKey('guia', $tree);
        $this->assertArrayNotHasKey('borrador-padre', $tree);
        $this->assertArrayHasKey('primeros-pasos', $tree['guia']->children());
    }

    /**
     * "no-listado" is not draft, only unlisted: it must remain reachable
     * by a direct get() (the documented "still reachable by direct URL"),
     * while filter()/filterTree() (a listing) must exclude it — unless the
     * caller explicitly filters by id or uri, which is the documented
     * "unless explicitly filtered by id/uri" carve-out. That carve-out is
     * implemented in matches() but, before this test, was never exercised
     * by any caller in this repo.
     */
    public function testUnlistedDocIsReachableDirectlyButExcludedFromListings(): void
    {
        $doc = $this->plugin->registry()->get('no-listado');
        $this->assertTrue($doc->unlisted());

        $uris = array_map(fn ($item) => $item->uri(), $this->plugin->registry()->filter());
        $this->assertNotContains('no-listado', $uris);

        $tree = $this->plugin->registry()->filterTree();
        $this->assertArrayNotHasKey('no-listado', $tree);

        $byUri = $this->plugin->registry()->filter(['uri' => 'no-listado']);
        $this->assertCount(1, $byUri);
        $this->assertSame('no-listado', $byUri[0]->uri());

        $byId = $this->plugin->registry()->filter(['id' => $doc->id()]);
        $this->assertCount(1, $byId);
        $this->assertSame('no-listado', $byId[0]->uri());
    }

    /**
     * indexable and searchable must be settable independently of each
     * other, each defaulting to true unless explicitly disabled (or the
     * item is draft/unlisted/deprecated, which defaults both to false).
     */
    public function testIndexableAndSearchableAreIndependentFlags(): void
    {
        $notIndexable = $this->plugin->registry()->get('no-indexable');
        $this->assertFalse($notIndexable->indexable());
        $this->assertTrue($notIndexable->searchable());

        $notSearchable = $this->plugin->registry()->get('no-buscable');
        $this->assertTrue($notSearchable->indexable());
        $this->assertFalse($notSearchable->searchable());
    }

    /**
     * The optional "searchable" filter criterion in matches() itself, in
     * isolation: never exercised by any test before, and — per the
     * broader investigation — never invoked by any real caller either
     * (the search plugin proxies to an external engine and never
     * consults this at all).
     */
    public function testFilterBySearchableMatchesOnlyNonSearchableItems(): void
    {
        $matches = $this->plugin->registry()->filter(['searchable' => false]);
        $uris = array_map(fn ($item) => $item->uri(), $matches);

        $this->assertContains('no-buscable', $uris);
        $this->assertNotContains('no-indexable', $uris);
    }

    /**
     * "futuro-deprecado" has `deprecated: "2099-01-01"` — a date far in
     * the future. Per the documented field ("a string/timestamp sets a
     * specific deprecation date"), this must NOT be treated as already
     * deprecated: deprecated() (the boolean gate) must stay false, and
     * indexable()/searchable() must stay true, until that date actually
     * arrives. deprecatedAt() keeps exposing the raw configured date
     * regardless — needed for display, e.g. an eventual "will be
     * deprecated starting {date}" notice.
     */
    public function testFutureDeprecationDateDoesNotDisableIndexableOrSearchableYet(): void
    {
        $doc = $this->plugin->registry()->get('futuro-deprecado');

        $this->assertNotNull($doc->deprecatedAt());
        $this->assertGreaterThan(new DateTime(), $doc->deprecatedAt());
        $this->assertFalse($doc->deprecated());
        $this->assertTrue($doc->indexable());
        $this->assertTrue($doc->searchable());
    }

    /**
     * Control/regression case: "pasado-deprecado" has `deprecated:
     * "2020-01-01"` — already in the past — so it must already act as
     * deprecated today: deprecated() true, indexable()/searchable() false.
     */
    public function testPastDeprecationDateDisablesIndexableAndSearchable(): void
    {
        $doc = $this->plugin->registry()->get('pasado-deprecado');

        $this->assertNotNull($doc->deprecatedAt());
        $this->assertLessThan(new DateTime(), $doc->deprecatedAt());
        $this->assertTrue($doc->deprecated());
        $this->assertFalse($doc->indexable());
        $this->assertFalse($doc->searchable());
    }

    /**
     * "deprecado-por-mtime" has `deprecated: true` (boolean) — per the
     * documented field ("true uses the file's modification time"), this
     * must resolve to the file's real mtime (always in the past relative
     * to "now", for a fixture file already on disk) and therefore act as
     * already deprecated. Never exercised by any test before this one.
     */
    public function testDeprecatedTrueResolvesToTheFileModificationTime(): void
    {
        $doc = $this->plugin->registry()->get('deprecado-por-mtime');

        $this->assertNotNull($doc->deprecatedAt());
        $this->assertLessThanOrEqual(new DateTime(), $doc->deprecatedAt());
        $this->assertTrue($doc->deprecated());
        $this->assertFalse($doc->indexable());
        $this->assertFalse($doc->searchable());
    }

    /**
     * "borrador-padre" is also useful here, but a dedicated fixture makes
     * the intent explicit: "seccion-futura" has a publish date far in the
     * future; its child "hijo-de-seccion-futura" does NOT — it has its
     * own, already-past date. The child must still be unpublished: date()
     * cascades the same way draft() does (an ancestor's own date gates
     * the whole branch), without the child ever adopting the ancestor's
     * date value itself.
     */
    public function testFutureDatedSectionMakesItsOwnPastDatedChildNotAllowedToo(): void
    {
        $child = $this->plugin->registry()->all()['seccion-futura']->children()['hijo-de-seccion-futura'];

        $this->assertLessThan(new DateTime(), $child->date());
        $this->assertFalse($child->allowed());

        $this->expectException(ContentNotFoundException::class);
        $this->plugin->registry()->get('seccion-futura/hijo-de-seccion-futura');
    }
}
