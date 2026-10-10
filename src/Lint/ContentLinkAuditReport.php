<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Lint;

/**
 * What an audit of the links of a site found.
 *
 * It only has facts, and each one is a fact about what was read: the pages that
 * were audited, as the site serves them. It does not say whether any of them is a
 * problem: that is for the test of each site to say, by asserting that the lists it
 * cares about are empty.
 *
 *     $this->assertSame([], $report->describe($report->missingPages));
 *
 * Part of the lint tools: it is for tools and tests, never for the code that runs
 * the package.
 */
final readonly class ContentLinkAuditReport
{
    /**
     * @param list<string> $pages The paths that were audited.
     * @param list<string> $unreachablePages Paths that were asked to be audited and
     * that the site does not serve.
     * @param list<ContentLink> $missingPages Links to a page that the site does not
     * serve.
     * @param list<ContentLink> $missingAnchors Links to a page that exists, with a
     * fragment that is not the `id` of any element of it.
     * @param list<ContentLink> $formatLinks Links to a format of another page (`.md`,
     * `.pdf`, `.json`) and not to the page: the site gives the file, not the page.
     * They are not checked any further. The links of a page to its own formats (the
     * downloads that a site shows) are not here.
     * @param int $linksChecked How many links to a page of the site were found,
     * counted once for each page.
     */
    public function __construct(
        public array $pages,
        public array $unreachablePages,
        public array $missingPages,
        public array $missingAnchors,
        public array $formatLinks,
        public int $linksChecked
    ) {
    }

    /**
     * Whether no link at all was found: the audit read nothing, so it proves nothing.
     */
    public function nothingFound(): bool
    {
        return $this->linksChecked === 0;
    }

    /**
     * Turns findings into lines, one each, for the message of a failed test: the
     * page, the link as it is written and, if it is not the same, where it goes.
     *
     * @param list<ContentLink|string> $findings
     * @return list<string>
     */
    public function describe(array $findings): array
    {
        $lines = [];

        foreach ($findings as $finding) {
            if (is_string($finding)) {
                $lines[] = $finding;
                continue;
            }

            $goes = $finding->target . ($finding->fragment !== null ? '#' . $finding->fragment : '');
            $lines[] = $finding->identity() . ($goes !== $finding->href ? ' [' . $goes . ']' : '');
        }

        return $lines;
    }
}
