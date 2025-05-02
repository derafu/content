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

use DateTimeInterface;
use Derafu\Content\Contract\ContentMonthInterface;
use Derafu\Support\Str;

/**
 * Class that represents a content month.
 */
class ContentMonth implements ContentMonthInterface
{
    /**
     * Year of the month.
     *
     * @var int
     */
    private int $year;

    /**
     * Month of the month.
     *
     * @var int
     */
    private int $month;

    /**
     * Name of the month.
     */
    private string $name;

    /**
     * Slug of the month.
     */
    private string $slug;

    /**
     * Count of posts with this month.
     */
    private int $count;

    /**
     * Constructor.
     *
     * @param DateTimeInterface|string $date Date of the month.
     * @param int $count Count of posts with this month.
     */
    public function __construct(DateTimeInterface|string $date, int $count = 0)
    {
        if (is_string($date)) {
            [$date, $slug] = explode('-', $date);
            $this->year = (int) substr($date, 0, 4);
            $this->month = (int) substr($date, 4, 2);
        } else {
            $this->year = (int) $date->format('Y');
            $this->month = (int) $date->format('m');
        }


        $this->count = $count;
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
                '%04d%02d-%s',
                $this->year,
                $this->month,
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
