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

use DateTimeInterface;
use Derafu\Content\Abstract\AbstractContentItem;
use Derafu\Content\ContentAuthor;
use Derafu\Content\ContentSplFileInfo;
use Derafu\Content\ContentTag;
use Derafu\Content\Contract\ContentAuthorInterface;
use Derafu\Content\Contract\ContentTagInterface;
use Derafu\Content\Plugin\Docs\DocsDoc;
use Derafu\Translation\Contract\TranslatableInterface;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * The values of the frontmatter are read with the type that each one must have:
 * a value of another type is an error that names the key and the content, and a
 * key that is not there (or is `null`) takes its default.
 */
#[CoversClass(AbstractContentItem::class)]
#[UsesClass(ContentSplFileInfo::class)]
#[UsesClass(DocsDoc::class)]
#[UsesClass(ContentAuthor::class)]
#[UsesClass(ContentTag::class)]
final class AbstractContentItemFrontmatterTest extends TestCase
{
    private function item(string $name): DocsDoc
    {
        return new DocsDoc(dirname(__DIR__, 2) . '/fixtures/frontmatter/' . $name . '.md');
    }

    public function testSidebarPropertiesKeepLiteralKeysAndNestedArrayValues(): void
    {
        $this->assertSame([
            'icon' => 'book',
            'theme.icon' => 'guide',
            'badges' => [7 => 'featured', 2 => null],
        ], $this->item('sidebar-string-keys')->sidebar_custom_props());
    }

    public function testEveryValueOfTheFrontmatterIsRead(): void
    {
        $item = $this->item('valid-all');

        $this->assertSame('All good', $item->title());
        $this->assertSame('todo-bien', $item->slug());
        $this->assertSame('A description.', $item->description());
        $this->assertSame(['one', 'two'], $item->keywords());
        $this->assertSame('/img/a.png', $item->image());
        $this->assertSame('https://example.com/v.mp4', $item->video());
        $this->assertSame(['guide', 'demo'], array_map(fn (ContentTagInterface $tag): string => $tag->name(), $item->tags()));
        $this->assertSame(['Ana'], array_map(fn (ContentAuthorInterface $author): string => $author->name(), $item->authors()));
        $this->assertSame(7, $item->time());
        $this->assertFalse($item->draft());
        $this->assertFalse($item->unlisted());
        $this->assertSame('2026-01-15', $item->date()->format('Y-m-d'));
        $this->assertSame('2026-02-20', $item->last_update()->format('Y-m-d'));
        $this->assertNull($item->deprecatedAt());
        $this->assertFalse($item->deprecated());
        $this->assertFalse($item->indexable());
        $this->assertTrue($item->searchable());
        $this->assertSame('Next', $item->pagination_label());
        $this->assertSame('Sidebar', $item->sidebar_label());
        $this->assertSame(5, $item->sidebar_position());
        $this->assertSame('highlighted', $item->sidebar_class_name());
        $this->assertSame(['icon' => 'book'], $item->sidebar_custom_props());
        $this->assertTrue($item->hide_title());
        $this->assertTrue($item->hide_table_of_contents());
        $this->assertSame(3, $item->toc_min_heading_level());
        $this->assertSame(4, $item->toc_max_heading_level());
        $this->assertTrue($item->has_twig());
    }

    public function testWhatIsNotInTheFrontmatterTakesItsDefault(): void
    {
        $item = $this->item('minimal');

        $this->assertSame('minimal', $item->title());
        $this->assertSame('minimal', $item->slug());
        $this->assertSame([], $item->keywords());
        $this->assertNull($item->image());
        $this->assertNull($item->video());
        $this->assertSame([], $item->tags());
        $this->assertSame(['Anonymous'], array_map(fn (ContentAuthorInterface $author): string => $author->name(), $item->authors()));
        $this->assertGreaterThanOrEqual(1, $item->time());
        $this->assertFalse($item->draft());
        $this->assertFalse($item->unlisted());
        $this->assertNull($item->deprecatedAt());
        $this->assertSame('minimal', $item->pagination_label());
        $this->assertSame('minimal', $item->sidebar_label());
        $this->assertNull($item->sidebar_class_name());
        $this->assertSame([], $item->sidebar_custom_props());
        $this->assertFalse($item->hide_title());
        $this->assertFalse($item->hide_table_of_contents());
        $this->assertSame(2, $item->toc_min_heading_level());
        $this->assertSame(6, $item->toc_max_heading_level());
        $this->assertFalse($item->has_twig());
    }

    public function testThePreviousNamesOfSomeKeysAreStillRead(): void
    {
        $item = $this->item('legacy-keys');

        $this->assertSame('Old summary.', $item->description());
        $this->assertSame(9, $item->sidebar_position());
        $this->assertSame('2026-04-05', $this->item('date-created')->date()->format('Y-m-d'));
        $this->assertSame(['Eva'], array_map(fn (ContentAuthorInterface $author): string => $author->name(), $this->item('authors-legacy')->authors()));
    }

    public function testFalseMeansThatThereIsNoImageNoVideoAndNoSidebarClass(): void
    {
        $item = $this->item('image-false');

        $this->assertNull($item->image());
        $this->assertNull($item->video());
        $this->assertNull($item->sidebar_class_name());
    }

    public function testAYoutubeVideoIsAnEmbed(): void
    {
        $this->assertSame('https://www.youtube.com/embed/abc123', $this->item('youtube')->video());
    }

    public function testTheAuthorsCanBeANameOrAListOfNamesObjectsAndPairs(): void
    {
        $authors = fn (string $name): array => array_map(
            fn (ContentAuthorInterface $author): array => [$author->name(), $author->slug()],
            $this->item($name)->authors()
        );

        $this->assertSame([['Ana', 'ana']], $authors('authors-string'));
        $this->assertSame(
            [['Ana', 'ana'], ['Beto', 'beto'], ['Carla', 'carla'], ['Dani', 'dani']],
            $authors('authors-forms')
        );
    }

    public function testDeprecatedCanBeTrueADateOrATextOfADate(): void
    {
        $true = $this->item('deprecated-true');
        $date = $this->item('deprecated-date');
        $text = $this->item('deprecated-text');

        $this->assertInstanceOf(DateTimeInterface::class, $true->deprecatedAt());
        $this->assertTrue($true->deprecated());
        $this->assertSame('2020-03-04', $date->deprecatedAt()?->format('Y-m-d'));
        $this->assertTrue($date->deprecated());
        $this->assertSame('2020-03-04', $text->deprecatedAt()?->format('Y-m-d'));
    }

    public function testTheDateIsReadFromATextAndFromTheNameOfTheFile(): void
    {
        $this->assertSame('2026-03-10', $this->item('date-text')->date()->format('Y-m-d'));
        $this->assertSame('2025-06-07', $this->item('2025-06-07-from-the-name')->date()->format('Y-m-d'));
        $this->assertInstanceOf(DateTimeInterface::class, $this->item('minimal')->date());
        $this->assertInstanceOf(DateTimeInterface::class, $this->item('minimal')->last_update());
    }

    public function testTheLevelsOfTheTableOfContentsAreKeptBetween2And6(): void
    {
        $item = $this->item('time-and-levels-out-of-range');

        $this->assertSame(2, $item->toc_min_heading_level());
        $this->assertSame(6, $item->toc_max_heading_level());
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function provideInvalidValues(): array
    {
        $message = fn (string $file, string $key, string $expected, string $given): string
            => sprintf('The key "%s" of docs/%s must be of type %s, %s given.', $key, $file, $expected, $given);

        return [
            'a title that is a number' => ['invalid-title', 'title', $message('invalid-title', 'title', 'string', 'int')],
            'a slug that is a list' => ['invalid-slug', 'slug', $message('invalid-slug', 'slug', 'string', 'array')],
            'keywords that are a text' => ['invalid-keywords', 'keywords', $message('invalid-keywords', 'keywords', 'list<string>', 'string')],
            'a tag that is a number' => ['invalid-tags', 'tags', $message('invalid-tags', 'tags.1', 'string', 'int')],
            'an image that is true' => ['invalid-image', 'image', $message('invalid-image', 'image', 'string|false', 'bool')],
            'a video that is a number' => ['invalid-video', 'video', $message('invalid-video', 'video', 'string|false', 'int')],
            'a draft that is a text' => ['invalid-draft', 'draft', $message('invalid-draft', 'draft', 'bool', 'string')],
            'a time that is a text' => ['invalid-time', 'time', $message('invalid-time', 'time', 'int', 'string')],
            'a date that is empty' => ['invalid-date', 'date', $message('invalid-date', 'date', 'date', 'string')],
            'a date that is not a date' => ['invalid-date-text', 'date', $message('invalid-date-text', 'date', 'date', 'string')],
            'a last update that is false' => ['invalid-last-update', 'last_update', $message('invalid-last-update', 'last_update', 'date', 'bool')],
            'a deprecated that is a list' => ['invalid-deprecated', 'deprecatedAt', $message('invalid-deprecated', 'deprecated', 'bool|date', 'array')],
            'a position that is a text' => ['invalid-sidebar-position', 'sidebar_position', $message('invalid-sidebar-position', 'sidebar_position', 'int', 'string')],
            'a sidebar class that is true' => ['invalid-sidebar-class-name', 'sidebar_class_name', $message('invalid-sidebar-class-name', 'sidebar_class_name', 'string|false', 'bool')],
            'custom props that are a text' => ['invalid-sidebar-custom-props', 'sidebar_custom_props', $message('invalid-sidebar-custom-props', 'sidebar_custom_props', 'map', 'string')],
            'custom props with a numeric key' => ['invalid-sidebar-numeric-key', 'sidebar_custom_props', $message('invalid-sidebar-numeric-key', 'sidebar_custom_props', 'map', 'array')],
            'authors that are a number' => ['invalid-authors-type', 'authors', $message('invalid-authors-type', 'authors', 'string|list', 'int')],
            'an author that is a number' => ['invalid-author-item', 'authors', $message('invalid-author-item', 'authors.0', 'array', 'int')],
            'an author without name' => ['invalid-authors', 'authors', sprintf('The key "authors.0.0" of docs/%s is required.', 'invalid-authors')],
            'a level that is a text' => ['invalid-toc', 'toc_min_heading_level', $message('invalid-toc', 'toc_min_heading_level', 'int', 'string')],
        ];
    }

    #[DataProvider('provideInvalidValues')]
    public function testAValueOfAnotherTypeIsATranslatableErrorThatNamesTheKeyAndTheContent(
        string $file,
        string $method,
        string $message
    ): void {
        $item = $this->item($file);

        $exception = null;
        try {
            $item->$method();
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(InvalidArgumentException::class, $exception);
        $this->assertInstanceOf(TranslatableInterface::class, $exception);
        $this->assertSame($message, $exception->getMessage());
    }

    public function testTheContentIsNamedWithItsAncestors(): void
    {
        $parent = $this->item('minimal');
        $child = $this->item('invalid-title');
        $parent->addChild($child);

        $this->expectExceptionMessage('The key "title" of docs/minimal/invalid-title must be of type string, int given.');

        $child->title();
    }
}
