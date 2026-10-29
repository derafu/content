<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Provider;

use Derafu\Twig\Contract\TemplatePathProviderInterface;

/**
 * Contributes Content's fallback templates to the shared Twig environment.
 *
 * Consumers can supply higher-priority template roots through the same tag.
 */
final class ContentTemplatePathProvider implements TemplatePathProviderInterface
{
    /**
     * {@inheritDoc}
     */
    public function getPaths(): array
    {
        return [dirname(__DIR__, 2) . '/resources/templates'];
    }
}
