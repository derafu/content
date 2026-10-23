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
        $this->assertArrayHasKey('guide', $all);
    }

    public function testChildDocIsReachableThroughItsParentUri(): void
    {
        $doc = $this->plugin->registry()->get('guide/getting-started');

        $this->assertSame('Getting started', $doc->title());
        $this->assertSame('guide', $doc->parent()?->slug());
        $this->assertSame(2, $doc->level());
    }

    /**
     * The single most common cause of "my content doesn't show up": a
     * subdirectory needs a file with the *same name* as the directory to
     * represent that level itself. "docs/orphan/lost-child.md" exists
     * on disk but "docs/orphan.md" does not, so the whole subdirectory
     * — including its child — must be silently skipped by the loader.
     */
    public function testDirectoryWithoutAMatchingIndexFileIsSilentlySkipped(): void
    {
        $all = $this->plugin->registry()->all();

        $this->assertArrayNotHasKey('orphan', $all);

        $this->expectException(ContentNotFoundException::class);
        $this->plugin->registry()->get('orphan/lost-child');
    }

    public function testGetWithFileExtensionSuffixStillResolves(): void
    {
        $doc = $this->plugin->registry()->get('guide/getting-started.md');

        $this->assertSame('getting-started', $doc->slug());
    }

    public function testFilterBySearchMatchesTitleDescriptionAndBody(): void
    {
        $matches = $this->plugin->registry()->filter(['search' => 'getting started']);

        $this->assertCount(1, $matches);
        $this->assertSame('getting-started', $matches[0]->slug());
    }

    public function testUnknownUriThrowsContentNotFoundException(): void
    {
        $this->expectException(ContentNotFoundException::class);

        $this->plugin->registry()->get('does-not-exist');
    }

    public function testOpenapiFieldResolvesAndParsesTheLocalAttachment(): void
    {
        $doc = $this->plugin->registry()->get('api');

        $spec = $doc->openapiSpec();

        $this->assertNotNull($spec);
        $this->assertSame('Fixture API', $spec->info->title);
        $this->assertCount(3, $spec->operations());
        $this->assertSame('get', $spec->operations()[0]->method->value);
        $this->assertSame('/widgets', $spec->operations()[0]->path);
    }

    public function testDocWithoutOpenapiFieldHasNoSpec(): void
    {
        $doc = $this->plugin->registry()->get('guide');

        $this->assertNull($doc->openapiSpec());
    }

    /**
     * "draft-parent" is draft, its child "visible-child" is not. allowed()
     * must still cascade: a non-draft item under a draft ancestor is not
     * allowed either, and get() must refuse it the same way it refuses the
     * draft section itself — otherwise the child stays reachable by a
     * direct URI even though the section that contains it is hidden.
     */
    public function testDraftSectionMakesItsNonDraftChildNotAllowedToo(): void
    {
        $child = $this->plugin->registry()->all()['draft-parent']->children()['visible-child'];

        $this->assertFalse($child->draft());
        $this->assertFalse($child->allowed());

        $this->expectException(ContentNotFoundException::class);
        $this->plugin->registry()->get('draft-parent/visible-child');
    }

    /**
     * "visible-section" is not draft, but two of its children are hidden
     * for different reasons: "draft-note" is draft, "unlisted-note"
     * is unlisted. visibleChildren() is what a sidebar/nested menu must
     * use instead of children(): the parent itself stays visible, but
     * neither hidden reason must leak a child through it.
     */
    public function testVisibleChildrenExcludesADraftChildOfAnAllowedItem(): void
    {
        $section = $this->plugin->registry()->all()['visible-section'];

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
        $section = $this->plugin->registry()->all()['visible-section'];
        $unlistedChild = $section->children()['unlisted-note'];

        $this->assertTrue($unlistedChild->allowed());
        $this->assertTrue($unlistedChild->unlisted());
        $this->assertArrayNotHasKey('unlisted-note', $section->visibleChildren());
    }

    /**
     * filterTree() is the tree-preserving counterpart of filter(): it must
     * drop "draft-parent" (draft) from the top level while keeping the
     * real hierarchy of the docs that remain, unlike filter()/flatten()
     * which return a flat list.
     */
    public function testFilterTreeExcludesADraftTopLevelSectionButKeepsHierarchy(): void
    {
        $tree = $this->plugin->registry()->filterTree();

        $this->assertArrayHasKey('index', $tree);
        $this->assertArrayHasKey('guide', $tree);
        $this->assertArrayNotHasKey('draft-parent', $tree);
        $this->assertArrayHasKey('getting-started', $tree['guide']->children());
    }

    /**
     * "not-listed" is not draft, only unlisted: it must remain reachable
     * by a direct get() (the documented "still reachable by direct URL"),
     * while filter()/filterTree() (a listing) must exclude it — unless the
     * caller explicitly filters by id or uri, which is the documented
     * "unless explicitly filtered by id/uri" carve-out. That carve-out is
     * implemented in matches() but, before this test, was never exercised
     * by any caller in this repo.
     */
    public function testUnlistedDocIsReachableDirectlyButExcludedFromListings(): void
    {
        $doc = $this->plugin->registry()->get('not-listed');
        $this->assertTrue($doc->unlisted());

        $uris = array_map(fn ($item) => $item->uri(), $this->plugin->registry()->filter());
        $this->assertNotContains('not-listed', $uris);

        $tree = $this->plugin->registry()->filterTree();
        $this->assertArrayNotHasKey('not-listed', $tree);

        $byUri = $this->plugin->registry()->filter(['uri' => 'not-listed']);
        $this->assertCount(1, $byUri);
        $this->assertSame('not-listed', $byUri[0]->uri());

        $byId = $this->plugin->registry()->filter(['id' => $doc->id()]);
        $this->assertCount(1, $byId);
        $this->assertSame('not-listed', $byId[0]->uri());
    }

    /**
     * indexable and searchable must be settable independently of each
     * other, each defaulting to true unless explicitly disabled (or the
     * item is draft/unlisted/deprecated, which defaults both to false).
     */
    public function testIndexableAndSearchableAreIndependentFlags(): void
    {
        $notIndexable = $this->plugin->registry()->get('not-indexable');
        $this->assertFalse($notIndexable->indexable());
        $this->assertTrue($notIndexable->searchable());

        $notSearchable = $this->plugin->registry()->get('not-searchable');
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

        $this->assertContains('not-searchable', $uris);
        $this->assertNotContains('not-indexable', $uris);
    }

    /**
     * "future-deprecated" has `deprecated: "2099-01-01"` — a date far in
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
        $doc = $this->plugin->registry()->get('future-deprecated');

        $this->assertNotNull($doc->deprecatedAt());
        $this->assertGreaterThan(new DateTime(), $doc->deprecatedAt());
        $this->assertFalse($doc->deprecated());
        $this->assertTrue($doc->indexable());
        $this->assertTrue($doc->searchable());
    }

    /**
     * Control/regression case: "past-deprecated" has `deprecated:
     * "2020-01-01"` — already in the past — so it must already act as
     * deprecated today: deprecated() true, indexable()/searchable() false.
     */
    public function testPastDeprecationDateDisablesIndexableAndSearchable(): void
    {
        $doc = $this->plugin->registry()->get('past-deprecated');

        $this->assertNotNull($doc->deprecatedAt());
        $this->assertLessThan(new DateTime(), $doc->deprecatedAt());
        $this->assertTrue($doc->deprecated());
        $this->assertFalse($doc->indexable());
        $this->assertFalse($doc->searchable());
    }

    /**
     * "deprecated-by-mtime" has `deprecated: true` (boolean) — per the
     * documented field ("true uses the file's modification time"), this
     * must resolve to the file's real mtime (always in the past relative
     * to "now", for a fixture file already on disk) and therefore act as
     * already deprecated. Never exercised by any test before this one.
     */
    public function testDeprecatedTrueResolvesToTheFileModificationTime(): void
    {
        $doc = $this->plugin->registry()->get('deprecated-by-mtime');

        $this->assertNotNull($doc->deprecatedAt());
        $this->assertLessThanOrEqual(new DateTime(), $doc->deprecatedAt());
        $this->assertTrue($doc->deprecated());
        $this->assertFalse($doc->indexable());
        $this->assertFalse($doc->searchable());
    }

    /**
     * "draft-parent" is also useful here, but a dedicated fixture makes
     * the intent explicit: "future-section" has a publish date far in the
     * future; its child "child-of-future-section" does NOT — it has its
     * own, already-past date. The child must still be unpublished: date()
     * cascades the same way draft() does (an ancestor's own date gates
     * the whole branch), without the child ever adopting the ancestor's
     * date value itself.
     */
    public function testFutureDatedSectionMakesItsOwnPastDatedChildNotAllowedToo(): void
    {
        $child = $this->plugin->registry()->all()['future-section']->children()['child-of-future-section'];

        $this->assertLessThan(new DateTime(), $child->date());
        $this->assertFalse($child->allowed());

        $this->expectException(ContentNotFoundException::class);
        $this->plugin->registry()->get('future-section/child-of-future-section');
    }

    public function testThePreviousAndTheNextOfADocAreItsNeighboursInTheList(): void
    {
        $registry = $this->plugin->registry();
        $uris = array_values(array_map(fn ($doc) => $doc->uri(), $registry->flatten()));
        $this->assertGreaterThan(2, count($uris));

        $this->assertNull($registry->previous($uris[0]));
        $this->assertSame($uris[0], $registry->previous($uris[1])?->uri());
        $this->assertSame($uris[2], $registry->next($uris[1])?->uri());
        $this->assertNull($registry->next($uris[count($uris) - 1]));
    }
}
