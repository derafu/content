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
use Derafu\Content\Lint\ContentLinkAudit;
use Derafu\Content\Lint\ContentLinkAuditReport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * The links between the pages of a site: which ones go to a page that is not served
 * or to a fragment that is not in the page, resolved as a browser does, over the
 * HTML that the site gives.
 */
#[CoversClass(ContentLinkAudit::class)]
#[CoversClass(ContentLinkAuditReport::class)]
#[UsesClass(ContentLink::class)]
final class ContentLinkAuditTest extends TestCase
{
    /**
     * @param array<string, string> $pages The HTML of each path that the site serves.
     */
    private function audit(array $pages): ContentLinkAudit
    {
        return new ContentLinkAudit(fn (string $path): ?string => $pages[$path] ?? null);
    }

    private static function page(string $body): string
    {
        return '<!doctype html><html><head><title>t</title></head><body>' . $body . '</body></html>';
    }

    #[Test]
    public function aSiteWhoseLinksAllGoSomewhereHasNothingToReport(): void
    {
        $audit = $this->audit([
            '/docs/a' => self::page('<h2 id="intro">Intro</h2><a href="/docs/b#part">b</a><a href="#intro">here</a>'),
            '/docs/b' => self::page('<h2><a id="part" href="#part">¶</a>Part</h2>'),
        ]);

        $report = $audit->audit(['/docs/a', '/docs/b']);

        $this->assertSame([], $report->missingPages);
        $this->assertSame([], $report->missingAnchors);
        $this->assertSame([], $report->formatLinks);
        $this->assertSame([], $report->unreachablePages);
        $this->assertFalse($report->nothingFound());
        // The two of `/docs/a` and the permalink of the heading of `/docs/b`.
        $this->assertSame(3, $report->linksChecked);
    }

    #[Test]
    public function aLinkToAPageThatIsNotServedIsReported(): void
    {
        $report = $this->audit(['/docs/a' => self::page('<a href="/docs/gone">gone</a>')])->audit(['/docs/a']);

        $this->assertSame(['/docs/a => /docs/gone (text: "gone")'], $report->describe($report->missingPages));
    }

    #[Test]
    public function theTextOfTheLinkIsTrimmedAsTheRenderedPageHasIt(): void
    {
        $report = $this->audit(['/docs/a' => self::page("<a href=\"/docs/gone\">\n  gone  \n</a>")])->audit(['/docs/a']);

        $this->assertSame('gone', $report->missingPages[0]->text);
    }

    #[Test]
    public function aLinkWithNoTextOfItsOwnHasAnEmptyOne(): void
    {
        // An image inside the `<a>`, for one.
        $report = $this->audit(['/docs/a' => self::page('<a href="/docs/gone"><img src="x.png"></a>')])->audit(['/docs/a']);

        $this->assertSame('', $report->missingPages[0]->text);
        $this->assertSame(['/docs/a => /docs/gone'], $report->describe($report->missingPages));
    }

    #[Test]
    public function aLinkToAFragmentThatIsNotInThePageIsReportedAndOneThatIsIsNot(): void
    {
        $audit = $this->audit([
            '/docs/a' => self::page('<a href="/docs/b#there">ok</a><a href="/docs/b#not-there">bad</a><a href="#nowhere">bad</a>'),
            '/docs/b' => self::page('<h2 id="there">There</h2>'),
        ]);

        $report = $audit->audit(['/docs/a']);

        $this->assertSame(
            ['/docs/a => /docs/b#not-there (text: "bad")', '/docs/a => #nowhere [/docs/a#nowhere] (text: "bad")'],
            $report->describe($report->missingAnchors)
        );
        $this->assertSame([], $report->missingPages);
    }

    #[Test]
    public function theIdsAreTheOnesOfTheRenderedPage(): void
    {
        // The prefix of the ids, or the number that a repeated heading gets, is what
        // the page has: not what the title would say.
        $audit = $this->audit([
            '/docs/a' => self::page('<a href="/docs/b#content-llm">prefixed</a><a href="/docs/b#llm">plain</a><a href="/docs/b#llm-1">second</a><a href="/docs/b#old">name</a>'),
            '/docs/b' => self::page('<h2 id="content-llm">LLM</h2><h2 id="llm-1">LLM</h2><a name="old"></a>'),
        ]);

        $report = $audit->audit(['/docs/a']);

        $this->assertSame(['/docs/a => /docs/b#llm (text: "plain")'], $report->describe($report->missingAnchors));
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
        ];
    }

    #[Test]
    #[DataProvider('provideRelativeLinks')]
    public function aRelativeLinkGoesWhereABrowserTakesIt(string $from, string $href, string $expected): void
    {
        $report = $this->audit([$from => self::page('<a href="' . $href . '">x</a>')])->audit([$from]);

        $this->assertCount(1, $report->missingPages);
        $this->assertSame($expected, $report->missingPages[0]->target);
    }

    #[Test]
    public function aLinkWithAQueryOrWithAnEncodedFragmentIsReadAsABrowserReadsIt(): void
    {
        $audit = $this->audit([
            '/docs/a' => self::page('<a href="/docs/b?full=1#a%20b">ok</a>'),
            '/docs/b' => self::page('<h2 id="a b">A B</h2>'),
        ]);

        $report = $audit->audit(['/docs/a']);

        $this->assertSame([], $report->missingPages);
        $this->assertSame([], $report->missingAnchors);
    }

    #[Test]
    public function theLinksThatAreNotToAPageOfTheSiteAreNotChecked(): void
    {
        $audit = $this->audit(['/docs/a' => self::page(
            '<a href="https://example.com/x#y">other</a><a href="mailto:a@b.c">mail</a><a href="//cdn.example.com/x">cdn</a>'
            . '<a href="javascript:void(0)">js</a><a href="">empty</a><a>no href</a>'
        )]);

        $report = $audit->audit(['/docs/a']);

        $this->assertTrue($report->nothingFound());
        $this->assertSame([], $report->missingPages);
    }

    #[Test]
    public function aLinkToTheTopOfThePageIsAlwaysValid(): void
    {
        $report = $this->audit(['/docs/a' => self::page('<a href="#">up</a><a href="#top">top</a>')])->audit(['/docs/a']);

        $this->assertSame([], $report->missingAnchors);
        $this->assertFalse($report->nothingFound());
    }

    #[Test]
    public function aLinkToAFormatOfAPageIsReportedByItselfAndNotChecked(): void
    {
        // The site gives the file, not the page: for a reader it is not the link that
        // was meant, even when the file exists.
        $audit = $this->audit([
            '/docs/a' => self::page('<a href="b.md#part">md</a><a href="/docs/b.pdf">pdf</a><a href="/docs/b.JSON">json</a><a href="b">page</a><a href="/docs/a.md">own</a><a href="a.pdf">own</a>'),
            '/docs/b' => self::page('<h2 id="part">Part</h2>'),
            '/docs/b.md' => 'x',
        ]);

        $report = $audit->audit(['/docs/a']);

        $this->assertSame(
            [
                '/docs/a => b.md#part [/docs/b.md#part] (text: "md")',
                '/docs/a => /docs/b.pdf (text: "pdf")',
                '/docs/a => /docs/b.JSON (text: "json")',
            ],
            $report->describe($report->formatLinks)
        );
        $this->assertSame([], $report->missingPages);
    }

    #[Test]
    public function theLinksOfAPageToItsOwnFormatsAreItsDownloadsAndAreNotReported(): void
    {
        $report = $this->audit(['/docs/a' => self::page('<a href="/docs/a.md">md</a><a href="a.pdf">pdf</a><a href="/docs/a.json">json</a>')])
            ->audit(['/docs/a']);

        $this->assertSame([], $report->formatLinks);
        $this->assertSame([], $report->missingPages);
        $this->assertSame(3, $report->linksChecked);
    }

    #[Test]
    public function onlyTheLinksInsideTheSelectorAreChecked(): void
    {
        $audit = $this->audit(['/docs/a' => self::page(
            '<nav><a href="/menu/gone">menu</a></nav><main><a href="/docs/gone">content</a></main>'
        )]);

        $report = $audit->audit(['/docs/a'], selector: 'main');

        $this->assertSame(['/docs/a => /docs/gone (text: "content")'], $report->describe($report->missingPages));
        $this->assertSame(1, $report->linksChecked);
    }

    #[Test]
    public function aLinkThatIsAllowedIsNotReportedAndOnlyThatOne(): void
    {
        $audit = $this->audit(['/docs/a' => self::page('<a href="/gone">1</a><a href="/other">2</a>')]);

        $report = $audit->audit(['/docs/a'], allowed: ['/docs/a' => ['/gone']]);

        $this->assertSame(['/docs/a => /other (text: "2")'], $report->describe($report->missingPages));
    }

    #[Test]
    public function aLinkThatIsAllowedInAPageIsReportedInTheOthers(): void
    {
        $audit = $this->audit([
            '/docs/a' => self::page('<a href="/gone">1</a>'),
            '/docs/b' => self::page('<a href="/gone">2</a>'),
        ]);

        $report = $audit->audit(['/docs/a', '/docs/b'], allowed: ['/docs/a' => ['/gone']]);

        $this->assertSame(['/docs/b => /gone (text: "2")'], $report->describe($report->missingPages));
    }

    #[Test]
    public function aLinkThatRepeatsInAPageIsReportedOnce(): void
    {
        $report = $this->audit(['/docs/a' => self::page('<a href="/gone">1</a><a href="/gone">2</a>')])->audit(['/docs/a']);

        $this->assertCount(1, $report->missingPages);
        $this->assertSame(1, $report->linksChecked);
    }

    #[Test]
    public function aPageThatWasAskedForAndIsNotServedIsReported(): void
    {
        $report = $this->audit(['/docs/a' => self::page('<a href="/docs/a">me</a>')])->audit(['/docs/a', '/docs/missing']);

        $this->assertSame(['/docs/missing'], $report->unreachablePages);
        $this->assertSame(['/docs/a', '/docs/missing'], $report->pages);
    }

    #[Test]
    public function theAuditReadsEachPageOnce(): void
    {
        $calls = [];
        $audit = new ContentLinkAudit(function (string $path) use (&$calls): string {
            $calls[] = $path;

            return self::page('<a href="/docs/b#x">b</a><a href="/docs/a">a</a><h2 id="x">x</h2>');
        });

        $audit->audit(['/docs/a', '/docs/b']);

        $this->assertSame(['/docs/a', '/docs/b'], $calls);
    }

    #[Test]
    public function aSecondAuditDoesNotKeepWhatTheFirstOneRead(): void
    {
        $served = ['/docs/a' => self::page('<a href="/docs/b">b</a>'), '/docs/b' => self::page('')];
        $audit = new ContentLinkAudit(function (string $path) use (&$served): ?string {
            return $served[$path] ?? null;
        });
        $this->assertSame([], $audit->audit(['/docs/a'])->missingPages);

        unset($served['/docs/b']);

        $this->assertCount(1, $audit->audit(['/docs/a'])->missingPages);
    }

    #[Test]
    public function aSiteWithoutLinksProvesNothing(): void
    {
        $report = $this->audit(['/docs/a' => self::page('<p>no links</p>')])->audit(['/docs/a']);

        $this->assertTrue($report->nothingFound());
        $this->assertSame(0, $report->linksChecked);
    }
}
