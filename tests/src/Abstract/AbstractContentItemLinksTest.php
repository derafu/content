<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent\Abstract;

use Derafu\Content\Abstract\AbstractContentItem;
use Derafu\Content\Abstract\AbstractContentRegistry;
use Derafu\Content\ContentBag;
use Derafu\Content\ContentConfig;
use Derafu\Content\ContentContext;
use Derafu\Content\ContentLoader;
use Derafu\Content\ContentSplFileInfo;
use Derafu\Content\ContentTag;
use Derafu\Content\Contract\ContentItemInterface;
use Derafu\Content\Plugin\Academy\AcademyCourse;
use Derafu\Content\Plugin\Academy\AcademyLesson;
use Derafu\Content\Plugin\Academy\AcademyModule;
use Derafu\Content\Plugin\Academy\AcademyPlugin;
use Derafu\Content\Plugin\Academy\AcademyRegistry;
use Derafu\Content\Plugin\Blog\BlogPlugin;
use Derafu\Content\Plugin\Blog\BlogPost;
use Derafu\Content\Plugin\Blog\BlogRegistry;
use Derafu\Content\Plugin\Docs\DocsDoc;
use Derafu\Content\Plugin\Docs\DocsPlugin;
use Derafu\Content\Plugin\Docs\DocsRegistry;
use Derafu\Content\Plugin\Faq\FaqPlugin;
use Derafu\Content\Plugin\Faq\FaqQuestion;
use Derafu\Content\Plugin\Faq\FaqRegistry;
use Derafu\Content\Plugin\Pages\PagesPage;
use Derafu\Content\Plugin\Pages\PagesPlugin;
use Derafu\Content\Plugin\Pages\PagesRegistry;
use Derafu\Routing\Contract\RouterInterface;
use Derafu\TestsContent\Support\ContentFixtures;
use Derafu\TestsContent\Support\RouterFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * The `_links` of an item come from the router, not from paths written in the
 * item: the paths of the routes are defined once, in the routes.
 *
 * The expected values of the first tests are the ones the items delivered
 * before the router was used, so they also show nothing changed for a reader
 * of the JSON.
 */
#[CoversClass(AbstractContentItem::class)]
#[UsesClass(AbstractContentRegistry::class)]
#[UsesClass(ContentBag::class)]
#[UsesClass(ContentConfig::class)]
#[UsesClass(ContentContext::class)]
#[UsesClass(ContentLoader::class)]
#[UsesClass(ContentSplFileInfo::class)]
#[UsesClass(ContentTag::class)]
#[UsesClass(AcademyPlugin::class)]
#[UsesClass(AcademyRegistry::class)]
#[UsesClass(AcademyCourse::class)]
#[UsesClass(AcademyModule::class)]
#[UsesClass(AcademyLesson::class)]
#[UsesClass(BlogPlugin::class)]
#[UsesClass(BlogRegistry::class)]
#[UsesClass(BlogPost::class)]
#[UsesClass(DocsPlugin::class)]
#[UsesClass(DocsRegistry::class)]
#[UsesClass(DocsDoc::class)]
#[UsesClass(FaqPlugin::class)]
#[UsesClass(FaqRegistry::class)]
#[UsesClass(FaqQuestion::class)]
#[UsesClass(PagesPlugin::class)]
#[UsesClass(PagesRegistry::class)]
#[UsesClass(PagesPage::class)]
final class AbstractContentItemLinksTest extends TestCase
{
    private function item(string $plugin, string $uri): ContentItemInterface
    {
        $class = [
            'academy' => AcademyPlugin::class,
            'blog' => BlogPlugin::class,
            'docs' => DocsPlugin::class,
            'faq' => FaqPlugin::class,
            'pages' => PagesPlugin::class,
        ][$plugin];

        $instance = new $class(ContentFixtures::contentContext(), ['path' => $plugin]);
        $instance->loadContent(new ContentLoader(ContentFixtures::contentPath()));

        return $instance->registry()->get($uri);
    }

    private function course(): AcademyCourse
    {
        $course = $this->item('academy', 'curso-demo');
        assert($course instanceof AcademyCourse);

        return $course;
    }

    private function module(): AcademyModule
    {
        $module = $this->course()->modules()['modulo-uno'];
        assert($module instanceof AcademyModule);

        return $module;
    }

    private function lesson(): AcademyLesson
    {
        $lesson = $this->module()->lessons()['leccion-uno'];
        assert($lesson instanceof AcademyLesson);

        return $lesson;
    }

    /**
     * Router whose paths are NOT the ones of this package: if an item wrote
     * its own paths, it would not follow it.
     */
    private function routerWithOtherPaths(): RouterInterface
    {
        $handler = 'App\\Controller\\AnyController::index';

        return RouterFixture::create([
            'docs' => ['path' => '/documentos', 'handler' => $handler],
            'docs_doc' => ['path' => '/documentos/{doc:.+}', 'handler' => $handler],
            'faq' => ['path' => '/preguntas', 'handler' => $handler],
            'faq_question' => ['path' => '/preguntas/{question:.+}', 'handler' => $handler],
            'blog' => ['path' => '/articulos', 'handler' => $handler],
            'blog_post' => ['path' => '/articulos/{post:.+}', 'handler' => $handler],
            'academy' => ['path' => '/cursos', 'handler' => $handler],
            'academy_course' => ['path' => '/cursos/{course}', 'handler' => $handler],
            'academy_module' => ['path' => '/cursos/{course}/{module}', 'handler' => $handler],
            'academy_lesson' => ['path' => '/cursos/{course}/{module}/{lesson}', 'handler' => $handler],
        ], includeContentRoutes: false);
    }

    /**
     * @return array<string, array{callable(self): ContentItemInterface, array<string, array<string, string>>}>
     */
    public static function itemsProvider(): array
    {
        return [
            'doc' => [
                fn (self $t) => $t->item('docs', 'guia'),
                ['self' => ['href' => '/docs/guia'], 'collection' => ['href' => '/docs']],
            ],
            'nested doc' => [
                fn (self $t) => $t->item('docs', 'guia/primeros-pasos'),
                ['self' => ['href' => '/docs/guia/primeros-pasos'], 'collection' => ['href' => '/docs']],
            ],
            'faq question' => [
                fn (self $t) => $t->item('faq', 'pregunta-uno'),
                ['self' => ['href' => '/faq/pregunta-uno'], 'collection' => ['href' => '/faq']],
            ],
            'blog post' => [
                fn (self $t) => $t->item('blog', '2026-01-15-primer-post'),
                ['self' => ['href' => '/blog/2026-01-15-primer-post'], 'collection' => ['href' => '/blog']],
            ],
            'academy course' => [
                fn (self $t) => $t->course(),
                ['self' => ['href' => '/academy/curso-demo'], 'collection' => ['href' => '/academy']],
            ],
            'academy module' => [
                fn (self $t) => $t->module(),
                ['self' => ['href' => '/academy/curso-demo/modulo-uno'], 'collection' => ['href' => '/academy']],
            ],
            'academy lesson' => [
                fn (self $t) => $t->lesson(),
                ['self' => ['href' => '/academy/curso-demo/modulo-uno/leccion-uno'], 'collection' => ['href' => '/academy']],
            ],
        ];
    }

    /**
     * @param callable(self): ContentItemInterface $item
     * @param array<string, array<string, string>> $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('itemsProvider')]
    public function testTheLinksAreTheSameThatWereAlwaysDelivered(callable $item, array $expected): void
    {
        $this->assertSame($expected, $item($this)->links(RouterFixture::create()));
    }

    /**
     * @return array<string, array{callable(self): ContentItemInterface, array<string, array<string, string>>}>
     */
    public static function itemsWithOtherPathsProvider(): array
    {
        return [
            'doc' => [
                fn (self $t) => $t->item('docs', 'guia/primeros-pasos'),
                ['self' => ['href' => '/documentos/guia/primeros-pasos'], 'collection' => ['href' => '/documentos']],
            ],
            'faq question' => [
                fn (self $t) => $t->item('faq', 'pregunta-uno'),
                ['self' => ['href' => '/preguntas/pregunta-uno'], 'collection' => ['href' => '/preguntas']],
            ],
            'blog post' => [
                fn (self $t) => $t->item('blog', '2026-01-15-primer-post'),
                ['self' => ['href' => '/articulos/2026-01-15-primer-post'], 'collection' => ['href' => '/articulos']],
            ],
            'academy course' => [
                fn (self $t) => $t->course(),
                ['self' => ['href' => '/cursos/curso-demo'], 'collection' => ['href' => '/cursos']],
            ],
            'academy module' => [
                fn (self $t) => $t->module(),
                ['self' => ['href' => '/cursos/curso-demo/modulo-uno'], 'collection' => ['href' => '/cursos']],
            ],
            'academy lesson' => [
                fn (self $t) => $t->lesson(),
                ['self' => ['href' => '/cursos/curso-demo/modulo-uno/leccion-uno'], 'collection' => ['href' => '/cursos']],
            ],
        ];
    }

    /**
     * @param callable(self): ContentItemInterface $item
     * @param array<string, array<string, string>> $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('itemsWithOtherPathsProvider')]
    public function testTheLinksFollowThePathsOfTheRouter(callable $item, array $expected): void
    {
        $this->assertSame($expected, $item($this)->links($this->routerWithOtherPaths()));
    }

    /**
     * The pages of a website are served from the root by the file system
     * parser, which gives them no name: the router can not build their URL, so
     * their links keep being the path of the page itself.
     */
    public function testThePagesKeepTheirLinksWhateverTheRouter(): void
    {
        $expected = ['self' => ['href' => '/about'], 'collection' => ['href' => '']];

        $page = $this->item('pages', 'about');

        $this->assertSame($expected, $page->links(RouterFixture::create()));
        $this->assertSame($expected, $page->links($this->routerWithOtherPaths()));
    }
}
