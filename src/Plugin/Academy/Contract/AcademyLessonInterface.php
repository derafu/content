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
     * Get the test of the lesson.
     *
     * @return string|ContentAttachmentInterface|null
     */
    public function test(): string|ContentAttachmentInterface|null;

    /**
     * Get the icon of the lesson.
     *
     * @return string
     */
    public function icon(): string;
}
