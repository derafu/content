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

use SplFileInfo;

/**
 * Class that represents a content file info in the file system.
 */
class ContentSplFileInfo extends SplFileInfo
{
    /**
     * ID of the file.
     *
     * @var string
     */
    private string $uri;

    /**
     * Checksum of the file.
     *
     * @var string
     */
    private string $checksum;

    /**
     * Get the URI of the file in the registry.
     *
     * The URI is a unique identifier for the file in the registry created using
     * the file path, relative to the base path if provided and without the
     * extension.
     *
     * @param string $basePath Base path of the content.
     * @return string
     */
    public function getUri(string $basePath = ''): string
    {
        if (!isset($this->uri)) {
            $dirname = str_replace(rtrim($basePath, '/'), '', $this->getPath());
            $basename = basename($this->getPathname(), '.' . $this->getExtension());
            $this->uri = ltrim($dirname . '/' . $basename, '/');
        }

        return $this->uri;
    }

    /**
     * Calculate the checksum of the file content using the sha256 algorithm.
     *
     * @return string
     */
    public function getChecksum(): string
    {
        if (!isset($this->checksum)) {
            $this->checksum = hash_file('sha256', $this->getRealPath());
        }

        return $this->checksum;
    }
}
