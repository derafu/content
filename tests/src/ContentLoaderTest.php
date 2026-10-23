<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent;

use Derafu\Content\Abstract\AbstractContentItem;
use Derafu\Content\ContentLoader;
use Derafu\Content\ContentSplFileInfo;
use Derafu\Content\Plugin\Docs\DocsDoc;
use Derafu\Support\File;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * The loader reads the files of a content directory that the patterns select,
 * puts them in a tree by their names and builds the items of the tree, each
 * one with its children, in the order that the contents ask for.
 */
#[CoversClass(ContentLoader::class)]
#[UsesClass(AbstractContentItem::class)]
#[UsesClass(ContentSplFileInfo::class)]
#[UsesClass(DocsDoc::class)]
final class ContentLoaderTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/content-loader-test-' . uniqid();
        mkdir($this->path . '/docs/guide', 0777, true);
        mkdir($this->path . '/docs/loose', 0777, true);

        $this->write('docs/zeta.md', 'Zeta', 1);
        $this->write('docs/alpha.md', 'Alpha', 2);
        $this->write('docs/guide.md', 'Guide', 3);
        $this->write('docs/guide/second.md', 'Second', 2);
        $this->write('docs/guide/first.md', 'First', 1);
        $this->write('docs/draft-notes.md', 'Draft notes', 4);
        $this->write('docs/loose/orphan.md', 'Orphan', 1);
        file_put_contents($this->path . '/docs/image.png', 'not a content');
    }

    protected function tearDown(): void
    {
        File::rmdir($this->path);
    }

    private function write(string $file, string $title, int $position): void
    {
        file_put_contents(
            $this->path . '/' . $file,
            "---\ntitle: \"{$title}\"\nsidebar_position: {$position}\n---\nThe text of {$title}.\n"
        );
    }

    /**
     * @param array<int|string, mixed> $tree
     * @return list<int|string>
     */
    private function names(array $tree): array
    {
        return array_keys($tree);
    }

    public function testTheTreeHasTheFilesThatThePatternsSelectByTheirNames(): void
    {
        $tree = (new ContentLoader($this->path))->scan('docs', ['*.md'], ['draft-*.md']);

        $this->assertSame(['alpha', 'guide', 'loose', 'zeta'], $this->names($tree));
        [$alpha, $guide, $loose] = [$tree['alpha'], $tree['guide'], $tree['loose']];
        $this->assertIsArray($alpha);
        $this->assertIsArray($guide);
        $this->assertIsArray($loose);

        $this->assertInstanceOf(ContentSplFileInfo::class, $alpha[0]);
        $this->assertInstanceOf(ContentSplFileInfo::class, $guide[0]);
        $this->assertSame(['first', 'second'], $this->names(array_filter($guide, 'is_array')));
        $this->assertArrayNotHasKey(0, $loose);
    }

    public function testTheIncludePatternsCanBeBraceLists(): void
    {
        $tree = (new ContentLoader($this->path))->scan('docs', ['{alpha,zeta}.md'], []);

        $this->assertSame(['alpha', 'zeta'], $this->names($tree));
    }

    public function testADirectoryThatDoesNotExistHasNoFiles(): void
    {
        $this->assertSame([], (new ContentLoader($this->path))->scan('missing', ['*.md'], []));
    }

    public function testTheItemsAreBuiltWithTheirChildrenInTheOrderOfTheirPositions(): void
    {
        $loader = new ContentLoader($this->path);
        $items = $loader->load(DocsDoc::class, $loader->scan('docs', ['*.md'], ['draft-*.md']));

        $this->assertSame(['zeta', 'alpha', 'guide'], array_keys($items));
        $this->assertSame(['first', 'second'], array_keys($items['guide']->children()));
        $this->assertSame('Guide', $items['guide']->title());
        $this->assertSame('First', $items['guide']->children()['first']->title());
    }

    public function testADirectoryWithoutItsOwnFileIsNotAnItem(): void
    {
        $loader = new ContentLoader($this->path);
        $items = $loader->load(DocsDoc::class, $loader->scan('docs', ['*.md'], []));

        $this->assertArrayNotHasKey('loose', $items);
        $this->assertArrayHasKey('draft-notes', $items);
    }
}
