<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Controller;

use Derafu\Content\Contract\AcademyRegistryInterface;
use Derafu\Content\ValueObject\ContentTag;
use Derafu\Http\Request;
use Derafu\Renderer\Contract\RendererInterface;
use InvalidArgumentException;

/**
 * Academy controller.
 */
class AcademyController
{
    /**
     * Constructor.
     *
     * @param AcademyRegistryInterface $academyRegistry Academy registry.
     * @param RendererInterface $renderer Renderer.
     */
    public function __construct(
        private readonly AcademyRegistryInterface $academyRegistry,
        private readonly RendererInterface $renderer
    ) {
    }

    /**
     * Index action.
     *
     * @return string
     */
    public function index(): string
    {
        return $this->renderer->render('academy/index.html.twig', [
            'courses' => $this->academyRegistry->all(),
            'tags' => $this->academyRegistry->tags(),
        ]);
    }

    /**
     * Show course action.
     *
     * @param string $course Course.
     * @return string
     */
    public function course(string $course): string
    {
        return $this->renderer->render('academy/course.html.twig', [
            'course' => $this->academyRegistry->get($course),
        ]);
    }

    /**
     * Show lesson action.
     *
     * @param string $course Course.
     * @param string $module Module.
     * @param string $lesson Lesson.
     * @return string
     */
    public function lesson(string $course, string $module, string $lesson): string
    {
        $course = $this->academyRegistry->get($course);
        $module = $course->modules()[$module] ?? throw new InvalidArgumentException('Module not found.');
        $lesson = $module->lessons()[$lesson] ?? throw new InvalidArgumentException('Lesson not found.');

        return $this->renderer->render('academy/lesson.html.twig', [
            'course' => $course,
            'module' => $module,
            'lesson' => $lesson,
        ]);
    }

    /**
     * Tag action.
     *
     * @param Request $request Request.
     * @param string $tag Tag.
     * @return string
     */
    public function tag(Request $request, string $tag): string
    {
        $filters = array_filter($request->all(), fn ($value) => $value !== '');
        $tags = $this->academyRegistry->tags();
        $contentTag = $tags[$tag] ?? new ContentTag($tag);
        $filters['tag'] = $contentTag->slug();
        $coursesFiltered = $this->academyRegistry->filter($filters);
        $courses = $this->academyRegistry->all();

        return $this->renderer->render('academy/tag.html.twig', [
            'filters' => $filters,
            'coursesFiltered' => $coursesFiltered,
            'courses' => $courses,
            'tags' => $tags,
            'tag' => $contentTag,
        ]);
    }
}
