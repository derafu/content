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

use Stringable;

/**
 * A link of a page of the site to another page of it.
 *
 * `href` is what the page has, as it is written. `target`, `query` and
 * `fragment` are resolved from it (as a browser does: relative paths, `.` and
 * `..`, the query apart from the fragment), so they can not disagree with
 * `href`: whoever makes a `ContentLink` only says where it is, what it says and
 * what it points to, never the pieces already in that.
 *
 * It is part of the lint tools: it is for tools and tests, never for the code
 * that runs the package.
 */
final readonly class ContentLink implements Stringable
{
    /**
     * The path that the link goes to, once it is resolved from the page
     * (`/docs/core/auth/api`), without its query or its fragment.
     */
    public string $target;

    /**
     * What follows the `?` of `href`, or null if it has none.
     */
    public ?string $query;

    /**
     * What follows the `#` of `href`, decoded, or null if it has none.
     */
    public ?string $fragment;

    /**
     * @param string $from The path of the page that has the link, as it is
     * served (`/docs/core/auth`).
     * @param string $text The text of the link, as the page shows it (empty if
     * it has none, a link of an image for one).
     * @param string $href The link, as it is written in the page.
     */
    public function __construct(
        public string $from,
        public string $text,
        public string $href,
    ) {
        $href = $this->href;

        $fragment = null;
        if (($hash = strpos($href, '#')) !== false) {
            $fragment = rawurldecode(substr($href, $hash + 1));
            $href = substr($href, 0, $hash);
        }

        $query = null;
        if (($mark = strpos($href, '?')) !== false) {
            $query = substr($href, $mark + 1);
            $href = substr($href, 0, $mark);
        }

        $from = ($mark = strpos($this->from, '?')) !== false ? substr($this->from, 0, $mark) : $this->from;

        if ($href === '') {
            $target = $from;
        } elseif ($href[0] === '/') {
            $target = self::normalize($href);
        } else {
            // A relative link goes from the directory of the page, as the browser
            // reads it: `/docs/a/b` is in `/docs/a/`, and `/docs/a/` is in itself.
            $base = substr($from, 0, (int) strrpos($from, '/') + 1);
            $target = self::normalize($base . $href);
        }

        $this->target = $target;
        $this->query = $query;
        $this->fragment = $fragment;
    }

    /**
     * The identity of the link: where it is and what it says. It is what makes
     * two links of a page the same one (the dedup of the audit uses it), not a
     * text for a person to read (`__toString()` is that one).
     */
    public function identity(): string
    {
        return sprintf('%s => %s', $this->from, $this->href);
    }

    /**
     * Where the link really goes: `target`, with its `query` and its `fragment`
     * put back. The same as `href` when there was nothing to resolve (`href` was
     * already an absolute path, with nothing before it).
     */
    public function resolved(): string
    {
        return $this->target
            . ($this->query !== null ? '?' . $this->query : '')
            . ($this->fragment !== null ? '#' . $this->fragment : '');
    }

    /**
     * The facts of the link, for a test or a report that wants them apart, not
     * joined in a text.
     *
     * @return array{from: string, text: string, href: string, target: string, query: ?string, fragment: ?string, resolved: string}
     */
    public function toArray(): array
    {
        return [
            'from' => $this->from,
            'text' => $this->text,
            'href' => $this->href,
            'target' => $this->target,
            'query' => $this->query,
            'fragment' => $this->fragment,
            'resolved' => $this->resolved(),
        ];
    }

    /**
     * Where it is and what it says (`identity()`), where it really goes when
     * that differs from what `href` says, and the text of the link, when it has
     * one: what a failed assertion of a test shows.
     */
    public function __toString(): string
    {
        $line = $this->identity();

        if ($this->resolved() !== $this->href) {
            $line .= sprintf(' [%s]', $this->resolved());
        }

        if ($this->text !== '') {
            $line .= sprintf(' (text: "%s")', $this->text);
        }

        return $line;
    }

    /**
     * Resolves `.` and `..`, and the slashes that repeat, of a path.
     */
    private static function normalize(string $path): string
    {
        $segments = [];
        $parts = explode('/', $path);
        foreach ($parts as $part) {
            if ($part === '..') {
                array_pop($segments);
            } elseif ($part !== '.' && $part !== '') {
                $segments[] = $part;
            }
        }

        $last = end($parts);

        return '/' . implode('/', $segments) . (($last === '' || $last === '.' || $last === '..') && $segments !== [] ? '/' : '');
    }
}
