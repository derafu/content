<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Storage;

use SplFileInfo;

/**
 * Class that represents a content file info in the file system.
 */
class ContentSplFileInfo extends SplFileInfo
{
    /**
     * Checksum of the file.
     *
     * @var string
     */
    private string $checksum;

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
