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

use Derafu\Content\Abstract\AbstractContentItem;
use Derafu\Content\Plugin\Academy\Contract\AcademyCourseInterface;
use Derafu\Content\Plugin\Academy\Contract\AcademyLessonInterface;
use Derafu\Content\Plugin\Academy\Contract\AcademyModuleInterface;

/**
 * Class that represents an academy module.
 */
class AcademyModule extends AbstractContentItem implements AcademyModuleInterface
{
    /**
     * Time of the module in minutes.
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
        return 'module';
    }

    /**
     * {@inheritDoc}
     */
    public function route(): object
    {
        return (object) [
            'name' => $this->type() . '_' . $this->category(),
            'params' => [
                'course' => $this->parent()->slug(),
                'module' => $this->slug(),
            ],
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
    public function course(): AcademyCourseInterface
    {
        $course = $this->parent();

        assert($course instanceof AcademyCourseInterface);

        return $course;
    }

    /**
     * {@inheritDoc}
     */
    public function lessons(): array
    {
        $lessons = [];

        foreach ($this->children() as $child) {
            assert($child instanceof AcademyLessonInterface);
            $lessons[$child->slug()] = $child;
        }

        return $lessons;
    }

    /**
     * {@inheritDoc}
     */
    public function time(): int
    {
        if (!isset($this->time)) {
            $this->time = 0;
            foreach ($this->lessons() as $lesson) {
                $this->time += $lesson->time();
            }
        }

        return $this->time;
    }
}
