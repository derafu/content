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
 * Academy course interface.
 */
interface AcademyCourseInterface extends ContentItemInterface
{
    /**
     * Modules.
     *
     * @return array<AcademyModuleInterface>
     */
    public function modules(): array;

    /**
     * Lessons.
     *
     * @return array<AcademyLessonInterface>
     */
    public function lessons(): array;

    /**
     * Videos.
     *
     * @return array<AcademyLessonInterface>
     */
    public function videos(): array;

    /**
     * Attachments.
     *
     * @return array<ContentAttachmentInterface>
     */
    public function attachments(): array;
}
