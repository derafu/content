<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Plugin\Mcp\Tool;

use Derafu\Content\Contract\ContentItemInterface;
use Derafu\Content\Contract\ContentServiceInterface;
use Derafu\Content\Exception\ContentNotFoundException;
use Derafu\Content\Plugin\Academy\Contract\AcademyLessonInterface;
use Derafu\Content\Plugin\Academy\Contract\AcademyModuleInterface;
use Derafu\Renderer\Contract\RendererInterface;
use Derafu\Routing\Contract\RouterInterface;
use Derafu\Routing\Enum\UrlReferenceType;
use Mcp\Capability\Attribute\Schema;
use Mcp\Exception\ToolCallException;
use RuntimeException;

/**
 * MCP tool that fetches a single content item, with its full Markdown body
 * and metadata. This is the canonical way for an agent to read a specific
 * doc/post/question/lesson, and it returns the exact same Markdown body the
 * website serves for the ".md" format of the item.
 */
class GetContentTool
{
    /**
     * Constructor.
     *
     * @param ContentServiceInterface $contentService Content service.
     * @param RouterInterface $router Router.
     * @param RendererInterface $renderer Renderer.
     */
    public function __construct(
        private readonly ContentServiceInterface $contentService,
        private readonly RouterInterface $router,
        private readonly RendererInterface $renderer
    ) {
    }

    /**
     * Fetch a single content item.
     *
     * @param string $source Content source of the item.
     * @param string $uri URI of the item within its source (e.g. "introduccion/que-es-libredte").
     * @return array<string, mixed>
     */
    public function __invoke(
        #[Schema(enum: ['academy', 'blog', 'docs', 'faq', 'pages'])]
        string $source,
        string $uri
    ): array {
        $plugin = ContentSourceResolver::resolve($this->contentService, $source);

        try {
            $item = $plugin->registry()->get($uri);
        } catch (ContentNotFoundException $e) {
            throw new ToolCallException(sprintf(
                'Content "%s" was not found in source "%s".',
                $uri,
                $source
            ), previous: $e);
        }

        $route = $item->route();

        return [
            'id' => $item->id(),
            'source' => $item->type(),
            'category' => $item->category(),
            'uri' => $item->uri(),
            'title' => $item->title(),
            'description' => $item->description(),
            'tags' => array_map(fn ($tag) => $tag->name(), $item->tags()),
            'authors' => array_map(fn ($author) => $author->name(), $item->authors()),
            'date' => $item->date()->format('Y-m-d'),
            'last_update' => $item->last_update()->format('Y-m-d'),
            'time_minutes' => $item->time(),
            'url' => $this->router->generate(
                $route->name,
                $route->params,
                UrlReferenceType::ABSOLUTE_URL
            ),
            'markdown' => $this->renderMarkdown($plugin, $item),
        ];
    }

    /**
     * Renders the exact same Markdown the ".md" format of this item
     * would return (video/test/openapi and everything else its own
     * "*.md.twig" template adds), instead of its raw, unprocessed body:
     * without this, an item whose real content lives in a "special"
     * frontmatter field (a lesson's "test", a doc's "openapi", a video)
     * would return next to nothing here.
     *
     * @param object $plugin The content plugin the item came from.
     * @param ContentItemInterface $item Content item.
     * @return string
     */
    private function renderMarkdown(object $plugin, ContentItemInterface $item): string
    {
        return match ($item->category()) {
            'doc' => $this->renderer->render('docs/show.md.twig', [
                'plugin' => $plugin,
                'doc' => $item,
                'full' => false,
            ]),
            'question' => $this->renderer->render('faq/show.md.twig', [
                'plugin' => $plugin,
                'faq' => $item,
                'full' => false,
            ]),
            'post' => $this->renderer->render('blog/show.md.twig', [
                'plugin' => $plugin,
                'post' => $item,
            ]),
            'page' => $this->renderer->render('pages/show.md.twig', [
                'plugin' => $plugin,
                'page' => $item,
            ]),
            'course' => $this->renderer->render('academy/course.md.twig', [
                'plugin' => $plugin,
                'course' => $item,
                'full' => false,
            ]),
            'module' => $this->renderAcademyModuleMarkdown($plugin, $item),
            'lesson' => $this->renderAcademyLessonMarkdown($plugin, $item),
            default => throw new RuntimeException(sprintf(
                'No Markdown template known for content category "%s".',
                $item->category()
            )),
        };
    }

    /**
     * @param object $plugin The content plugin the item came from.
     * @param ContentItemInterface $item An academy module.
     * @return string
     */
    private function renderAcademyModuleMarkdown(object $plugin, ContentItemInterface $item): string
    {
        assert($item instanceof AcademyModuleInterface);

        return $this->renderer->render('academy/module.md.twig', [
            'plugin' => $plugin,
            'course' => $item->course(),
            'module' => $item,
            'full' => false,
        ]);
    }

    /**
     * @param object $plugin The content plugin the item came from.
     * @param ContentItemInterface $item An academy lesson.
     * @return string
     */
    private function renderAcademyLessonMarkdown(object $plugin, ContentItemInterface $item): string
    {
        assert($item instanceof AcademyLessonInterface);

        return $this->renderer->render('academy/lesson.md.twig', [
            'plugin' => $plugin,
            'course' => $item->module()->course(),
            'module' => $item->module(),
            'lesson' => $item,
        ]);
    }
}
