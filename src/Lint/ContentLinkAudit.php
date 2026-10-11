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

use Closure;
use Dom\HTMLDocument;

/**
 * Checks the links that the pages of a site have to other pages of it, and the
 * fragments of those links, as the site serves them.
 *
 * It reads the pages after they are rendered, so it checks what a reader gets and
 * not what the source says: the same Markdown, the same ids of the headings (their
 * prefix, the number that is added to the one that repeats), the same routes. For
 * that it does not know how a site is made: it asks for each page with a function,
 * that gives its HTML or null if the site does not serve it.
 *
 *     $audit = new ContentLinkAudit(fn (string $path): ?string => $this->html($path));
 *     $report = $audit->audit(['/docs', '/docs/core/auth'], selector: 'main');
 *     $this->assertSame([], $report->describe($report->missingPages));
 *
 * It only finds facts, in a report. What is a problem is for the test of each site
 * to say. A link that is known and is not to be reported is allowed one by one,
 * by the page it is in and the `href` it has: an explicit decision, and it is lost
 * when the link changes.
 *
 * Part of the lint tools: it is for tools and tests, never for the code that runs
 * the package.
 */
final class ContentLinkAudit
{
    /**
     * The formats of a page that a site gives for a suffix in its path.
     */
    private const FORMAT_SUFFIX = '/\.(md|pdf|json)$/i';

    /**
     * What the pages that were read have: their ids and the links that are to be
     * audited (the `href` and the text of each anchor). A page that is not
     * served is false.
     *
     * @var array<string, false|array{ids: array<string, true>, links: list<array{href: string, text: string}>}>
     */
    private array $pages = [];

    /**
     * @param Closure(string): ?string $fetch The HTML of the page of a path
     * (`/docs/core/auth`), or null if the site does not serve it.
     */
    public function __construct(private readonly Closure $fetch)
    {
    }

    /**
     * Audits the links of some pages.
     *
     * @param list<string> $paths The pages whose links are checked.
     * @param array<string, list<string>> $allowed Links that are not reported: the
     * `href`s, as they are written, by the page they are in.
     * @param string|null $selector A CSS selector: only the links inside what it
     * selects are checked (`main`, for the content of the page and not the menu or
     * the footer, that every page repeats). Without it, all of them.
     * @param list<string> $pagesWithoutFragments Pages that a link still has to
     * reach, but whose fragment is never checked: a page whose content a browser
     * builds afterwards (with JavaScript), so the site never serves an id that is
     * in it.
     */
    public function audit(
        array $paths,
        array $allowed = [],
        ?string $selector = null,
        array $pagesWithoutFragments = []
    ): ContentLinkAuditReport {
        $this->pages = [];
        $unreachable = [];
        $missingPages = [];
        $missingAnchors = [];
        $formatLinks = [];
        $checked = 0;
        $seen = [];

        foreach ($paths as $path) {
            $page = $this->page($path, $selector);
            if ($page === false) {
                $unreachable[] = $path;
                continue;
            }

            foreach ($page['links'] as $anchor) {
                $link = $this->link($path, $anchor['href'], $anchor['text']);
                if ($link === null || in_array($link->href, $allowed[$path] ?? [], true) || isset($seen[$link->identity()])) {
                    continue;
                }
                $seen[$link->identity()] = true;
                $checked++;

                // A link to a format of another page (`other.md`): the site gives the file,
                // not the page. The ones to a format of the page itself are the links to
                // its own downloads, which a site shows on purpose.
                if (preg_match(self::FORMAT_SUFFIX, $link->target) === 1) {
                    if (preg_replace(self::FORMAT_SUFFIX, '', $link->target) !== $link->from) {
                        $formatLinks[] = $link;
                    }
                    continue;
                }

                $target = $this->page($link->target, $selector);
                if ($target === false) {
                    $missingPages[] = $link;
                    continue;
                }

                // An empty fragment and `top` always go to the top of the page (HTML).
                if (
                    $link->fragment !== null && $link->fragment !== '' && $link->fragment !== 'top'
                    && !isset($target['ids'][$link->fragment])
                    && !in_array($link->target, $pagesWithoutFragments, true)
                ) {
                    $missingAnchors[] = $link;
                }
            }
        }

        return new ContentLinkAuditReport($paths, $unreachable, $missingPages, $missingAnchors, $formatLinks, $checked);
    }

    /**
     * Reads a page, once.
     *
     * @return false|array{ids: array<string, true>, links: list<array{href: string, text: string}>}
     */
    private function page(string $path, ?string $selector): false|array
    {
        if (isset($this->pages[$path])) {
            return $this->pages[$path];
        }

        $html = ($this->fetch)($path);
        if ($html === null) {
            return $this->pages[$path] = false;
        }

        $document = HTMLDocument::createFromString($html, LIBXML_NOERROR);

        $ids = [];
        foreach ($document->querySelectorAll('[id], a[name]') as $element) {
            foreach (['id', 'name'] as $attribute) {
                $value = $element->getAttribute($attribute);
                if ($value !== null && $value !== '') {
                    $ids[$value] = true;
                }
            }
        }

        $links = [];
        foreach ($document->querySelectorAll(($selector !== null ? $selector . ' ' : '') . 'a[href]') as $anchor) {
            $links[] = [
                'href' => (string) $anchor->getAttribute('href'),
                'text' => trim($anchor->textContent),
            ];
        }

        return $this->pages[$path] = ['ids' => $ids, 'links' => $links];
    }

    /**
     * The link of a page, resolved, or null if it does not go to a page of the site:
     * another site, another scheme (`mailto:`), or nothing.
     */
    private function link(string $from, string $href, string $text): ?ContentLink
    {
        $href = trim($href);
        if ($href === '' || str_starts_with($href, '//') || preg_match('#^[a-z][a-z0-9+.-]*:#i', $href) === 1) {
            return null;
        }

        return new ContentLink($from, $text, $href);
    }
}
