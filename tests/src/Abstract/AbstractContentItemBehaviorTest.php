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
use Derafu\Content\ContentAuthor;
use Derafu\Content\ContentHtmlTag;
use Derafu\Content\ContentHtmlTags;
use Derafu\Content\ContentSplFileInfo;
use Derafu\Content\ContentTag;
use Derafu\Content\Plugin\Docs\DocsDoc;
use Derafu\File\Directory;
use Derafu\TestsContent\Support\RouterFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * What a content says about itself, from its frontmatter and its text: the
 * preview that the listings show, the time to read it, if it can be seen and
 * indexed, the tags of the HTML head and the data that it is exported with.
 */
#[CoversClass(AbstractContentItem::class)]
#[UsesClass(ContentAuthor::class)]
#[UsesClass(ContentHtmlTag::class)]
#[UsesClass(ContentHtmlTags::class)]
#[UsesClass(ContentSplFileInfo::class)]
#[UsesClass(ContentTag::class)]
#[UsesClass(DocsDoc::class)]
final class AbstractContentItemBehaviorTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/content-item-behavior-' . uniqid();
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        Directory::remove($this->directory);
    }

    private function item(string $text, string $frontmatter = '', string $file = 'item.md'): DocsDoc
    {
        file_put_contents(
            $this->directory . '/' . $file,
            "---\ntitle: \"Item\"\n" . $frontmatter . "---\n" . $text
        );

        return new DocsDoc($this->directory . '/' . $file);
    }

    private function longText(): string
    {
        return str_repeat('word ', 30) . 'end';
    }

    public function testThePreviewIsTheFirstParagraphWithoutTheMarkersOfTitlesAndLists(): void
    {
        $item = $this->item("# A title\n- first item\n- second item\n\nThe second paragraph is not in the preview.\n");

        $this->assertSame("A title\nfirst item\nsecond item", $item->preview());
    }

    public function testALongPreviewIsCutAfterTheWordThatCrossesTheLimit(): void
    {
        $item = $this->item($this->longText() . "\n");

        $preview = $item->preview(20);

        $this->assertSame('word word word word word...', $preview);
    }

    public function testAPreviewWithoutSpacesAfterTheLimitIsCutAtTheLimit(): void
    {
        $item = $this->item(str_repeat('x', 50) . "\n");

        $this->assertSame(str_repeat('x', 10) . '...', $item->preview(10));
    }

    public function testAPreviewDoesNotEndWithAnOpenMarkdownCharacter(): void
    {
        $item = $this->item("Read the **guide of the project** and **more things to know about it** today\n");

        $this->assertSame('Read the **guide of the project...', $item->preview(25));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function provideTimes(): array
    {
        return [
            'a short text is one minute' => ['', '1 m'],
            'the time of the frontmatter' => ["time: 45\n", '45 m'],
            'whole hours' => ["time: 120\n", '2 h'],
            'hours and minutes' => ["time: 135\n", '2 h 15 m'],
        ];
    }

    #[DataProvider('provideTimes')]
    public function testTheTimeToReadIsFormatted(string $frontmatter, string $expected): void
    {
        $this->assertSame($expected, $this->item("A few words.\n", $frontmatter)->time_formatted());
    }

    public function testWithoutTimeItIsCalculatedFromTheWords(): void
    {
        $item = $this->item(str_repeat('word ', 450) . "\n");

        $this->assertSame(3, $item->time());
    }

    public function testADraftOrAFutureContentIsNotAllowed(): void
    {
        $this->assertTrue($this->item("Text.\n", "date: 2020-01-01\n")->allowed());
        $this->assertFalse($this->item("Text.\n", "draft: true\ndate: 2020-01-01\n", 'draft.md')->allowed());
        $this->assertFalse($this->item("Text.\n", "date: 2999-01-01\n", 'future.md')->allowed());
    }

    public function testAShortContentIsNotIndexableUnlessItSaysSo(): void
    {
        $this->assertFalse($this->item("Short.\n")->indexable());
        $this->assertTrue($this->item($this->longText() . "\n", '', 'long.md')->indexable());
        $this->assertTrue($this->item("Short.\n", "indexable: true\n", 'forced.md')->indexable());
        $this->assertFalse($this->item($this->longText() . "\n", "indexable: false\n", 'hidden.md')->indexable());
    }

    public function testADraftOrAnUnlistedContentIsNotIndexableNorSearchable(): void
    {
        foreach (['draft: true', 'unlisted: true'] as $key => $line) {
            $item = $this->item($this->longText() . "\n", $line . "\n", 'case' . $key . '.md');

            $this->assertFalse($item->indexable(), $line);
            $this->assertFalse($item->searchable(), $line);
        }

        $this->assertTrue($this->item($this->longText() . "\n", '', 'normal.md')->searchable());
        $this->assertTrue($this->item("Short.\n", "searchable: true\ndraft: true\n", 'forced.md')->searchable());
    }

    public function testTheHeadTagsHaveTheDescriptionTheImageTheKeywordsAndTheAuthors(): void
    {
        $item = $this->item(
            "Text.\n",
            "description: \"What it is about\"\nimage: /img/cover.png\nkeywords: [php, content]\ntags: [Open Source]\nauthors: [Ana Pérez]\n"
        );

        $html = $item->htmlTags()->headTagsToHtml();

        $this->assertStringContainsString('<meta name="description" content="What it is about">', $html);
        $this->assertStringContainsString('<meta property="og:description" content="What it is about">', $html);
        $this->assertStringContainsString('<meta property="og:image" content="/img/cover.png">', $html);
        $this->assertStringContainsString('<meta name="keywords" content="php, content, Open Source">', $html);
        $this->assertStringContainsString('<meta name="author" content="Ana Pérez">', $html);
    }

    public function testWithoutFrontmatterTheDescriptionIsTheTextAndTheAuthorIsAnonymous(): void
    {
        $this->assertSame(
            '<meta name="description" content="Text."><meta property="og:description" content="Text.">'
            . '<meta name="author" content="Anonymous">',
            $this->item("Text.\n")->htmlTags()->headTagsToHtml()
        );
    }

    /**
     * @return array<string, array{string, string|null}>
     */
    public static function provideImages(): array
    {
        return [
            'a path of the website' => ['/img/a.png', 'https://example.com/img/a.png'],
            'a relative path' => ['img/a.png', 'img/a.png'],
            'a URL without protocol' => ['//cdn.example.com/a.png', '//cdn.example.com/a.png'],
            'an absolute URL' => ['https://cdn.example.com/a.png', 'https://cdn.example.com/a.png'],
        ];
    }

    #[DataProvider('provideImages')]
    public function testOnlyThePathsOfTheWebsiteAreMadeAbsolute(string $image, ?string $expected): void
    {
        $item = $this->item("Text.\n", "image: \"" . $image . "\"\n");

        $this->assertSame($expected, $item->image(['asset_base_url' => 'https://example.com']));
        $this->assertSame($image, $item->image());
    }

    public function testItIsExportedAsAnArrayAndAsJson(): void
    {
        $item = $this->item(
            $this->longText() . "\n",
            "date: 2026-03-01\nlast_update: 2026-03-02\ntags: [php]\nauthors: [Ana Pérez]\n",
            '2026-03-01-the-item.md'
        );

        $data = $item->toArray();

        $this->assertSame('docs', $data['type']);
        $this->assertSame('doc', $data['category']);
        $this->assertSame('Item', $data['title']);
        $this->assertSame('2026-03-01', $data['date']);
        $this->assertSame('2026-03-02', $data['last_update']);
        $this->assertFalse($data['draft']);
        $this->assertNull($data['deprecated']);
        $this->assertSame($item->checksum(), $data['checksum']);
        $this->assertSame($item->data(), $data['data']);
        $this->assertArrayNotHasKey('_links', $data);
        $this->assertSame($data, $item->jsonSerialize());
        $this->assertIsString(json_encode($item));
    }

    public function testItIsTheTextWhenItIsUsedAsAString(): void
    {
        $item = $this->item("The text of the item.\n");

        $this->assertSame('The text of the item.', (string) $item);
    }

    public function testTheNameOfTheDownloadIsTheSlugOfTheUri(): void
    {
        $this->assertSame('item', $this->item("Text.\n")->downloadFilename());
    }

    public function testItKnowsTheKindOfFileItIs(): void
    {
        $this->assertTrue($this->item("Text.\n")->isMarkdown());
        $this->assertTrue($this->item("Text.\n", '', 'long.markdown')->isMarkdown());
    }

    public function testItGivesItsPathAndItsChecksum(): void
    {
        $item = $this->item("Text.\n");

        $this->assertSame(realpath($this->directory . '/item.md'), $item->path());
        $this->assertSame(hash_file('sha256', $this->directory . '/item.md'), $item->checksum());
    }

    public function testThePreviewCanMakeItsLinksAndImagesAbsolute(): void
    {
        $item = $this->item("Read the [guide](/docs/guide) and see ![logo](/img/logo.png).\n");

        $this->assertSame(
            'Read the [guide](https://example.com/docs/guide) and see ![logo](https://cdn.example.com/img/logo.png).',
            $item->preview(300, ['link_base_url' => 'https://example.com', 'asset_base_url' => 'https://cdn.example.com'])
        );
    }

    public function testItIsExportedWithItsLinksWhenItIsGivenARouter(): void
    {
        $data = $this->item("Text.\n")->toArray(RouterFixture::create());

        $this->assertSame(
            ['self' => ['href' => '/docs/item'], 'collection' => ['href' => '/docs']],
            $data['_links']
        );
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function provideLocalEnvironments(): array
    {
        return [
            'APP_ENV local' => ['APP_ENV', 'local'],
            'localhost' => ['SERVER_NAME', 'localhost'],
        ];
    }

    #[DataProvider('provideLocalEnvironments')]
    public function testInALocalEnvironmentADraftOrAFutureContentCanBeSeen(string $variable, string $value): void
    {
        $draft = $this->item("Text.\n", "draft: true\n", 'draft.md');
        $future = $this->item("Text.\n", "date: 2999-01-01\n", 'future.md');

        $previous = $_SERVER[$variable] ?? null;
        $_SERVER[$variable] = $value;
        try {
            $this->assertTrue($draft->allowed());
            $this->assertTrue($future->allowed());
        } finally {
            if ($previous === null) {
                unset($_SERVER[$variable]);
            } else {
                $_SERVER[$variable] = $previous;
            }
        }

        $this->assertFalse($draft->allowed());
        $this->assertFalse($future->allowed());
    }
}
