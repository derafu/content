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

use SplFileObject;

/**
 * Class that represents a content file object in the file system.
 */
class ContentSplFileObject extends SplFileObject
{
    /**
     * Raw content of the file.
     *
     * This is the whole content of the file, including the metadata if any.
     *
     * @var string
     */
    private string $raw;

    /**
     * Get the raw data of the file.
     *
     * This is the whole content of the file, including the metadata if any.
     *
     * @return string
     */
    public function raw(): string
    {
        if (!isset($this->raw)) {
            $this->raw = $this->fread($this->getSize());
        }

        return $this->raw;
    }
}
