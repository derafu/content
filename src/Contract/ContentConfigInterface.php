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

/**
 * Interface for content configuration.
 */
interface ContentConfigInterface
{
    /**
     * Get the title of the content website.
     *
     * @return string
     */
    public function title(): string;

    /**
     * Get the URL of the content website.
     *
     * @return string
     */
    public function url(): string;

    /**
     * Get the base URL of the content website.
     *
     * @return string
     */
    public function baseUrl(): string;

    /**
     * Get the favicon of the content website.
     *
     * @return string|null
     */
    public function favicon(): string|null;

    /**
     * Get the title delimiter of the content website.
     *
     * @return string
     */
    public function titleDelimiter(): string;

    /**
     * Get the tagline of the content website.
     *
     * @return string|null
     */
    public function tagline(): string|null;

    /**
     * Get the URI format of the content website.
     *
     * @return string
     */
    public function uriFormat(): string;

    /**
     * Get whether the content website is no-index.
     *
     * @return bool
     */
    public function noIndex(): bool;

    /**
     * Get the action to take when a broken link is found.
     *
     * @return string
     */
    public function onBrokenLinks(): string;

    /**
     * Get the markdown configuration of the content website.
     *
     * @return array
     */
    public function markdown(): array;

    /**
     * Get the plugins of the content website.
     *
     * @return array
     */
    public function plugins(): array;

    /**
     * Get the i18n configuration of the content website.
     *
     * @return array
     */
    public function i18n(): array;

    /**
     * Get the custom fields of the content website.
     *
     * @return array<string, mixed>
     */
    public function customFields(): array;

    /**
     * Get a custom field of the content website.
     *
     * @param string $name Name of the custom field.
     * @param mixed $default Default value to return if the key is not found.
     * @return mixed
     */
    public function customField(string $name, mixed $default = null): mixed;
}
