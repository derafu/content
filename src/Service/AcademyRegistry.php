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
use Derafu\Content\Entity\AcademyLesson;
use Derafu\Content\Entity\AcademyModule;

/**
 * Academy registry.
 */
class AcademyRegistry extends ContentRegistry implements AcademyRegistryInterface
{
    /**
     * Content items of type AcademyCourse.
     *
     * @var array<string, AcademyCourseInterface>
     */
    private array $courses;

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
    public function all(): array
    {
        if (!isset($this->courses)) {
            $this->courses = $this->loader->load(
                $this->path,
                $this->extensions,
                AcademyCourse::class,
                recursive: false
            );

            foreach ($this->courses as $course) {
                // Load modules.
                $modulesPath = $course->directory() . '/' . $course->name() . '/';
                $modules = $this->loader->load(
                    $modulesPath,
                    $this->extensions,
                    AcademyModule::class,
                    recursive: false
                );

                // Add modules to course.
                if (!empty($modules)) {
                    foreach ($modules as $module) {
                        $course->addChild($module);

                        // Load lessons.
                        $lessonsPath = $module->directory() . '/' . $module->name() . '/';
                        $lessons = $this->loader->load(
                            $lessonsPath,
                            $this->extensions,
                            AcademyLesson::class,
                            recursive: false
                        );

                        // Add lessons to module.
                        if (!empty($lessons)) {
                            foreach ($lessons as $lesson) {
                                $module->addChild($lesson);
                            }
                        }
                    }
                }
            }
        }

        return $this->courses;
    }
}
