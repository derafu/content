<?php

declare(strict_types=1);

/**
 * Derafu: Content - Content Management Library.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent\Translation;

use Derafu\Content\ContentLoader;
use Derafu\Content\Plugin\Academy\AcademyController;
use Derafu\Content\Plugin\Academy\AcademyPlugin;
use Derafu\Content\Plugin\Blog\BlogController;
use Derafu\Content\Plugin\Blog\BlogPlugin;
use Derafu\Content\Plugin\Docs\DocsPlugin;
use Derafu\Content\Translation\ContentTranslationResourceProvider;
use Derafu\Http\Request;
use Derafu\Renderer\Factory\RendererFactory;
use Derafu\Routing\ValueObject\RequestContext;
use Derafu\TestsContent\Support\ContentFixtures;
use Derafu\TestsContent\Support\RendererFixture;
use Derafu\TestsContent\Support\RouterFixture;
use Derafu\Translation\TranslatorFactory;
use Derafu\Twig\Extension\RoutingExtension;
use Derafu\Twig\Extension\TranslationExtension;
use Derafu\Twig\Extension\TwigExtension;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The texts that the templates write are in English, as they are written, and
 * they are translated when the translation extension has a translator.
 *
 * These are texts that were written in the templates without going through the
 * translation: the label of the breadcrumb, the size of a file, and the notice
 * of a deprecated content in the PDF.
 */
#[CoversNothing]
final class TemplateTranslationsTest extends TestCase
{
    private function translator(): TranslatorInterface
    {
        return TranslatorFactory::create('es', [], [new ContentTranslationResourceProvider()]);
    }

    private function blogIndex(?TranslatorInterface $translator): string
    {
        $plugin = new BlogPlugin(ContentFixtures::contentContext(), ['path' => 'blog']);
        $plugin->loadContent(new ContentLoader(ContentFixtures::contentPath()));

        $router = RouterFixture::create();
        $router->setContext(new RequestContext(pathInfo: '/blog'));

        return (new BlogController(
            ContentFixtures::contentService(['blog' => $plugin]),
            RendererFixture::create($router, $translator),
            $router
        ))->index(new Request('GET', 'http://localhost/blog'));
    }

    private function academyCourse(?TranslatorInterface $translator): string
    {
        $plugin = new AcademyPlugin(ContentFixtures::contentContext(), ['path' => 'academy']);
        $plugin->loadContent(new ContentLoader(ContentFixtures::contentPath()));

        $router = RouterFixture::create();
        $router->setContext(new RequestContext(pathInfo: '/academy/curso-demo'));

        $html = (new AcademyController(
            ContentFixtures::contentService(['academy' => $plugin]),
            RendererFixture::create($router, $translator),
            $router
        ))->course(new Request('GET', 'http://localhost/academy/curso-demo'), 'curso-demo');

        $this->assertIsString($html);

        return $html;
    }

    /**
     * The notice of a deprecated content, in the two places of the PDF that
     * write it by themselves: the macro of a section and the layout.
     *
     * @return array{string, string} The notice of the macro and of the layout.
     */
    private function deprecatedNotices(?TranslatorInterface $translator): array
    {
        $plugin = new DocsPlugin(ContentFixtures::contentContext(), ['path' => 'docs']);
        $plugin->loadContent(new ContentLoader(ContentFixtures::contentPath()));
        $doc = $plugin->registry()->get('pasado-deprecado');

        $router = RouterFixture::create();
        $router->setContext(new RequestContext(pathInfo: '/docs/pasado-deprecado.pdf'));

        $twig = RendererFactory::createTwigService([
            'paths' => [ContentFixtures::templatesPath(), dirname(__DIR__, 3) . '/resources/templates'],
            'extensions' => [
                new TwigExtension(),
                new TranslationExtension($translator, null, $translator !== null ? 'es' : null),
                new RoutingExtension($router),
            ],
        ]);

        $data = ['plugin' => $plugin, 'doc' => $doc, 'full' => false, 'item' => $doc];
        $section = $twig->renderFromString("{% import 'pdf/_macros.pdf.twig' as pdf %}{{ pdf.section(item, 'Docs', 0, {}) }}", $data);
        $layout = $twig->render('docs/show.pdf.twig', $data);

        $notices = [];
        foreach ([$section, $layout] as $html) {
            $this->assertSame(1, preg_match('#<div class="pdf-warning">(.*?)</div>#s', $html, $match));
            $notices[] = trim(html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5));
        }

        return $notices;
    }

    public function testTheLabelOfTheBreadcrumbIsInEnglishAndItIsTranslated(): void
    {
        $this->assertStringContainsString('aria-label="breadcrumb"', $this->blogIndex(null));
        $this->assertStringContainsString('aria-label="migas de pan"', $this->blogIndex($this->translator()));
    }

    public function testTheSizeOfAFileIsTheSameWithAndWithoutTranslation(): void
    {
        $pattern = '#<td>[0-9]+(\.[0-9]+)? KB</td>#';

        $this->assertSame(1, preg_match($pattern, $this->academyCourse(null), $english));
        $this->assertSame(1, preg_match($pattern, $this->academyCourse($this->translator()), $spanish));
        $this->assertSame($english[0], $spanish[0]);
    }

    public function testTheNoticeOfADeprecatedContentInThePdfIsInEnglishAndItIsTranslated(): void
    {
        $this->assertSame(
            array_fill(0, 2, "This content is deprecated since 01/01/2020. It's not recommended to use this content anymore."),
            $this->deprecatedNotices(null)
        );
        $this->assertSame(
            array_fill(0, 2, 'Este contenido está obsoleto desde 01/01/2020. No se recomienda seguir usando este contenido.'),
            $this->deprecatedNotices($this->translator())
        );
    }
}
