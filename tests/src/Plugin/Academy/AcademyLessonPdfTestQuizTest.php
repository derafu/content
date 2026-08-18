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
use Derafu\Content\Plugin\Academy\AcademyPlugin;
use Derafu\Renderer\Factory\RendererFactory;
use Derafu\Routing\ValueObject\RequestContext;
use Derafu\TestsContent\Support\ContentFixtures;
use Derafu\TestsContent\Support\RouterFixture;
use Derafu\Twig\Extension\RoutingExtension;
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

        $course = $plugin->registry()->get('curso-demo');
        $module = $course->modules()['modulo-uno'];
        $lesson = $module->lessons()[$lessonSlug];

        $router = RouterFixture::create();
        $router->setContext(new RequestContext(
            pathInfo: "/academy/curso-demo/modulo-uno/{$lessonSlug}.pdf"
        ));

        $twigService = RendererFactory::createTwigService([
            'paths' => [
                ContentFixtures::templatesPath(),
                dirname(__DIR__, 3) . '/../resources/templates',
            ],
            'extensions' => [new TwigExtension(), new RoutingExtension($router)],
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
        $html = $this->renderLessonPdf('leccion-uno');

        $this->assertStringContainsString('Cuestionario de prueba', $html);
        $this->assertStringContainsString('¿Esto es un fixture?', $html);
        $this->assertStringContainsString('¿Este cuestionario se usa en producción?', $html);
        // Both options of the true_false question, not just the correct one.
        $this->assertStringContainsString('True', $html);
        $this->assertStringContainsString('False', $html);
    }

    public function testLessonWithAQuizRendersAnAnswerKeyWithExplanations(): void
    {
        $html = $this->renderLessonPdf('leccion-uno');

        $this->assertStringContainsString('Answers', $html);
        $this->assertStringContainsString(
            'Sí, este archivo es un fixture usado por los tests.',
            $html
        );
        $this->assertStringContainsString(
            'No, es solo para los tests automatizados de derafu/content.',
            $html
        );
    }

    public function testLessonWithoutAQuizRendersNoTestMarkup(): void
    {
        $html = $this->renderLessonPdf('leccion-dos');

        $this->assertStringNotContainsString('<div class="pdf-test">', $html);
        $this->assertStringNotContainsString('<div class="pdf-test-answers"', $html);
    }
}
