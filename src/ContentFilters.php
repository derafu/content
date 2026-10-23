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

use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException as InvalidArgumentException;

/**
 * Filters of the registries from the data of a request.
 *
 * What arrives in a request is text, and the registries read each filter with
 * its type: the text of the keys that are numbers or booleans is converted
 * here, once, and a value that can not be is an error with the status 400 that
 * names the filter. The other keys of the request are not filters and are kept
 * as they are.
 */
final class ContentFilters
{
    /**
     * Filters that are texts.
     *
     * @var list<string>
     */
    private const STRINGS = ['id', 'uri', 'slug', 'type', 'category', 'search', 'author', 'tag', 'attachment'];

    /**
     * Filters that are whole numbers.
     *
     * @var list<string>
     */
    private const INTEGERS = ['page', 'limit', 'year', 'month'];

    /**
     * Filters that are true or false.
     *
     * @var list<string>
     */
    private const BOOLEANS = ['indexable', 'searchable'];

    /**
     * Creates the filters from the data of a request.
     *
     * The keys without value (empty text) are not filters.
     *
     * @param array<string, mixed> $data Data of the request.
     * @return array<string, mixed>
     * @throws InvalidArgumentException If the value of a filter has another type.
     */
    public static function fromRequest(array $data): array
    {
        $filters = array_filter($data, fn ($value) => $value !== '');

        foreach (self::STRINGS as $key) {
            if (isset($filters[$key]) && !is_string($filters[$key])) {
                throw self::invalid($key, 'a text', $filters[$key]);
            }
        }

        foreach (self::INTEGERS as $key) {
            if (!isset($filters[$key])) {
                continue;
            }

            $value = $filters[$key];
            if (is_string($value) && ctype_digit($value)) {
                $filters[$key] = (int) $value;
            } elseif (!is_int($value)) {
                throw self::invalid($key, 'a whole number', $value);
            }
        }

        foreach (self::BOOLEANS as $key) {
            if (!isset($filters[$key])) {
                continue;
            }

            $value = $filters[$key];
            if (in_array($value, ['1', 'true'], true)) {
                $filters[$key] = true;
            } elseif (in_array($value, ['0', 'false'], true)) {
                $filters[$key] = false;
            } elseif (!is_bool($value)) {
                throw self::invalid($key, 'true or false', $value);
            }
        }

        return $filters;
    }

    /**
     * Creates the error of a filter that has another type.
     *
     * @param string $filter Name of the filter.
     * @param string $expected What the filter must be.
     * @param mixed $given Value that was received.
     */
    private static function invalid(string $filter, string $expected, mixed $given): InvalidArgumentException
    {
        return new InvalidArgumentException([
            'The filter "{filter}" must be {expected}, {given} given.',
            'filter' => $filter,
            'expected' => $expected,
            'given' => get_debug_type($given),
        ], 400);
    }
}
