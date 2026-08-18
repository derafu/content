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
use Derafu\Content\Plugin\Academy\Contract\AcademyTestInterface;

/**
 * Class that represents an academy lesson.
 */
class AcademyLesson extends AbstractContentItem implements AcademyLessonInterface
{
    /**
     * Self-assessment test ("quiz") of the lesson, parsed from its
     * attachment. False is used (never null) as the "not resolved yet"
     * memoization sentinel, since a nullable typed property set to null
     * would make isset() (used to detect "already resolved") return
     * false again on every call.
     *
     * @var AcademyTestInterface|false
     */
    private AcademyTestInterface|false $test;

    /**
     * Raw attachment backing the lesson's test. Same false-as-sentinel
     * reasoning as $test above.
     *
     * @var ContentAttachmentInterface|false
     */
    private ContentAttachmentInterface|false $testAttachment;

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
    public function test(): ?AcademyTestInterface
    {
        if (!isset($this->test)) {
            $attachment = $this->testAttachment();

            $this->test = $attachment
                ? AcademyTest::fromJson($attachment->raw())
                : false;
        }

        return $this->test ?: null;
    }

    /**
     * {@inheritDoc}
     */
    public function testAttachment(): ?ContentAttachmentInterface
    {
        if (!isset($this->testAttachment)) {
            $this->testAttachment = $this->resolveTestAttachment() ?? false;
        }

        return $this->testAttachment ?: null;
    }

    /**
     * Resolves the "test" metadata to a local attachment, supporting both
     * the "?attachment=name" convention and a literal path ending in
     * "/_attachments/name" (used directly in real content), since both
     * point to the same attachment file by its basename.
     *
     * @return ContentAttachmentInterface|null
     */
    private function resolveTestAttachment(): ?ContentAttachmentInterface
    {
        $test = $this->metadata('test');

        if ($test === null) {
            return null;
        }

        if (str_contains($test, '?attachment=')) {
            return $this->attachment(explode('?attachment=', $test)[1]);
        }

        if (str_contains($test, '/_attachments/')) {
            return $this->attachment(basename($test));
        }

        return null;
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
