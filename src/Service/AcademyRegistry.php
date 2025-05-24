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
use Derafu\Content\Contract\AcademyLessonInterface;
use Derafu\Content\Contract\AcademyModuleInterface;
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
    public function get(string $uri): AcademyCourseInterface
    {
        $course = parent::get($uri);

        assert($course instanceof AcademyCourseInterface);

        return $course;
    }

    /**
     * {@inheritDoc}
     */
    public function previous(string $uri, array $filters = []): AcademyModuleInterface|AcademyLessonInterface|null
    {
        $content = parent::previous($uri, $filters);

        if ($content === null) {
            return null;
        }

        assert($content instanceof AcademyModuleInterface || $content instanceof AcademyLessonInterface);

        return $content;
    }

    /**
     * {@inheritDoc}
     */
    public function next(string $uri, array $filters = []): AcademyModuleInterface|AcademyLessonInterface|null
    {
        $content = parent::next($uri, $filters);

        if ($content === null) {
            return null;
        }

        assert($content instanceof AcademyModuleInterface || $content instanceof AcademyLessonInterface);

        return $content;
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
