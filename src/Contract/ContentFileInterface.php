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

use DateTimeInterface;
use JsonSerializable;
use Stringable;

/**
 * Interface for the representation of a content file.
 */
interface ContentFileInterface extends JsonSerializable, Stringable
{
    /**
     * Get the path of the content file.
     *
     * @return string
     */
    public function path(): string;

    /**
     * Get the directory of the content file.
     *
     * @return string
     */
    public function directory(): string;

    /**
     * Get the name of the content file.
     *
     * @return string
     */
    public function name(): string;

    /**
     * Get the extension of the content file.
     *
     * @return string
     */
    public function extension(): string;

    /**
     * Get the slug of the content file.
     *
     * @return string
     */
    public function slug(): string;

    /**
     * Get the data of the content file.
     *
     * @return string
     */
    public function data(): string;

    /**
     * Get the metadata of the content file.
     *
     * @param string|null $key Key of the metadata to get.
     * @param mixed $default Default value to return if the key is not found.
     * @return mixed
     */
    public function metadata(?string $key = null, mixed $default = null): mixed;

    /**
     * Get the content of the content file.
     *
     * @return string
     */
    public function content(): string;

    /**
     * Get the summary of the content file.
     *
     * @return string
     */
    public function summary(): string;

    /**
     * Get the preview of the content file.
     *
     * @param int $maxLength Maximum length of the preview.
     * @return string
     */
    public function preview(int $maxLength = 300): string;

    /**
     * Calculate the checksum of the content file.
     *
     * @return string
     */
    public function checksum(): string;

    /**
     * Get the created date of the content file.
     *
     * @return DateTimeInterface
     */
    public function created(): DateTimeInterface;

    /**
     * Get the modified date of the content file.
     *
     * @return DateTimeInterface
     */
    public function modified(): DateTimeInterface;

    /**
     * Get the published date of the content file.
     *
     * @return DateTimeInterface
     */
    public function published(): DateTimeInterface;

    /**
     * Get the deprecated date of the content file.
     *
     * @return DateTimeInterface|null
     */
    public function deprecated(): ?DateTimeInterface;

    /**
     * Get the tags of the content file.
     *
     * @return ContentTagInterface[]
     */
    public function tags(): array;

    /**
     * Get the content file as an array.
     *
     * @return array
     */
    public function toArray(): array;

    /**
     * Get the content file as a JSON string.
     *
     * @return array
     */
    public function jsonSerialize(): array;

    /**
     * Get the content file as a string.
     *
     * @return string
     */
    public function __toString(): string;
}
