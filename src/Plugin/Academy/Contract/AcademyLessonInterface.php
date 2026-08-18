<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Plugin\Academy\Contract;

use Derafu\Content\Contract\ContentAttachmentInterface;
use Derafu\Content\Contract\ContentItemInterface;

/**
 * Academy lesson interface.
 */
interface AcademyLessonInterface extends ContentItemInterface
{
    /**
     * Get module.
     *
     * @return AcademyModuleInterface
     */
    public function module(): AcademyModuleInterface;

    /**
     * Get the self-assessment test ("quiz") of the lesson, parsed from its
     * JSON attachment (see testAttachment()), or null if the lesson has
     * none, or if its "test" metadata does not point to a local
     * attachment.
     *
     * @return AcademyTestInterface|null
     */
    public function test(): ?AcademyTestInterface;

    /**
     * Get the raw attachment backing the lesson's test, if the "test"
     * metadata points to one — either via the "?attachment=name"
     * convention, or a literal path ending in "/_attachments/name"
     * (both resolve to the same local attachment by its filename).
     *
     * @return ContentAttachmentInterface|null
     */
    public function testAttachment(): ?ContentAttachmentInterface;

    /**
     * Get the icon of the lesson.
     *
     * @return string
     */
    public function icon(): string;
}
