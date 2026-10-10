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
use Derafu\Content\Lint\ContentLinkAuditReport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * What an audit of the links reports: how a finding is written in the message of a
 * failed test, and when an audit proves nothing.
 */
#[CoversClass(ContentLinkAuditReport::class)]
#[UsesClass(ContentLink::class)]
final class ContentLinkAuditReportTest extends TestCase
{
    private static function report(int $linksChecked = 1): ContentLinkAuditReport
    {
        return new ContentLinkAuditReport([], [], [], [], [], $linksChecked);
    }

    #[Test]
    public function aLinkIsDescribedByItsOwnText(): void
    {
        // `describe()` does not format anything itself: it is `ContentLink` that
        // says what it is (`__toString()`), the same text that `toArray()` and the
        // object itself give apart, for whoever wants the facts and not a line.
        $link = new ContentLink('/docs/a', 'gone', '/docs/gone');

        $this->assertSame([(string) $link], self::report()->describe([$link]));
        $this->assertSame('/docs/a => /docs/gone (text: "gone")', (string) $link);
    }

    #[Test]
    public function aPathIsDescribedAsItIs(): void
    {
        $this->assertSame(['/docs/x'], self::report()->describe(['/docs/x']));
    }

    #[Test]
    public function theFindingsAreDescribedInTheirOrderAndNothingIsNothing(): void
    {
        $report = self::report();
        $link = new ContentLink('/docs/a', '', '/gone');

        $this->assertSame([], $report->describe([]));
        $this->assertSame(['/docs/x', (string) $link], $report->describe(['/docs/x', $link]));
    }

    #[Test]
    public function anAuditThatCheckedNoLinksProvesNothing(): void
    {
        $this->assertTrue(self::report(0)->nothingFound());
        $this->assertFalse(self::report(1)->nothingFound());
    }
}
