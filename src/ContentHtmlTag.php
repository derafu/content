<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content;

use Derafu\Content\Contract\ContentHtmlTagInterface;

/**
 * Class to manage the HTML tags to inject in the content.
 */
class ContentHtmlTag implements ContentHtmlTagInterface
{
    /**
     * Constructor.
     *
     * @param string $tagName The name of the tag.
     * @param array<string, mixed> $attributes The attributes of the tag.
     */
    public function __construct(
        private string $tagName,
        private array $attributes,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function tagName(): string
    {
        return $this->tagName;
    }

    /**
     * {@inheritDoc}
     */
    public function attributes(): array
    {
        return $this->attributes;
    }

    /**
     * {@inheritDoc}
     */
    public function toHtml(): string
    {
        $html = '<' . $this->tagName;

        foreach ($this->attributes as $name => $value) {
            if (is_bool($value)) {
                $html .= ' ' . $name;
            } else {
                $html .= ' ' . $name . '="' . htmlspecialchars($value) . '"';
            }
        }

        $html .= '>';

        return $html;
    }
}
