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

use Derafu\Content\Contract\AcademyCourseInterface;
use Derafu\Content\Contract\AcademyLessonInterface;
use Derafu\Content\Contract\AcademyModuleInterface;

/**
 * Class that represents an academy course.
 */
class AcademyCourse extends ContentItem implements AcademyCourseInterface
{
    /**
     * Lessons of the course.
     *
     * @var array<string, AcademyLessonInterface>
     */
    private array $lessons;

    /**
     * Time of the course in minutes.
     *
     * @var int
     */
    private int $time;

    /**
     * {@inheritDoc}
     */
    public function type(): string
    {
        return 'academy';
    }

    /**
     * {@inheritDoc}
     */
    public function category(): string
    {
        return 'course';
    }

    /**
     * {@inheritDoc}
     */
    public function route(): object
    {
        return (object) [
            'name' => $this->type() . '_' . $this->category(),
            'params' => ['course' => $this->slug()],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function links(): array
    {
        if (!isset($this->links)) {
            $urlBasePath = '/academy';

            $this->links = [
                'self' => ['href' => $urlBasePath . '/' . $this->uri()],
                'collection' => ['href' => $urlBasePath],
            ];
        }

        return $this->links;
    }

    /**
     * {@inheritDoc}
     */
    public function modules(): array
    {
        $modules = [];

        foreach ($this->children() as $child) {
            assert($child instanceof AcademyModuleInterface);
            $modules[$child->slug()] = $child;
        }

        return $modules;
    }

    /**
     * {@inheritDoc}
     */
    public function lessons(): array
    {
        if (!isset($this->lessons)) {
            $this->lessons = [];

            foreach ($this->modules() as $module) {
                foreach ($module->lessons() as $lesson) {
                    $this->lessons[$lesson->uri()] = $lesson;
                }
            }
        }

        return $this->lessons;
    }

    /**
     * {@inheritDoc}
     */
    public function videos(): array
    {
        $videos = [];

        foreach ($this->modules() as $module) {
            foreach ($module->lessons() as $lesson) {
                if ($lesson->video()) {
                    $videos[] = $lesson;
                }
            }
        }

        return $videos;
    }

    /**
     * {@inheritDoc}
     */
    public function attachments(): array
    {
        $attachments = [];

        foreach ($this->modules() as $module) {
            foreach ($module->lessons() as $lesson) {
                foreach ($lesson->attachments() as $attachment) {
                    $attachments[] = $attachment;
                }
            }
        }

        return $attachments;
    }

    /**
     * {@inheritDoc}
     */
    public function time(): int
    {
        if (!isset($this->time)) {
            $this->time = 0;
            foreach ($this->modules() as $module) {
                $this->time += $module->time();
            }
        }

        return $this->time;
    }
}
