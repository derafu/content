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

use Derafu\Content\Contract\ContentAuthorInterface;
use Derafu\Support\Str;

/**
 * Class that represents a content author.
 */
class ContentAuthor implements ContentAuthorInterface
{
    /**
     * Name of the author.
     */
    private string $name;

    /**
     * Slug of the author.
     */
    private string $slug;

    /**
     * Constructor.
     *
     * @param string $name Name of the author.
     * @param string|null $slug Slug of the author.
     */
    public function __construct(string $name, ?string $slug = null)
    {
        $this->name = $name;
        $this->slug = $slug ?? Str::slug($name);
    }

    /**
     * {@inheritDoc}
     */
    public function id(): string
    {
        return $this->slug();
    }

    /**
     * {@inheritDoc}
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * {@inheritDoc}
     */
    public function slug(): string
    {
        if (!isset($this->slug)) {
            $this->slug = Str::slug($this->name);
        }

        return $this->slug;
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name(),
            'slug' => $this->slug(),
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * {@inheritDoc}
     */
    public function __toString(): string
    {
        return $this->name();
    }
}
