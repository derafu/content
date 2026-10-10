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
 * A link of a page of the site to another page of it.
 *
 * It is part of the lint tools: it is for tools and tests, never for the code
 * that runs the package.
 */
final readonly class ContentLink
{
    /**
     * @param string $from The path of the page that has the link, as it is served
     * (`/docs/core/auth`).
     * @param string $href The link, as it is written in the page.
     * @param string $target The path that the link goes to, once it is resolved
     * from the page (`/docs/core/auth/api`).
     * @param string|null $fragment What follows the `#`, decoded, or null if the
     * link has none.
     */
    public function __construct(
        public string $from,
        public string $href,
        public string $target,
        public ?string $fragment
    ) {
    }

    /**
     * The identity of the link: where it is and what it says.
     */
    public function identity(): string
    {
        return sprintf('%s => %s', $this->from, $this->href);
    }
}
