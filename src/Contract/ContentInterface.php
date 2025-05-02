<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Contract;

use DateTimeImmutable;
use JsonSerializable;
use Stringable;

/**
 * Interface for the representation of a content.
 */
interface ContentItemInterface extends JsonSerializable, Stringable
{
    /**
     * Get the path of the content.
     *
     * @return string
     */
    public function path(): string;

    /**
     * Get the directory of the content.
     *
     * @return string
     */
    public function directory(): string;

    /**
     * Get the name of the content, without the file extension.
     *
     * @return string
     */
    public function name(): string;

    /**
     * Get the file extension of the content.
     *
     * @return string
     */
    public function extension(): string;

    /**
     * Calculate the checksum of the file content.
     *
     * @return string
     */
    public function checksum(): string;

    /**
     * Get the raw data of the content.
     *
     * This is the whole content of the file content, including the metadata.
     *
     * @return string
     */
    public function raw(): string;

    /**
     * Get the metadata of the content.
     *
     * This is the metadata of the content, without the data.
     *
     * @param string|null $key Key of the metadata to get.
     * @param mixed $default Default value to return if the key is not found.
     * @return mixed
     */
    public function metadata(?string $key = null, mixed $default = null): mixed;

    /**
     * Get the data of the content.
     *
     * This is the data of the content, without the metadata.
     *
     * @return string
     */
    public function data(): string;

    /**
     * Get the slug of the content.
     *
     * @return string
     */
    public function slug(): string;

    /**
     * Get the title of the content.
     *
     * @return string
     */
    public function title(): string;

    /**
     * Get the summary of the content.
     *
     * @return string
     */
    public function summary(): string;

    /**
     * Get the preview of the content.
     *
     * @param int $maxLength Maximum length of the preview.
     * @return string
     */
    public function preview(int $maxLength = 300): string;

    /**
     * Get the created date of the content.
     *
     * @return DateTimeImmutable
     */
    public function created(): DateTimeImmutable;

    /**
     * Get the modified date of the content.
     *
     * @return DateTimeImmutable
     */
    public function modified(): DateTimeImmutable;

    /**
     * Get the published date of the content.
     *
     * @return DateTimeImmutable
     */
    public function published(): DateTimeImmutable;

    /**
     * Get the deprecated date of the content.
     *
     * @return DateTimeImmutable|null
     */
    public function deprecated(): ?DateTimeImmutable;

    /**
     * Get the tags of the content.
     *
     * @return ContentTagInterface[]
     */
    public function tags(): array;

    /**
     * Get the main image of the content, if any.
     *
     * @return string|null
     */
    public function image(): ?string;

    /**
     * Get the author of the content, if any.
     *
     * @return ContentAuthorInterface|null
     */
    public function author(): ?ContentAuthorInterface;

    /**
     * Get the time required to read or watch the content.
     *
     * @return int
     */
    public function time(): int;

    /**
     * Get the links of the content.
     *
     * @return array
     */
    public function links(): array;

    /**
     * Get the content as an array.
     *
     * @return array
     */
    public function toArray(): array;

    /**
     * Get the content as a JSON string.
     *
     * @return array
     */
    public function jsonSerialize(): array;

    /**
     * Get the content as a string.
     *
     * @return string
     */
    public function __toString(): string;
}
