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
use Derafu\Renderer\Factory\RendererFactory;
use Derafu\Routing\ValueObject\RequestContext;
use Derafu\TestsContent\Support\ContentFixtures;
use Derafu\TestsContent\Support\RouterFixture;
use Derafu\Twig\Extension\RoutingExtension;
use Derafu\Twig\Extension\TranslationExtension;
use Derafu\Twig\Extension\TwigExtension;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the "test" macro added to pdf/_macros.pdf.twig, which turns a
 * lesson's self-assessment test ("quiz", lost entirely by the PDF export
 * before this) into a static, printable questionnaire: questions with
 * checkbox-style options first, an answer key with the explanation for
 * each question afterwards.
 */
#[CoversNothing]
final class AcademyLessonPdfTestQuizTest extends TestCase
{
    private function renderLessonPdf(string $lessonSlug): string
    {
        $plugin = new AcademyPlugin(
            ContentFixtures::contentContext(),
            ['path' => 'academy']
        );
        $plugin->loadContent(new ContentLoader(ContentFixtures::contentPath()));

        $course = $plugin->registry()->get('demo-course');
        $this->assertInstanceOf(AcademyCourse::class, $course);
        $module = $course->modules()['module-one'];
        $lesson = $module->lessons()[$lessonSlug];

        $router = RouterFixture::create();
        $router->setContext(new RequestContext(
            pathInfo: "/academy/demo-course/module-one/{$lessonSlug}.pdf"
        ));

        $twigService = RendererFactory::createTwigService([
            'paths' => [
                ContentFixtures::templatesPath(),
                dirname(__DIR__, 3) . '/../resources/templates',
            ],
            'extensions' => [
                new TwigExtension(),
                new TranslationExtension(null, 'content+intl-icu'),
                new RoutingExtension($router),
            ],
        ]);

        $data = [
            'plugin' => $plugin,
            'course' => $course,
            'module' => $module,
            'lesson' => $lesson,
        ];

        return $twigService->render('academy/lesson.pdf.twig', $data);
    }

    public function testLessonWithAQuizRendersQuestionsWithCheckboxOptions(): void
    {
        $html = $this->renderLessonPdf('lesson-one');

        $this->assertStringContainsString('Test quiz', $html);
        $this->assertStringContainsString('Is this a fixture?', $html);
        $this->assertStringContainsString('Is this quiz used in production?', $html);
        // Both options of the true_false question, not just the correct one.
        $this->assertStringContainsString('True', $html);
        $this->assertStringContainsString('False', $html);
    }

    public function testLessonWithAQuizRendersAnAnswerKeyWithExplanations(): void
    {
        $html = $this->renderLessonPdf('lesson-one');

        $this->assertStringContainsString('Answers', $html);
        $this->assertStringContainsString(
            'Yes, this file is a fixture used by the tests.',
            $html
        );
        $this->assertStringContainsString(
            'No, it is only for the automated tests of derafu/content.',
            $html
        );
    }

    public function testLessonWithoutAQuizRendersNoTestMarkup(): void
    {
        $html = $this->renderLessonPdf('lesson-two');

        $this->assertStringNotContainsString('<div class="pdf-test">', $html);
        $this->assertStringNotContainsString('<div class="pdf-test-answers"', $html);
    }
}
