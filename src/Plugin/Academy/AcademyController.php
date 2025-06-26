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

use Derafu\Content\ContentTag;
use Derafu\Content\Contract\ContentServiceInterface;
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
     * @param ContentServiceInterface $contentService Content service.
     * @param RendererInterface $renderer Renderer.
     */
    public function __construct(
        private readonly ContentServiceInterface $contentService,
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
        $plugin = $this->contentService->plugin('academy');
        assert($plugin instanceof AcademyPlugin);

        return $this->renderer->render('academy/index.html.twig', [
            'plugin' => $plugin,
            'courses' => $plugin->registry()->all(),
            'tags' => $plugin->registry()->tags(),
        ]);
    }

    /**
     * Show course action.
     *
     * @param Request $request Request.
     * @param string $course Course.
     * @return string|array
     */
    public function course(Request $request, string $course): string|array
    {
        $plugin = $this->contentService->plugin('academy');
        assert($plugin instanceof AcademyPlugin);

        $preferredFormat = $request->getPreferredFormat();
        $course = str_replace('.' . $preferredFormat, '', $course);

        $course = $plugin->registry()->get($course);

        if ($preferredFormat === 'json') {
            return [
                'data' => $course->toArray(),
            ];
        } elseif ($preferredFormat === 'pdf') {
            return $this->renderer->render('academy/course.pdf.twig', [
                'plugin' => $plugin,
                'course' => $course,
            ]);
        } else {
            return $this->renderer->render('academy/course.html.twig', [
                'plugin' => $plugin,
                'course' => $course,
            ]);
        }
    }

    /**
     * Show module action.
     *
     * @param Request $request Request.
     * @param string $course Course.
     * @param string $module Module.
     * @return string|array
     */
    public function module(
        Request $request,
        string $course,
        string $module
    ): string|array {
        $plugin = $this->contentService->plugin('academy');
        assert($plugin instanceof AcademyPlugin);

        $preferredFormat = $request->getPreferredFormat();
        $module = str_replace('.' . $preferredFormat, '', $module);

        $course = $plugin->registry()->get($course);
        $module = $course->modules()[$module]
            ?? throw new InvalidArgumentException(sprintf(
                'Module "%s" not found.',
                $module
            ))
        ;

        if ($preferredFormat === 'json') {
            return [
                'data' => $module->toArray(),
            ];
        } elseif ($preferredFormat === 'pdf') {
            return $this->renderer->render('academy/module.pdf.twig', [
                'plugin' => $plugin,
                'course' => $course,
                'module' => $module,
            ]);
        } else {
            return $this->renderer->render('academy/module.html.twig', [
                'plugin' => $plugin,
                'course' => $course,
                'module' => $module,
                'previous' => $plugin->registry()->previous(
                    $module->uri(),
                    ['category' => 'module'],
                ),
                'next' => $plugin->registry()->next(
                    $module->uri(),
                    ['category' => 'module'],
                ),
            ]);
        }
    }

    /**
     * Show lesson action.
     *
     * @param Request $request Request.
     * @param string $course Course.
     * @param string $module Module.
     * @param string $lesson Lesson.
     * @return string|array
     */
    public function lesson(
        Request $request,
        string $course,
        string $module,
        string $lesson
    ): string|array {
        $plugin = $this->contentService->plugin('academy');
        assert($plugin instanceof AcademyPlugin);

        $preferredFormat = $request->getPreferredFormat();
        $lesson = str_replace('.' . $preferredFormat, '', $lesson);

        $course = $plugin->registry()->get($course);
        $module = $course->modules()[$module]
            ?? throw new InvalidArgumentException(sprintf(
                'Module "%s" not found.',
                $module
            ))
        ;
        $lesson = $module->lessons()[$lesson]
            ?? throw new InvalidArgumentException(sprintf(
                'Lesson "%s" not found.',
                $lesson
            ))
        ;

        if ($preferredFormat === 'json') {
            return [
                'data' => $lesson->toArray(),
            ];
        } elseif ($preferredFormat === 'pdf') {
            return $this->renderer->render('academy/lesson.pdf.twig', [
                'plugin' => $plugin,
                'course' => $course,
                'module' => $module,
                'lesson' => $lesson,
            ]);
        } else {
            return $this->renderer->render('academy/lesson.html.twig', [
                'plugin' => $plugin,
                'course' => $course,
                'module' => $module,
                'lesson' => $lesson,
                'previous' => $plugin->registry()->previous(
                    $lesson->uri(),
                    ['category' => 'lesson'],
                ),
                'next' => $plugin->registry()->next(
                    $lesson->uri(),
                    ['category' => 'lesson'],
                ),
            ]);
        }
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
        $plugin = $this->contentService->plugin('academy');
        assert($plugin instanceof AcademyPlugin);

        $filters = array_filter($request->all(), fn ($value) => $value !== '');
        $tags = $plugin->registry()->tags();
        $contentTag = $tags[$tag] ?? new ContentTag($tag);
        $filters['tag'] = $contentTag->slug();
        $coursesFiltered = $plugin->registry()->filter($filters);
        $courses = $plugin->registry()->all();

        return $this->renderer->render('academy/tag.html.twig', [
            'plugin' => $plugin,
            'filters' => $filters,
            'coursesFiltered' => $coursesFiltered,
            'courses' => $courses,
            'tags' => $tags,
            'tag' => $contentTag,
        ]);
    }
}
