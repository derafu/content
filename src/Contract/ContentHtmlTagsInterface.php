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
 *
 * Plugins must use this interface to inject their HTML tags in the content.
 */
interface ContentHtmlTagsInterface
{
    /**
     * Add a tag to the head.
     *
     * @param ContentHtmlTagInterface $tag The tag to add.
     * @return static
     */
    public function addHeadTag(ContentHtmlTagInterface $tag): static;

    /**
     * Add a tag to the pre body.
     *
     * @param ContentHtmlTagInterface|string $tag The tag to add.
     * @return static
     */
    public function addPreBodyTag(ContentHtmlTagInterface|string $tag): static;

    /**
     * Add a tag to the post body.
     *
     * @param ContentHtmlTagInterface|string $tag The tag to add.
     * @return static
     */
    public function addPostBodyTag(ContentHtmlTagInterface|string $tag): static;

    /**
     * Get the HTML tags to inject in the head.
     *
     * @return string
     */
    public function headTagsToHtml(): string;

    /**
     * Get the HTML tags to inject before the body.
     *
     * @return string
     */
    public function preBodyTagsToHtml(): string;

    /**
     * Get the HTML tags to inject after the body.
     *
     * @return string
     */
    public function postBodyTagsToHtml(): string;
}
