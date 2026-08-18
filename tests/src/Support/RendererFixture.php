<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent\Support;

use Derafu\Renderer\Contract\RendererInterface;
use Derafu\Renderer\Factory\RendererFactory;
use Derafu\Routing\Contract\RouterInterface;
use Derafu\Twig\Extension\RoutingExtension;
use Derafu\Twig\Extension\TwigExtension;

/**
 * Builds a real Renderer (real Twig environment, real Markdown engine),
 * pointed at both this package's own templates and the test fixture
 * layout, so controller tests render actual HTML instead of skipping the
 * rendering step or mocking RendererInterface.
 */
final class RendererFixture
{
    /**
     * Build a real renderer able to render this package's own templates
     * (docs/, faq/, pages/, etc.) on top of the fixture "layouts/default"
     * used to stand in for a website's own base layout.
     *
     * @param RouterInterface|null $router Real router to back the
     * "path"/"url"/"is_active_path" Twig functions used by the sidebar
     * templates (docs/faq). Only needed to render templates that call them.
     * @return RendererInterface
     */
    public static function create(?RouterInterface $router = null): RendererInterface
    {
        $extensions = [new TwigExtension()];

        if ($router !== null) {
            $extensions[] = new RoutingExtension($router);
        }

        return RendererFactory::create([
            'paths' => [
                ContentFixtures::templatesPath(),
                dirname(__DIR__, 3) . '/resources/templates',
            ],
            'engines' => ['twig', 'markdown', 'pdf'],
            'extensions' => $extensions,
        ]);
    }
}
