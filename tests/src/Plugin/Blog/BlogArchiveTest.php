<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent\Plugin\Blog;

use DateTimeImmutable;
use Derafu\Content\Exception\ContentNotFoundException;
use Derafu\Content\Plugin\Blog\BlogArchive;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * An archive is a month of the blog, built from a date or from the slug that
 * the URL of the archive has.
 */
#[CoversClass(BlogArchive::class)]
#[UsesClass(ContentNotFoundException::class)]
final class BlogArchiveTest extends TestCase
{
    public function testItIsBuiltFromADate(): void
    {
        $archive = new BlogArchive(new DateTimeImmutable('2026-03-15'));

        $this->assertSame('202603', $archive->id());
        $this->assertSame(2026, $archive->year());
        $this->assertSame(3, $archive->month());
        $this->assertSame('03/2026', $archive->name());
        $this->assertSame('202603-03-2026', $archive->slug());
        $this->assertSame('03/2026', (string) $archive);
    }

    public function testItIsBuiltFromTheSlugOfItsUrl(): void
    {
        $archive = new BlogArchive('202611-11-2026');

        $this->assertSame(2026, $archive->year());
        $this->assertSame(11, $archive->month());
        $this->assertSame('202611', $archive->id());
    }

    public function testTheIdThatIsGivenIsKept(): void
    {
        $this->assertSame('custom', (new BlogArchive('202601', 0, 'custom'))->id());
    }

    public function testTheCountGrows(): void
    {
        $archive = new BlogArchive(new DateTimeImmutable('2026-03-15'), 4);
        $archive->increment();

        $this->assertSame(5, $archive->count());
    }

    public function testItIsExportedAsAnArrayAndAsJson(): void
    {
        $archive = new BlogArchive(new DateTimeImmutable('2026-03-15'), 2);
        $expected = [
            'id' => '202603',
            'year' => 2026,
            'month' => 3,
            'name' => '03/2026',
            'slug' => '202603-03-2026',
            'count' => 2,
        ];

        $this->assertSame($expected, $archive->toArray());
        $this->assertSame(json_encode($expected), json_encode($archive));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function provideArchivesThatAreNotValid(): array
    {
        return [
            'text' => ['archive'],
            'a year without month' => ['2026'],
            'month zero' => ['202600'],
            'month thirteen' => ['202613-x'],
        ];
    }

    #[DataProvider('provideArchivesThatAreNotValid')]
    public function testASlugWithoutAMonthIsNotFound(string $slug): void
    {
        $this->expectException(ContentNotFoundException::class);
        $this->expectExceptionMessage(sprintf('Invalid archive "%s": expected it to start with a "YYYYMM" prefix.', $slug));

        new BlogArchive($slug);
    }
}
