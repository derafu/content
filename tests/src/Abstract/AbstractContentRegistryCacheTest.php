<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent\Abstract;

use Derafu\Content\Abstract\AbstractContentItem;
use Derafu\Content\Abstract\AbstractContentRegistry;
use Derafu\Content\ContentAttachment;
use Derafu\Content\ContentBag;
use Derafu\Content\ContentConfig;
use Derafu\Content\ContentContext;
use Derafu\Content\ContentLoader;
use Derafu\Content\ContentSplFileInfo;
use Derafu\Content\ContentTag;
use Derafu\Content\Exception\ContentNotFoundException;
use Derafu\Content\Plugin\Academy\AcademyCourse;
use Derafu\Content\Plugin\Academy\AcademyLesson;
use Derafu\Content\Plugin\Academy\AcademyModule;
use Derafu\Content\Plugin\Academy\AcademyPlugin;
use Derafu\Content\Plugin\Academy\AcademyRegistry;
use Derafu\Content\Plugin\Docs\DocsDoc;
use Derafu\Content\Plugin\Docs\DocsRegistry;
use Derafu\Content\Plugin\Faq\FaqQuestion;
use Derafu\Content\Plugin\Faq\FaqRegistry;
use Derafu\TestsContent\Support\ContentFixtures;
use Derafu\TestsContent\Support\CountingContentLoader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

/**
 * Exercises the registry caching added this session — real cache pools
 * (Symfony's ArrayAdapter/FilesystemAdapter, no mocks), a real counting
 * ContentLoader decorator to prove a filesystem scan is actually skipped
 * on a cache hit, and a real disk round trip through
 * AbstractContentItem::__serialize()/__unserialize(), including the
 * nested Academy course/module/lesson/attachment tree, which is exactly
 * the shape that could silently lose data if serialization only handled
 * a flat item.
 */
#[CoversClass(AbstractContentRegistry::class)]
#[CoversClass(AbstractContentItem::class)]
#[UsesClass(ContentContext::class)]
#[UsesClass(DocsRegistry::class)]
#[UsesClass(DocsDoc::class)]
#[UsesClass(FaqRegistry::class)]
#[UsesClass(FaqQuestion::class)]
#[UsesClass(AcademyPlugin::class)]
#[UsesClass(AcademyRegistry::class)]
#[UsesClass(AcademyCourse::class)]
#[UsesClass(AcademyModule::class)]
#[UsesClass(AcademyLesson::class)]
#[UsesClass(ContentAttachment::class)]
#[UsesClass(ContentBag::class)]
#[UsesClass(ContentConfig::class)]
#[UsesClass(ContentLoader::class)]
#[UsesClass(ContentSplFileInfo::class)]
#[UsesClass(ContentTag::class)]
#[UsesClass(ContentNotFoundException::class)]
final class AbstractContentRegistryCacheTest extends TestCase
{
    public function testCacheHitAvoidsARealSecondFilesystemScan(): void
    {
        $loader = new CountingContentLoader(ContentFixtures::contentLoader());
        $cache = new ArrayAdapter();

        $first = new DocsRegistry(
            $loader,
            'docs',
            ['**.{markdown,md}'],
            [],
            $cache
        );
        $first->all();

        $this->assertSame(1, $loader->scans);

        $second = new DocsRegistry(
            $loader,
            'docs',
            ['**.{markdown,md}'],
            [],
            $cache
        );
        $items = $second->all();

        // Still 1: the second registry's all() was served from the cache,
        // it never touched the filesystem loader's scan() again.
        $this->assertSame(1, $loader->scans);
        $this->assertSame('Guía', $items['guia']->title());
    }

    public function testDifferentContentPathsDoNotShareACacheEntryEvenWithTheSameCachePool(): void
    {
        $loader = ContentFixtures::contentLoader();
        $cache = new ArrayAdapter();

        $docs = new DocsRegistry($loader, 'docs', ['**.{markdown,md}'], [], $cache);
        $faq = new FaqRegistry($loader, 'faq', ['**.{markdown,md}'], [], $cache);

        $this->assertArrayHasKey('guia', $docs->all());
        $this->assertArrayNotHasKey('guia', $faq->all());
        $this->assertArrayHasKey('pregunta-uno', $faq->all());
        $this->assertArrayNotHasKey('pregunta-uno', $docs->all());
    }

    /**
     * The real, most important guarantee: after a cache round trip, a
     * three-level nested tree (course -> module -> lesson), with an
     * attachment and a parent/child cycle, must still behave exactly like
     * the freshly-loaded tree — including AcademyCourse::lessons()/time(),
     * which are private caches declared on the *subclass*, not on
     * AbstractContentItem, and therefore invisible to the generic
     * __serialize() there.
     */
    public function testNestedAcademyTreeSurvivesARealFilesystemAdapterRoundTrip(): void
    {
        $cacheDir = sys_get_temp_dir() . '/derafu-content-cache-test-' . uniqid();
        $cache = new FilesystemAdapter('derafu_content_test', 60, $cacheDir);

        try {
            $loader = new CountingContentLoader(ContentFixtures::contentLoader());

            $first = new AcademyRegistry(
                $loader,
                'academy',
                ['**.{markdown,md}'],
                [],
                $cache
            );
            $first->all();
            $this->assertSame(1, $loader->scans);

            // A second, independent registry, sharing only the real
            // filesystem cache: this forces an actual disk read plus
            // Symfony's real (un)serialization, not an in-memory clone.
            $second = new AcademyRegistry(
                $loader,
                'academy',
                ['**.{markdown,md}'],
                [],
                $cache
            );
            $course = $second->all()['curso-demo'];

            $this->assertSame(1, $loader->scans);
            $this->assertInstanceOf(AcademyCourse::class, $course);
            $this->assertSame('Curso Demo', $course->title());

            $module = $course->modules()['modulo-uno'];
            $this->assertSame($course, $module->course());
            $this->assertSame($course, $module->parent());

            $lessons = $module->lessons();
            $this->assertCount(2, $lessons);
            $this->assertSame(7, $lessons['leccion-uno']->time());

            // AcademyCourse::lessons()/time() are private caches declared
            // on AcademyCourse itself: not restored by the generic
            // __serialize()/__unserialize() on AbstractContentItem, so
            // this proves they still recompute correctly on demand from
            // the restored children() tree.
            $this->assertCount(2, $course->lessons());
            $expectedTime = $lessons['leccion-uno']->time() + $lessons['leccion-dos']->time();
            $this->assertSame($expectedTime, $module->time());
            $this->assertSame($expectedTime, $course->time());

            $test = $lessons['leccion-uno']->testAttachment();
            $this->assertStringContainsString('"questions"', $test->raw());
        } finally {
            ContentFixtures::removeDirectory($cacheDir);
        }
    }
}
