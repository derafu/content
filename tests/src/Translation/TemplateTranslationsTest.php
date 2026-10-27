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
use Derafu\Routing\ValueObject\RequestContext;
use Derafu\TestsContent\Support\ContentFixtures;
use Derafu\TestsContent\Support\RendererFixture;
use Derafu\TestsContent\Support\RouterFixture;
use Derafu\Translation\TranslatorFactory;
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
        $router->setContext(new RequestContext(pathInfo: '/academy/demo-course'));

        $html = (new AcademyController(
            ContentFixtures::contentService(['academy' => $plugin]),
            RendererFixture::create($router, $translator),
            $router
        ))->course(new Request('GET', 'http://localhost/academy/demo-course'), 'demo-course');

        $this->assertIsString($html);

        return $html;
    }

    private function academyLesson(?TranslatorInterface $translator): string
    {
        $plugin = new AcademyPlugin(ContentFixtures::contentContext(), ['path' => 'academy']);
        $plugin->loadContent(new ContentLoader(ContentFixtures::contentPath()));

        $router = RouterFixture::create();
        $router->setContext(new RequestContext(pathInfo: '/academy/demo-course/module-one/lesson-one'));

        $html = (new AcademyController(
            ContentFixtures::contentService(['academy' => $plugin]),
            RendererFixture::create($router, $translator),
            $router
        ))->lesson(
            new Request('GET', 'http://localhost/academy/demo-course/module-one/lesson-one'),
            'demo-course',
            'module-one',
            'lesson-one'
        );

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
        $doc = $plugin->registry()->get('past-deprecated');

        $router = RouterFixture::create();
        $router->setContext(new RequestContext(pathInfo: '/docs/past-deprecated.pdf'));

        $twig = RendererFixture::twigService($router, $translator, domain: null);

        $data = ['plugin' => $plugin, 'doc' => $doc, 'full' => false, 'item' => $doc];
        $section = $twig->renderFromString("{% import 'pdf/_macros.pdf.twig' as pdf %}{{ pdf.section(item, 'Docs', 0, {}) }}", $data);
        $layout = $twig->render('docs/show.pdf.twig', $data);

        $notices = [];
        foreach ([$section, $layout] as $html) {
            if (preg_match('#<div class="pdf-warning">(.*?)</div>#s', $html, $match) !== 1) {
                $this->fail('The PDF has no warning notice.');
            }
            $notices[] = trim(html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5));
        }

        return $notices;
    }

    public function testTheLabelOfTheBreadcrumbIsInEnglishAndItIsTranslated(): void
    {
        $this->assertStringContainsString('aria-label="breadcrumb"', $this->blogIndex(null));
        $this->assertStringContainsString('aria-label="migas de pan"', $this->blogIndex($this->translator()));
    }

    /**
     * The texts that the widget of the test reads from the data-messages
     * attribute of its container.
     *
     * @return array<string, string>
     */
    private function widgetMessages(string $html): array
    {
        if (preg_match('#<div id="academy-test-container"[^>]* data-messages="([^"]*)"#', $html, $match) !== 1) {
            $this->fail('The container of the test has no data-messages.');
        }

        $messages = json_decode(html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5), true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($messages);

        return array_map(fn (mixed $text): string => is_string($text) ? $text : '', $messages);
    }

    public function testTheTextsOfTheWidgetOfTheTestAreInEnglishAndTheyAreTranslated(): void
    {
        $this->assertSame([
            'loadError' => 'Error loading data. Please try again later.',
            'true' => 'True',
            'false' => 'False',
            'reviewAnswers' => 'Review answers',
            'correct' => 'Correct',
            'incorrect' => 'Incorrect',
            'result' => 'Result: {correct} / {total} ({percent}%)',
        ], $this->widgetMessages($this->academyLesson(null)));

        $this->assertSame([
            'loadError' => 'Error al cargar los datos. Inténtalo de nuevo más tarde.',
            'true' => 'Verdadero',
            'false' => 'Falso',
            'reviewAnswers' => 'Revisar respuestas',
            'correct' => 'Correcto',
            'incorrect' => 'Incorrecto',
            'result' => 'Resultado: {correct} / {total} ({percent}%)',
        ], $this->widgetMessages($this->academyLesson($this->translator())));
    }

    /**
     * The texts of the cards of the blog that the script reads from the
     * data-messages attribute of its container.
     *
     * @param array<string, mixed> $variables Variables of the template.
     * @return array{data-api: string, data-base-path: string, messages: array<string, string>}
     */
    private function blogCards(?TranslatorInterface $translator, array $variables = []): array
    {
        $router = RouterFixture::create();
        $html = RendererFixture::create($router, $translator)->render('blog/_cards.html.twig', $variables);

        $pattern = '#<div id="blog-posts" data-api="([^"]*)" data-base-path="([^"]*)" data-messages="([^"]*)"></div>#';
        if (preg_match($pattern, $html, $match) !== 1) {
            $this->fail('The container of the cards is not the expected one: ' . $html);
        }

        $messages = json_decode(html_entity_decode($match[3], ENT_QUOTES | ENT_HTML5), true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($messages);

        return [
            'data-api' => html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5),
            'data-base-path' => $match[2],
            'messages' => array_map(fn (mixed $text): string => is_string($text) ? $text : '', $messages),
        ];
    }

    public function testTheTextsOfTheCardsOfTheBlogAreInEnglishAndTheyAreTranslated(): void
    {
        $english = $this->blogCards(null);
        $spanish = $this->blogCards($this->translator());

        $this->assertSame([
            'loadError' => 'Error loading data. Please try again later.',
            'readMore' => 'Read more',
            'byline' => 'By {authors} on {date} • {time} min read',
        ], $english['messages']);
        $this->assertSame([
            'loadError' => 'Error al cargar los datos. Inténtalo de nuevo más tarde.',
            'readMore' => 'Leer más',
            'byline' => 'Por {authors} el {date} • {time} min de lectura',
        ], $spanish['messages']);
    }

    public function testTheContainerOfTheCardsHasTheApiAndTheBasePath(): void
    {
        $default = $this->blogCards(null);
        $custom = $this->blogCards(null, ['limit' => 8, 'base_path' => '/en']);

        $this->assertSame('/api/content.json?type=blog&limit=4', $default['data-api']);
        $this->assertSame('', $default['data-base-path']);
        $this->assertSame('/api/content.json?type=blog&limit=8', $custom['data-api']);
        $this->assertSame('/en', $custom['data-base-path']);
    }

    public function testAMessageWithPlaceholdersAndWithoutParametersKeepsThemAsTheyAre(): void
    {
        $translator = $this->translator();

        $this->assertSame(
            'Resultado: {correct} / {total} ({percent}%)',
            $translator->trans('Result: {correct} / {total} ({percent}%)', [], 'content+intl-icu')
        );
        $this->assertSame(
            'Por {authors} el {date} • {time} min de lectura',
            $translator->trans('By {authors} on {date} • {time} min read', [], 'content+intl-icu')
        );
    }

    public function testTheSizeOfAFileIsTheSameWithAndWithoutTranslation(): void
    {
        $pattern = '#<td>[0-9]+(\.[0-9]+)? KB</td>#';

        if (preg_match($pattern, $this->academyCourse(null), $english) !== 1) {
            $this->fail('The course without translation has no file size.');
        }
        if (preg_match($pattern, $this->academyCourse($this->translator()), $spanish) !== 1) {
            $this->fail('The translated course has no file size.');
        }
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
