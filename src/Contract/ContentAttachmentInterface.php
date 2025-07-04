<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Contract;

use Derafu\Http\Enum\ContentType;

/**
 * Content attachment interface.
 */
interface ContentAttachmentInterface
{
    /**
     * Get the ID of the attachment.
     *
     * @return string
     */
    public function id(): string;

    /**
     * Get the path of the attachment.
     *
     * @return string
     */
    public function path(): string;

    /**
     * Get the directory of the attachment.
     *
     * @return string
     */
    public function directory(): string;

    /**
     * Get the name of the attachment, without the file extension.
     *
     * @return string
     */
    public function name(): string;

    /**
     * Get the file extension of the attachment.
     *
     * @return string
     */
    public function extension(): string;

    /**
     * Calculate the checksum of the attachment.
     *
     * @return string
     */
    public function checksum(): string;

    /**
     * Get the raw data of the attachment.
     *
     * @return string
     */
    public function raw(): string;

    /**
     * Get the type of the attachment.
     *
     * @return ContentType
     */
    public function type(): ContentType;

    /**
     * Get the size of the attachment.
     *
     * @return int
     */
    public function size(): int;

    /**
     * Get the parent of the attachment.
     *
     * @return ContentItemInterface
     */
    public function parent(): ContentItemInterface;
}
