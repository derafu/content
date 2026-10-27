<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent\Support;

use Derafu\Twig\Contract\TemplatePathProviderInterface;

final class FixtureTemplatePathProvider implements TemplatePathProviderInterface
{
    public function getPaths(): array
    {
        return [ContentFixtures::templatesPath()];
    }
}
