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
        $course = $this->item('academy', 'demo-course');
        assert($course instanceof AcademyCourse);

        return $course;
    }

    private function module(): AcademyModule
    {
        $module = $this->course()->modules()['module-one'];
        assert($module instanceof AcademyModule);

        return $module;
    }

    private function lesson(): AcademyLesson
    {
        $lesson = $this->module()->lessons()['lesson-one'];
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
            'docs' => ['path' => '/documents', 'handler' => $handler],
            'docs_doc' => ['path' => '/documents/{doc:.+}', 'handler' => $handler],
            'faq' => ['path' => '/questions', 'handler' => $handler],
            'faq_question' => ['path' => '/questions/{question:.+}', 'handler' => $handler],
            'blog' => ['path' => '/articles', 'handler' => $handler],
            'blog_post' => ['path' => '/articles/{post:.+}', 'handler' => $handler],
            'academy' => ['path' => '/courses', 'handler' => $handler],
            'academy_course' => ['path' => '/courses/{course}', 'handler' => $handler],
            'academy_module' => ['path' => '/courses/{course}/{module}', 'handler' => $handler],
            'academy_lesson' => ['path' => '/courses/{course}/{module}/{lesson}', 'handler' => $handler],
        ], includeContentRoutes: false);
    }

    /**
     * @return array<string, array{callable(self): ContentItemInterface, array<string, array<string, string>>}>
     */
    public static function itemsProvider(): array
    {
        return [
            'doc' => [
                fn (self $t) => $t->item('docs', 'guide'),
                ['self' => ['href' => '/docs/guide'], 'collection' => ['href' => '/docs']],
            ],
            'nested doc' => [
                fn (self $t) => $t->item('docs', 'guide/getting-started'),
                ['self' => ['href' => '/docs/guide/getting-started'], 'collection' => ['href' => '/docs']],
            ],
            'faq question' => [
                fn (self $t) => $t->item('faq', 'question-one'),
                ['self' => ['href' => '/faq/question-one'], 'collection' => ['href' => '/faq']],
            ],
            'blog post' => [
                fn (self $t) => $t->item('blog', '2026-01-15-first-post'),
                ['self' => ['href' => '/blog/2026-01-15-first-post'], 'collection' => ['href' => '/blog']],
            ],
            'academy course' => [
                fn (self $t) => $t->course(),
                ['self' => ['href' => '/academy/demo-course'], 'collection' => ['href' => '/academy']],
            ],
            'academy module' => [
                fn (self $t) => $t->module(),
                ['self' => ['href' => '/academy/demo-course/module-one'], 'collection' => ['href' => '/academy']],
            ],
            'academy lesson' => [
                fn (self $t) => $t->lesson(),
                ['self' => ['href' => '/academy/demo-course/module-one/lesson-one'], 'collection' => ['href' => '/academy']],
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
                fn (self $t) => $t->item('docs', 'guide/getting-started'),
                ['self' => ['href' => '/documents/guide/getting-started'], 'collection' => ['href' => '/documents']],
            ],
            'faq question' => [
                fn (self $t) => $t->item('faq', 'question-one'),
                ['self' => ['href' => '/questions/question-one'], 'collection' => ['href' => '/questions']],
            ],
            'blog post' => [
                fn (self $t) => $t->item('blog', '2026-01-15-first-post'),
                ['self' => ['href' => '/articles/2026-01-15-first-post'], 'collection' => ['href' => '/articles']],
            ],
            'academy course' => [
                fn (self $t) => $t->course(),
                ['self' => ['href' => '/courses/demo-course'], 'collection' => ['href' => '/courses']],
            ],
            'academy module' => [
                fn (self $t) => $t->module(),
                ['self' => ['href' => '/courses/demo-course/module-one'], 'collection' => ['href' => '/courses']],
            ],
            'academy lesson' => [
                fn (self $t) => $t->lesson(),
                ['self' => ['href' => '/courses/demo-course/module-one/lesson-one'], 'collection' => ['href' => '/courses']],
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
