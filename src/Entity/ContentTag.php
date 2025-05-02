<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Entity;

use Derafu\Content\Contract\ContentTagInterface;
use Derafu\Support\Str;

/**
 * Class that represents a content tag.
 */
final class ContentTag implements ContentTagInterface
{
    /**
     * Name of the tag.
     */
    private string $name;

    /**
     * Slug of the tag.
     */
    private string $slug;

    /**
     * Count of posts with this tag.
     */
    private int $count;

    /**
     * Constructor.
     *
     * @param string $name Name of the tag.
     * @param int $count Count of posts with this tag.
     */
    public function __construct(string $name, int $count = 0)
    {
        $this->name = $name;
        $this->count = $count;
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
    public function count(): int
    {
        return $this->count;
    }

    /**
     * {@inheritDoc}
     */
    public function increment(): void
    {
        $this->count++;
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name(),
            'slug' => $this->slug(),
            'count' => $this->count(),
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
        return $this->name() . ' (' . $this->count() . ')';
    }
}
