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

use Derafu\Content\Contract\ContentItemInterface;

/**
 * Academy module interface.
 */
interface AcademyModuleInterface extends ContentItemInterface
{
    /**
     * Get course.
     *
     * @return AcademyCourseInterface
     */
    public function course(): AcademyCourseInterface;

    /**
     * Get lessons.
     *
     * @return array<string, AcademyLessonInterface>
     */
    public function lessons(): array;
}
