<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Service;

use Derafu\Content\Contract\AcademyCourseInterface;
use Derafu\Content\Contract\AcademyRegistryInterface;
use Derafu\Content\Entity\AcademyCourse;

/**
 * Academy registry.
 */
class AcademyRegistry extends ContentRegistry implements AcademyRegistryInterface
{
    /**
     * {@inheritDoc}
     */
    public function get(string $slug): AcademyCourseInterface
    {
        $course = parent::get($slug);

        assert($course instanceof AcademyCourseInterface);

        return $course;
    }

    /**
     * {@inheritDoc}
     */
    protected function getContentClass(): string
    {
        return AcademyCourse::class;
    }
}
