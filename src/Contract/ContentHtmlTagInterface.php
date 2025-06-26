<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Contract;

/**
 * Interface for the HTML tags to inject in the content.
 */
interface ContentHtmlTagInterface
{
    /**
     * Get the name of the tag.
     *
     * @return string
     */
    public function tagName(): string;

    /**
     * Get the attributes of the tag.
     *
     * @return array<string, mixed>
     */
    public function attributes(): array;

    /**
     * Get the HTML representation of the tag.
     *
     * @return string
     */
    public function toHtml(): string;
}
