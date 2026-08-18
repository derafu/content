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
use Derafu\Content\Exception\ContentNotFoundException;
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
     * @param DateTimeInterface|string $date Date of the archive. A string
     * must start with a "YYYYMM" prefix (the format slug() generates and
     * the "archive" route/controller receives); anything else, including
     * an otherwise natural-looking "YYYY-MM", is rejected instead of
     * silently producing an invalid month.
     * @param int $count Count of posts in the archive.
     * @param string|null $id ID of the archive.
     * @throws ContentNotFoundException If $date is a string that does not
     * start with a valid "YYYYMM" prefix.
     */
    public function __construct(
        DateTimeInterface|string $date,
        int $count = 0,
        ?string $id = null
    ) {
        if (is_string($date)) {
            if (!preg_match('/^(\d{4})(\d{2})/', $date, $matches) || (int) $matches[2] < 1 || (int) $matches[2] > 12) {
                throw new ContentNotFoundException(sprintf(
                    'Invalid archive "%s": expected it to start with a "YYYYMM" prefix.',
                    $date
                ));
            }

            $this->year = (int) $matches[1];
            $this->month = (int) $matches[2];
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
