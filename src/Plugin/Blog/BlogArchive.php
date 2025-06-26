<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Plugin\Blog;

use DateTimeInterface;
use Derafu\Content\Plugin\Blog\Contract\BlogArchiveInterface;
use Derafu\Support\Str;

/**
 * Class that represents a blog archive.
 */
class BlogArchive implements BlogArchiveInterface
{
    /**
     * ID of the archive.
     *
     * @var string
     */
    private string $id;

    /**
     * Year of the archive.
     *
     * @var int
     */
    private int $year;

    /**
     * Month of the archive.
     *
     * @var int
     */
    private int $month;

    /**
     * Name of the archive.
     */
    private string $name;

    /**
     * Slug of the archive.
     */
    private string $slug;

    /**
     * Count of posts in the archive.
     */
    private int $count;

    /**
     * Constructor.
     *
     * @param DateTimeInterface|string $date Date of the archive.
     * @param int $count Count of posts in the archive.
     * @param string|null $id ID of the archive.
     */
    public function __construct(
        DateTimeInterface|string $date,
        int $count = 0,
        ?string $id = null
    ) {
        if (is_string($date)) {
            [$date, $slug] = explode('-', $date);
            $this->year = (int) substr($date, 0, 4);
            $this->month = (int) substr($date, 4, 2);
        } else {
            $this->year = (int) $date->format('Y');
            $this->month = (int) $date->format('m');
        }

        if ($id === null) {
            $this->id = sprintf('%04d%02d', $this->year, $this->month);
        } else {
            $this->id = $id;
        }

        $this->count = $count;
    }

    /**
     * {@inheritDoc}
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * {@inheritDoc}
     */
    public function year(): int
    {
        return $this->year;
    }

    /**
     * {@inheritDoc}
     */
    public function month(): int
    {
        return $this->month;
    }

    /**
     * {@inheritDoc}
     */
    public function name(): string
    {
        if (!isset($this->name)) {
            $this->name = sprintf('%02d/%04d', $this->month, $this->year);
        }

        return $this->name;
    }

    /**
     * {@inheritDoc}
     */
    public function slug(): string
    {
        if (!isset($this->slug)) {
            $this->slug = sprintf(
                '%s-%s',
                $this->id(),
                Str::slug($this->name())
            );
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
            'id' => $this->id(),
            'year' => $this->year(),
            'month' => $this->month(),
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
        return $this->name();
    }
}
