<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent\Abstract;

use Derafu\Content\Abstract\AbstractContentItem;
use Derafu\Content\ContentBag;
use Derafu\Content\ContentSplFileInfo;
use Derafu\Content\Plugin\Docs\DocsDoc;
use Derafu\TestsContent\Support\ContentFixtures;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * The URI, level, route and ancestors of an item are memoized the
 * first time they are read, and all of them depend on its chain of parents.
 * The tree is built bottom up (children first, then they are attached to
 * their parent), so a parent set after one of those values was read would
 * leave it stale, for the item and for everything below it.
 *
 * Setting the parent at that point fails instead of leaving a wrong value.
 */
#[CoversClass(AbstractContentItem::class)]
#[UsesClass(ContentBag::class)]
#[UsesClass(ContentSplFileInfo::class)]
#[UsesClass(DocsDoc::class)]
final class AbstractContentItemParentTest extends TestCase
{
    private function item(string $name): DocsDoc
    {
        return new DocsDoc(ContentFixtures::contentPath() . '/docs/' . $name . '.md');
    }

    public function testTheParentCanBeSetBeforeAnythingIsRead(): void
    {
        $parent = $this->item('guia');
        $child = $this->item('seccion-visible');

        $parent->addChild($child);

        $this->assertSame($parent, $child->parent());
        $this->assertSame([$parent], $child->ancestors());
        $this->assertSame('guia/seccion-visible', $child->uri());
        $this->assertSame(2, $child->level());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function parentDerivedValuesProvider(): array
    {
        return [
            'uri' => ['uri'],
            'route' => ['route'],
            'level' => ['level'],
            'ancestors' => ['ancestors'],
        ];
    }

    #[DataProvider('parentDerivedValuesProvider')]
    public function testSetParentFailsOnceAParentDerivedValueWasRead(string $method): void
    {
        $child = $this->item('seccion-visible');
        $child->$method();

        $this->expectException(LogicException::class);

        $child->setParent($this->item('guia'));
    }

    public function testTheParentIsNotChangedWhenSettingItFails(): void
    {
        $child = $this->item('seccion-visible');
        $child->uri();

        try {
            $child->setParent($this->item('guia'));
            $this->fail('Expected a LogicException.');
        } catch (LogicException) {
            $this->assertNull($child->parent());
        }
    }

    public function testAddChildFailsWhenTheChildAlreadyReadItsUri(): void
    {
        $child = $this->item('seccion-visible');
        $child->uri();

        $this->expectException(LogicException::class);

        $this->item('guia')->addChild($child);
    }

    /**
     * The URI of a child is built from the chain of parents above it, but it
     * is memoized in the child, not in its parent: the parent has nothing
     * memoized and still must not get a parent of its own.
     */
    public function testSetParentFailsWhenADescendantAlreadyReadItsUri(): void
    {
        $grandparent = $this->item('api');
        $parent = $this->item('guia');
        $child = $this->item('seccion-visible');

        $parent->addChild($child);
        $child->uri();

        $this->expectException(LogicException::class);

        $grandparent->addChild($parent);
    }

    public function testSettingTheSameParentAgainIsHarmlessAfterReadingValues(): void
    {
        $parent = $this->item('guia');
        $child = $this->item('seccion-visible');
        $parent->addChild($child);
        $child->uri();

        $child->setParent($parent);

        $this->assertSame($parent, $child->parent());
        $this->assertSame('guia/seccion-visible', $child->uri());
    }
}
