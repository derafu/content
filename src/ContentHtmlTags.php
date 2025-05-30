<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content;

use Derafu\Content\Contract\ContentHtmlTagInterface;
use Derafu\Content\Contract\ContentHtmlTagsInterface;

/**
 * Class to manage the HTML tags to inject in the content.
 *
 * Plugins should use this class to inject their HTML tags in the content.
 */
class ContentHtmlTags implements ContentHtmlTagsInterface
{
    /**
     * Tags to inject in the head.
     *
     * Will be inserted before the closing </head> tag after scripts added by
     * config.
     *
     * @var array<ContentHtmlTagInterface>
     */
    private array $headTags = [];

    /**
     * Tags to inject before the body.
     *
     * Will be inserted after the opening <body> tag before any child elements.
     *
     * @var array<ContentHtmlTagInterface|string>
     */
    private array $preBodyTags = [];

    /**
     * Tags to inject after the body.
     *
     * Will be inserted before the closing </body> tag after all child elements.
     *
     * @var array<ContentHtmlTagInterface|string>
     */
    private array $postBodyTags = [];

    /**
     * {@inheritDoc}
     */
    public function addHeadTag(ContentHtmlTagInterface $tag): static
    {
        $this->headTags[] = $tag;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function addPreBodyTag(ContentHtmlTagInterface|string $tag): static
    {
        $this->preBodyTags[] = $tag;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function addPostBodyTag(ContentHtmlTagInterface|string $tag): static
    {
        $this->postBodyTags[] = $tag;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function headTagsToHtml(): string
    {
        return implode(
            '',
            array_map(
                fn ($tag) => $tag->toHtml(),
                $this->headTags
            )
        );
    }

    /**
     * {@inheritDoc}
     */
    public function preBodyTagsToHtml(): string
    {
        return implode(
            '',
            array_map(
                fn ($tag) => $tag instanceof ContentHtmlTagInterface ? $tag->toHtml() : $tag,
                $this->preBodyTags
            )
        );
    }

    /**
     * {@inheritDoc}
     */
    public function postBodyTagsToHtml(): string
    {
        return implode(
            '',
            array_map(
                fn ($tag) => $tag instanceof ContentHtmlTagInterface ? $tag->toHtml() : $tag,
                $this->postBodyTags
            )
        );
    }
}
