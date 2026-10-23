<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent;

use Derafu\Content\ContentFilters;
use Derafu\Translation\Contract\TranslatableInterface;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * The data of a request are texts: the filters that are numbers or booleans
 * are converted once, and what can not be converted is an error with the
 * status 400 that names the filter.
 */
#[CoversClass(ContentFilters::class)]
final class ContentFiltersTest extends TestCase
{
    public function testTheNumbersAndTheBooleansAreConverted(): void
    {
        $filters = ContentFilters::fromRequest([
            'page' => '2',
            'limit' => 10,
            'year' => '2026',
            'month' => '03',
            'indexable' => 'true',
            'searchable' => '0',
            'search' => 'text',
        ]);

        $this->assertSame([
            'page' => 2,
            'limit' => 10,
            'year' => 2026,
            'month' => 3,
            'indexable' => true,
            'searchable' => false,
            'search' => 'text',
        ], $filters);
    }

    public function testTheBooleansThatAreAlreadyBooleansAreKept(): void
    {
        $this->assertSame(
            ['indexable' => false, 'searchable' => true],
            ContentFilters::fromRequest(['indexable' => false, 'searchable' => true])
        );
    }

    public function testTheKeysWithoutValueAndTheOtherKeysAreHandledAsTheyAre(): void
    {
        $filters = ContentFilters::fromRequest(['search' => '', 'full' => '1', 'tag' => 'php', 'anything' => ['a']]);

        $this->assertSame(['full' => '1', 'tag' => 'php', 'anything' => ['a']], $filters);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function provideFiltersThatAreNotValid(): array
    {
        return [
            'a text that is a list' => [
                ['search' => ['a']],
                'The filter "search" must be a text, array given.',
            ],
            'a page that is not a number' => [
                ['page' => 'abc'],
                'The filter "page" must be a whole number, string given.',
            ],
            'a limit that is negative' => [
                ['limit' => '-5'],
                'The filter "limit" must be a whole number, string given.',
            ],
            'a month that is a decimal' => [
                ['month' => 1.5],
                'The filter "month" must be a whole number, float given.',
            ],
            'indexable that is not true or false' => [
                ['indexable' => 'yes'],
                'The filter "indexable" must be true or false, string given.',
            ],
            'searchable that is a list' => [
                ['searchable' => ['1']],
                'The filter "searchable" must be true or false, array given.',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('provideFiltersThatAreNotValid')]
    public function testAFilterThatCanNotBeConvertedIsAnErrorWithStatus400(array $data, string $message): void
    {
        $exception = null;
        try {
            ContentFilters::fromRequest($data);
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(InvalidArgumentException::class, $exception);
        $this->assertInstanceOf(TranslatableInterface::class, $exception);
        $this->assertSame(400, $exception->getCode());
        $this->assertSame($message, $exception->getMessage());
    }
}
