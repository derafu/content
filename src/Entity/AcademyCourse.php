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
use Derafu\Content\Contract\AcademyModuleInterface;

/**
 * Class that represents an academy course.
 */
class AcademyCourse extends ContentItem implements AcademyCourseInterface
{
    /**
     * {@inheritDoc}
     */
    public function links(): array
    {
        if (!isset($this->links)) {
            $urlBasePath = '/academy';

            $this->links = [
                'self' => ['href' => $urlBasePath . '/' . $this->slug()],
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
}
