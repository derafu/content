<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Plugin\Academy;

use Derafu\Content\Abstract\AbstractContentRegistry;
use Derafu\Content\Plugin\Academy\Contract\AcademyCourseInterface;
use Derafu\Content\Plugin\Academy\Contract\AcademyLessonInterface;
use Derafu\Content\Plugin\Academy\Contract\AcademyModuleInterface;
use Derafu\Content\Plugin\Academy\Contract\AcademyRegistryInterface;

/**
 * Academy registry.
 */
class AcademyRegistry extends AbstractContentRegistry implements AcademyRegistryInterface
{
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
     * Load the content items.
     *
     * @return array<string, AcademyCourseInterface>
     */
    protected function load(): array
    {
        $hierarchy = $this->contentLoader->scan(
            $this->path,
            $this->include,
            $this->exclude
        );

        $courses = [];

        foreach ($hierarchy as $courseSlug => $courseFiles) {
            if (!isset($courseFiles[0])) {
                continue;
            }

            $course = new AcademyCourse($courseFiles[0]);
            $courses[$courseSlug] = $course;

            unset($courseFiles[0]);
            $modules = [];
            foreach ($courseFiles as $moduleSlug => $moduleFiles) {
                if (!isset($moduleFiles[0])) {
                    continue;
                }

                $module = new AcademyModule($moduleFiles[0]);
                $modules[$moduleSlug] = $module;

                unset($moduleFiles[0]);
                $lessons = [];
                foreach ($moduleFiles as $lessonSlug => $lessonFiles) {
                    if (!isset($lessonFiles[0])) {
                        continue;
                    }

                    $lesson = new AcademyLesson($lessonFiles[0]);
                    $lessons[$lessonSlug] = $lesson;
                }

                uasort($lessons, fn ($a, $b) => $a->sidebar_position() <=> $b->sidebar_position());
                foreach ($lessons as $lesson) {
                    $module->addChild($lesson);
                }
            }

            uasort($modules, fn ($a, $b) => $a->sidebar_position() <=> $b->sidebar_position());
            foreach ($modules as $module) {
                $course->addChild($module);
            }
        }

        uasort($courses, fn ($a, $b) => $a->sidebar_position() <=> $b->sidebar_position());

        return $courses;
    }

    /**
     * {@inheritDoc}
     */
    protected function getContentClass(): string
    {
        return AcademyCourse::class;
    }
}
