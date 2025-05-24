<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Contract;

/**
 * Academy registry interface.
 */
interface AcademyRegistryInterface extends ContentRegistryInterface
{
    /**
     * Get academy courses filtered by criteria.
     *
     * @param array<string, mixed> $filters Filter criteria.
     * @return array<AcademyCourseInterface>
     */
    public function filter(array $filters = []): array;

    /**
     * Get an academy course by URI.
     *
     * @param string $uri URI of the academy course.
     * @return AcademyCourseInterface
     */
    public function get(string $uri): AcademyCourseInterface;

    /**
     * Get the previous academy content relative to the given URI.
     *
     * @param string $uri URI of the academy content.
     * @param array<string, mixed> $filters Filter criteria.
     * @return AcademyModuleInterface|AcademyLessonInterface|null
     */
    public function previous(string $uri, array $filters = []): AcademyModuleInterface|AcademyLessonInterface|null;

    /**
     * Get the next academy content relative to the given URI.
     *
     * @param string $uri URI of the academy content.
     * @param array<string, mixed> $filters Filter criteria.
     * @return AcademyModuleInterface|AcademyLessonInterface|null
     */
    public function next(string $uri, array $filters = []): AcademyModuleInterface|AcademyLessonInterface|null;
}
