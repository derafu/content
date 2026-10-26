<?php

declare(strict_types=1);

namespace Derafu\TestsContent\Plugin\Pages;

use Derafu\Content\Plugin\Pages\PagesPlugin;
use Derafu\TestsContent\Support\ContentFixtures;
use Derafu\TestsContent\Support\RendererFixture;
use Derafu\TestsContent\Support\RouterFixture;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class PagesPdfTemplateTest extends TestCase
{
    public function testPagePdfRendersWithStrictVariablesAndWithoutASectionLabel(): void
    {
        $plugin = new PagesPlugin(ContentFixtures::contentContext(), ['path' => 'pages']);
        $plugin->loadContent(ContentFixtures::contentLoader());
        $twig = RendererFixture::twig(RouterFixture::create());
        $twig->enableStrictVariables();
        $html = $twig->render('pages/show.pdf.twig', ['plugin' => $plugin, 'page' => $plugin->registry()->get('about')]);

        self::assertStringContainsString('About us', $html);
        self::assertStringContainsString('Test page used by the automated tests', $html);
        self::assertStringNotContainsString('pdf-breadcrumb">', $html);
    }
}
