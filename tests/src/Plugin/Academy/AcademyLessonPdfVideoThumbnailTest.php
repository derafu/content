<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent\Plugin\Academy;

use Derafu\Content\ContentLoader;
use Derafu\Content\Plugin\Academy\AcademyCourse;
use Derafu\Content\Plugin\Academy\AcademyPlugin;
use Derafu\Routing\ValueObject\RequestContext;
use Derafu\TestsContent\Support\ContentFixtures;
use Derafu\TestsContent\Support\RendererFixture;
use Derafu\TestsContent\Support\RouterFixture;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the "video" macro added to pdf/_macros.pdf.twig, which turns a
 * frontmatter "video" (lost entirely by the PDF export before this) into a
 * clickable YouTube thumbnail. Renders the Twig template directly (the
 * "twig" engine only, no "pdf") on purpose: going through the real "pdf"
 * engine would make mPDF actually fetch the thumbnail over the network to
 * embed it, which is exactly what HtmlPdfEngineLocalAssetsTest (in
 * derafu/renderer) already proves works — this test only needs to pin
 * down the markup (thumbnail URL, watch URL) the template produces, with
 * no network dependency.
 */
#[CoversNothing]
final class AcademyLessonPdfVideoThumbnailTest extends TestCase
{
    public function testLessonWithAYoutubeVideoRendersAClickableThumbnail(): void
    {
        $plugin = new AcademyPlugin(
            ContentFixtures::contentContext(),
            ['path' => 'academy']
        );
        $plugin->loadContent(new ContentLoader(ContentFixtures::contentPath()));

        $course = $plugin->registry()->get('demo-course');
        $this->assertInstanceOf(AcademyCourse::class, $course);
        $module = $course->modules()['module-one'];
        $lesson = $module->lessons()['lesson-two'];

        $router = RouterFixture::create();
        $router->setContext(new RequestContext(
            pathInfo: '/academy/demo-course/module-one/lesson-two.pdf'
        ));

        $twigService = RendererFixture::twigService($router);

        $data = [
            'plugin' => $plugin,
            'course' => $course,
            'module' => $module,
            'lesson' => $lesson,
        ];
        $html = $twigService->render('academy/lesson.pdf.twig', $data);

        // Fixture's frontmatter: video: "https://www.youtube.com/watch?v=dQw4w9WgXcQ".
        $this->assertStringContainsString(
            '<img src="https://img.youtube.com/vi/dQw4w9WgXcQ/hqdefault.jpg"',
            $html
        );
        $this->assertStringContainsString(
            '<a href="https://www.youtube.com/watch?v=dQw4w9WgXcQ">',
            $html
        );
        $this->assertStringContainsString('Watch video', $html);
    }

    public function testLessonWithoutAVideoRendersNoVideoMarkup(): void
    {
        $plugin = new AcademyPlugin(
            ContentFixtures::contentContext(),
            ['path' => 'academy']
        );
        $plugin->loadContent(new ContentLoader(ContentFixtures::contentPath()));

        $course = $plugin->registry()->get('demo-course');
        $this->assertInstanceOf(AcademyCourse::class, $course);
        $module = $course->modules()['module-one'];
        $lesson = $module->lessons()['lesson-one'];

        $router = RouterFixture::create();
        $router->setContext(new RequestContext(
            pathInfo: '/academy/demo-course/module-one/lesson-one.pdf'
        ));

        $twigService = RendererFixture::twigService($router);

        $data = [
            'plugin' => $plugin,
            'course' => $course,
            'module' => $module,
            'lesson' => $lesson,
        ];
        $html = $twigService->render('academy/lesson.pdf.twig', $data);

        // Note: '.pdf-video { ... }' still appears in the <style> block
        // regardless (it is a static CSS rule), so assert on the actual
        // rendered markup instead of the bare class name.
        $this->assertStringNotContainsString('<div class="pdf-video">', $html);
        $this->assertStringNotContainsString('Watch video', $html);
    }
}
