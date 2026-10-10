<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent\Lint;

use Derafu\Content\Lint\ContentLink;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * A link of a page: `from` and `href` are what was found, as it was found;
 * `target`, `query` and `fragment` are resolved from `href` (as a browser
 * does), so a `ContentLink` can not disagree with itself.
 */
#[CoversClass(ContentLink::class)]
final class ContentLinkTest extends TestCase
{
    #[Test]
    public function fromTextAndHrefAreKeptAsTheyAreGiven(): void
    {
        $link = new ContentLink('/docs/a', 'Read more', './b');

        $this->assertSame('/docs/a', $link->from);
        $this->assertSame('Read more', $link->text);
        $this->assertSame('./b', $link->href);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function provideRelativeLinks(): array
    {
        return [
            'a sibling of a page' => ['/docs/a/b', 'c', '/docs/a/c'],
            'a sibling, with ./' => ['/docs/a/b', './c', '/docs/a/c'],
            'a page of the folder above' => ['/docs/a/b', '../c', '/docs/c'],
            'from a path that ends with a slash it is its folder' => ['/docs/a/', 'c', '/docs/a/c'],
            'from the root' => ['/docs', 'x', '/x'],
            'above the root is the root' => ['/docs', '../../x', '/x'],
            'an absolute path' => ['/docs/a/b', '/blog/x', '/blog/x'],
            'nothing after the page itself' => ['/docs/a', '', '/docs/a'],
        ];
    }

    #[Test]
    #[DataProvider('provideRelativeLinks')]
    public function theTargetGoesWhereABrowserTakesTheHref(string $from, string $href, string $expected): void
    {
        $this->assertSame($expected, (new ContentLink($from, '', $href))->target);
    }

    #[Test]
    public function theQueryIsApartFromTheTarget(): void
    {
        $link = new ContentLink('/docs/a', '', '/docs/b?full=1&x=2');

        $this->assertSame('/docs/b', $link->target);
        $this->assertSame('full=1&x=2', $link->query);
        $this->assertNull($link->fragment);
    }

    #[Test]
    public function theFragmentIsApartAndDecoded(): void
    {
        $link = new ContentLink('/docs/a', '', '/docs/b#a%20b');

        $this->assertSame('/docs/b', $link->target);
        $this->assertNull($link->query);
        $this->assertSame('a b', $link->fragment);
    }

    #[Test]
    public function theQueryAndTheFragmentAreBothApartFromTheTarget(): void
    {
        $link = new ContentLink('/docs/a', '', '/docs/b?full=1#part');

        $this->assertSame('/docs/b', $link->target);
        $this->assertSame('full=1', $link->query);
        $this->assertSame('part', $link->fragment);
    }

    #[Test]
    public function aFragmentOfThePageItselfGoesToTheSamePage(): void
    {
        $link = new ContentLink('/docs/a', '', '#gone');

        $this->assertSame('/docs/a', $link->target);
        $this->assertSame('gone', $link->fragment);
    }

    #[Test]
    public function theQueryOfThePageThatHasTheLinkDoesNotCountForTheRelativePath(): void
    {
        // The page itself is never asked with a query; a link relative to it is not
        // either.
        $link = new ContentLink('/docs/a?tab=2', '', 'b');

        $this->assertSame('/docs/b', $link->target);
    }

    #[Test]
    public function theIdentityIsWhereItIsAndWhatTheHrefSays(): void
    {
        $this->assertSame(
            '/docs/a => ./b#part',
            (new ContentLink('/docs/a', 'text', './b#part'))->identity()
        );
    }

    #[Test]
    public function resolvedIsTheHrefWhenThereWasNothingToResolve(): void
    {
        $link = new ContentLink('/docs/a', '', '/docs/gone');

        $this->assertSame('/docs/gone', $link->resolved());
        $this->assertSame($link->href, $link->resolved());
    }

    #[Test]
    public function resolvedPutsTheQueryAndTheFragmentBackOnTheTarget(): void
    {
        $link = new ContentLink('/docs/a/b', '', '../c?x=1#part');

        $this->assertSame('/docs/c?x=1#part', $link->resolved());
        $this->assertNotSame($link->href, $link->resolved());
    }

    #[Test]
    public function toArrayHasEveryFactApart(): void
    {
        $link = new ContentLink('/docs/a/b', 'Read more', '../c?x=1#part');

        $this->assertSame([
            'from' => '/docs/a/b',
            'text' => 'Read more',
            'href' => '../c?x=1#part',
            'target' => '/docs/c',
            'query' => 'x=1',
            'fragment' => 'part',
            'resolved' => '/docs/c?x=1#part',
        ], $link->toArray());
    }

    #[Test]
    public function theTextIsShownBetweenParenthesesWhenThereIsOne(): void
    {
        $link = new ContentLink('/docs/a', 'Read more', '/docs/gone');

        $this->assertSame('/docs/a => /docs/gone (text: "Read more")', (string) $link);
    }

    #[Test]
    public function aLinkWithoutTextDoesNotShowEmptyParentheses(): void
    {
        // An image inside the `<a>`, for one: the anchor has no text of its own.
        $link = new ContentLink('/docs/a', '', '/docs/gone');

        $this->assertSame('/docs/a => /docs/gone', (string) $link);
    }

    #[Test]
    public function whereItReallyGoesIsShownOnlyWhenItDiffersFromTheHref(): void
    {
        $same = new ContentLink('/docs/a', '', '/docs/gone');
        $different = new ContentLink('/docs/a', '', './gone');

        $this->assertSame('/docs/a => /docs/gone', (string) $same);
        $this->assertSame('/docs/a => ./gone [/docs/gone]', (string) $different);
    }
}
