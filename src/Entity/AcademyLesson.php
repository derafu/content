<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Entity;

use Derafu\Content\Contract\AcademyLessonInterface;
use Derafu\Content\Contract\AcademyModuleInterface;

/**
 * Class that represents an academy lesson.
 */
class AcademyLesson extends ContentItem implements AcademyLessonInterface
{
    /**
     * {@inheritDoc}
     */
    public function module(): AcademyModuleInterface
    {
        $module = $this->parent();

        assert($module instanceof AcademyModuleInterface);

        return $module;
    }
}
