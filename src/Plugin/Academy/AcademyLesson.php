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
use Derafu\Content\Contract\ContentAttachmentInterface;
use Derafu\Content\Plugin\Academy\Contract\AcademyLessonInterface;
use Derafu\Content\Plugin\Academy\Contract\AcademyModuleInterface;

/**
 * Class that represents an academy lesson.
 */
class AcademyLesson extends AbstractContentItem implements AcademyLessonInterface
{
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
        return 'lesson';
    }

    /**
     * {@inheritDoc}
     */
    public function route(): object
    {
        return (object) [
            'name' => $this->type() . '_' . $this->category(),
            'params' => [
                'course' => $this->parent()->parent()->slug(),
                'module' => $this->parent()->slug(),
                'lesson' => $this->slug(),
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
    public function module(): AcademyModuleInterface
    {
        $module = $this->parent();

        assert($module instanceof AcademyModuleInterface);

        return $module;
    }

    /**
     * {@inheritDoc}
     */
    public function test(): string|ContentAttachmentInterface|null
    {
        $test = $this->metadata('test');

        if ($test === null) {
            return null;
        }

        if (str_contains($test, '?attachment=')) {
            $attachment = explode('?attachment=', $test)[1];

            return $this->attachment($attachment);
        }

        return $test;
    }

    /**
     * {@inheritDoc}
     */
    public function icon(): string
    {
        if ($this->video()) {
            return 'fa-solid fa-play fa-fw';
        } elseif ($this->test()) {
            return 'fa-solid fa-question-circle fa-fw';
        } else {
            return 'fa-solid fa-file-lines fa-fw';
        }
    }
}
