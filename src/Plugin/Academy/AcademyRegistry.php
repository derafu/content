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
use Derafu\Content\ContentSplFileInfo;
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
    public function get(string $uri): AcademyCourseInterface|AcademyModuleInterface|AcademyLessonInterface
    {
        $content = parent::get($uri);

        assert(
            $content instanceof AcademyCourseInterface
            || $content instanceof AcademyModuleInterface
            || $content instanceof AcademyLessonInterface
        );

        return $content;
    }

    /**
     * {@inheritDoc}
     */
    public function previous(string $uri, array $filters = []): AcademyModuleInterface|AcademyLessonInterface|null
    {
        return $this->neighbour($uri, $filters, -1);
    }

    /**
     * {@inheritDoc}
     */
    public function next(string $uri, array $filters = []): AcademyModuleInterface|AcademyLessonInterface|null
    {
        return $this->neighbour($uri, $filters, 1);
    }

    /**
     * Gets the module or the lesson that is around another one, inside of its
     * own course: after the last one of a course there is no next, and before
     * the first one there is no previous, instead of going to another course.
     *
     * @param string $uri URI of the content.
     * @param array<string, mixed> $filters Filters of the list that is walked.
     * @param int $step 1 for the next, -1 for the previous.
     * @return AcademyModuleInterface|AcademyLessonInterface|null
     */
    private function neighbour(string $uri, array $filters, int $step): AcademyModuleInterface|AcademyLessonInterface|null
    {
        // The course is the first part of the URI.
        $course = explode('/', $uri, 2)[0];

        $items = array_values(array_filter(
            $this->flatten($filters),
            fn ($item) => ($item instanceof AcademyModuleInterface || $item instanceof AcademyLessonInterface)
                && explode('/', $item->uri(), 2)[0] === $course
        ));

        $index = $this->search($uri, $items);

        return $index === null ? null : ($items[$index + $step] ?? null);
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
            /** @var array<int|string, mixed> $courseFiles The file of the course at 0, the modules by name. */
            if (!isset($courseFiles[0])) {
                continue;
            }

            /** @var ContentSplFileInfo $courseFile */
            $courseFile = $courseFiles[0];
            $course = new AcademyCourse($courseFile);
            $courses[(string) $courseSlug] = $course;

            unset($courseFiles[0]);
            $modules = [];
            foreach ($courseFiles as $moduleSlug => $moduleFiles) {
                /** @var array<int|string, mixed> $moduleFiles The file of the module at 0, the lessons by name. */
                if (!isset($moduleFiles[0])) {
                    continue;
                }

                /** @var ContentSplFileInfo $moduleFile */
                $moduleFile = $moduleFiles[0];
                $module = new AcademyModule($moduleFile);
                $modules[$moduleSlug] = $module;

                unset($moduleFiles[0]);
                $lessons = [];
                foreach ($moduleFiles as $lessonSlug => $lessonFiles) {
                    /** @var array<int|string, mixed> $lessonFiles The file of the lesson at 0. */
                    if (!isset($lessonFiles[0])) {
                        continue;
                    }

                    /** @var ContentSplFileInfo $lessonFile */
                    $lessonFile = $lessonFiles[0];
                    $lesson = new AcademyLesson($lessonFile);
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
