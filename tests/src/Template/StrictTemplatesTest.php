<?php

declare(strict_types=1);

namespace Derafu\TestsContent\Template;

use Derafu\Config\Contract\OptionsInterface;
use Derafu\Content\Plugin\Academy\AcademyController;
use Derafu\Content\Plugin\Academy\AcademyPlugin;
use Derafu\Content\Plugin\Blog\BlogController;
use Derafu\Content\Plugin\Blog\BlogPlugin;
use Derafu\Content\Plugin\Docs\DocsController;
use Derafu\Content\Plugin\Docs\DocsPlugin;
use Derafu\Content\Plugin\Faq\FaqController;
use Derafu\Content\Plugin\Faq\FaqPlugin;
use Derafu\Content\Plugin\Pages\PagesController;
use Derafu\Content\Plugin\Pages\PagesPlugin;
use Derafu\Http\Request;
use Derafu\Renderer\Engine\Html\TwigHtmlEngine;
use Derafu\Routing\ValueObject\RequestContext;
use Derafu\TestsContent\Support\ContentFixtures;
use Derafu\TestsContent\Support\RendererFixture;
use Derafu\TestsContent\Support\RouterFixture;
use LogicException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/** Exercises the package templates with real content and strict Twig lookups. */
#[CoversNothing]
final class StrictTemplatesTest extends TestCase
{
    private PagesController $pages;

    private DocsController $docs;

    private FaqController $faq;

    private BlogController $blog;

    private AcademyController $academy;

    private DocsPlugin $docsPlugin;

    private FaqPlugin $faqPlugin;

    private Environment $twig;

    protected function setUp(): void
    {
        $context = ContentFixtures::contentContext();
        $plugins = [
            'pages' => new PagesPlugin($context, ['path' => 'pages']),
            'docs' => new DocsPlugin($context, ['path' => 'docs']),
            'faq' => new FaqPlugin($context, ['path' => 'faq']),
            'blog' => new BlogPlugin($context, ['path' => 'blog']),
            'academy' => new AcademyPlugin($context, ['path' => 'academy']),
        ];
        foreach ($plugins as $plugin) {
            $plugin->loadContent(ContentFixtures::contentLoader());
        }
        $this->docsPlugin = $plugins['docs'];
        $this->faqPlugin = $plugins['faq'];
        $service = ContentFixtures::contentService($plugins);
        $router = RouterFixture::create();
        $router->setContext(new RequestContext());
        $renderer = RendererFixture::create($router);
        $engine = $renderer->getEngine('twig');
        self::assertInstanceOf(TwigHtmlEngine::class, $engine);
        $this->twig = $engine->getTwig();
        $this->twig->enableStrictVariables();
        $loader = $this->twig->getLoader();
        self::assertInstanceOf(FilesystemLoader::class, $loader);
        $loader->prependPath(dirname(__DIR__, 3) . '/vendor/derafu/twig-components/resources/templates');

        // Check the actual PDF markup before binary conversion. Fetching video
        // thumbnails or invoking mPDF is unrelated to strict variable lookup.
        $renderer->addEngine('pdf', $engine);
        $this->pages = new PagesController($service, $renderer, $router);
        $this->docs = new DocsController($service, $renderer, $router);
        $this->faq = new FaqController($service, $renderer, $router);
        $this->blog = new BlogController($service, $renderer, $router);
        $this->academy = new AcademyController($service, $renderer, $router);
    }

    /** @return iterable<string, array{string, string, bool}> */
    public static function contentExports(): iterable
    {
        foreach (['page', 'docs-index', 'docs-guide', 'docs-api', 'faq-index', 'faq-question', 'post', 'course', 'module', 'lesson'] as $scenario) {
            foreach (['html', 'md', 'json', 'pdf'] as $format) {
                foreach (in_array($format, ['md', 'pdf'], true) ? [false, true] : [false] as $full) {
                    yield $scenario . '/' . $format . ($full ? '/full' : '') => [$scenario, $format, $full];
                }
            }
        }
    }

    #[DataProvider('contentExports')]
    public function testContentExportsRenderWithStrictVariables(string $scenario, string $format, bool $full): void
    {
        $suffix = '.' . $format;
        $request = (new Request('GET', 'http://localhost/content' . $suffix))->withQueryParams($full ? ['full' => '1'] : []);
        $result = match ($scenario) {
            'page' => $this->pages->show($request, 'about' . $suffix),
            'docs-index' => $this->docs->show($request, 'index' . $suffix),
            'docs-guide' => $this->docs->show($request, 'guide' . $suffix),
            'docs-api' => $this->docs->show($request, 'api' . $suffix),
            'faq-index' => $this->faq->show($request, 'index' . $suffix),
            'faq-question' => $this->faq->show($request, 'question-one' . $suffix),
            'post' => $this->blog->show($request, '2026-01-15-first-post' . $suffix),
            'course' => $this->academy->course($request, 'demo-course' . $suffix),
            'module' => $this->academy->module($request, 'demo-course', 'module-one' . $suffix),
            'lesson' => $this->academy->lesson($request, 'demo-course', 'module-one', 'lesson-two' . $suffix),
            default => throw new LogicException('Unknown content export scenario: ' . $scenario),
        };
        if ($format === 'json') {
            self::assertIsArray($result);
            self::assertArrayHasKey('data', $result);
        } else {
            self::assertIsString($result);
            self::assertNotEmpty($result);
        }
    }

    public function testBlogViewsUseDefaultImagesAndTheActualArchiveName(): void
    {
        $request = new Request('GET', 'http://localhost/blog');
        foreach ([$this->blog->index($request), $this->blog->tag($request, 'demo'), $this->blog->archive($request, '202601'), $this->blog->show($request, '2026-01-15-first-post')] as $html) {
            self::assertIsString($html);
            self::assertStringContainsString('/img/content/blog/default-cover.png', $html);
        }
        self::assertStringContainsString('Month: 01/2026', $this->blog->archive($request, '202601'));
    }

    public function testExplicitBasePathIsPreservedForDefaultImages(): void
    {
        $this->twig->addGlobal('base_path', '/installation');
        self::assertStringContainsString('/installation/img/content/blog/default-cover.png', $this->blog->index(new Request('GET', 'http://localhost/blog')));
    }

    public function testModuleBreadcrumbEndsAtTheModule(): void
    {
        $html = $this->academy->module(new Request('GET', 'http://localhost/academy/demo-course/module-one'), 'demo-course', 'module-one');
        self::assertIsString($html);
        preg_match('/<ol class="breadcrumb">(.*?)<\/ol>/s', $html, $matches);
        $breadcrumb = $matches[1] ?? self::fail('The module must render its breadcrumb.');
        self::assertSame(3, substr_count($breadcrumb, '<li '));
        self::assertMatchesRegularExpression('/class="breadcrumb-item active" aria-current="page">\s*Module One\s*<\/li>/', $breadcrumb);
        self::assertStringNotContainsString('Lesson', $breadcrumb);
    }

    public function testConfiguredOptionalMetadataStillControlsRenderedSections(): void
    {
        $metadata = $this->docsPlugin->registry()->get('guide')->metadata();
        self::assertInstanceOf(OptionsInterface::class, $metadata);
        $metadata['show_title'] = true;
        $metadata['show_description'] = true;
        $metadata['show_source'] = true;
        $metadata['show_children'] = true;
        $metadata['iframe'] = 'https://example.test/embedded';
        $html = $this->docs->show(new Request('GET', 'http://localhost/docs/guide'), 'guide');
        self::assertIsString($html);
        self::assertStringContainsString('<iframe src="https://example.test/embedded"', $html);
        self::assertStringContainsString('language-markdown', $html);
        self::assertStringContainsString('btn btn-outline-primary', $html);
        self::assertStringContainsString('<h1', $html);

        $faqMetadata = $this->faqPlugin->registry()->get('question-one')->metadata();
        self::assertInstanceOf(OptionsInterface::class, $faqMetadata);
        $faqMetadata['show_children'] = true;
        $faq = $this->faq->show(new Request('GET', 'http://localhost/faq/question-one'), 'question-one');
        self::assertIsString($faq);
        self::assertStringContainsString('btn btn-outline-primary', $faq);
    }

    public function testTagAndIndexViewsRenderWithStrictVariables(): void
    {
        $request = new Request('GET', 'http://localhost/');
        foreach ([$this->docs->tag($request, 'demo'), $this->faq->tag($request, 'demo'), $this->academy->index(), $this->academy->tag($request, 'demo')] as $html) {
            self::assertNotEmpty($html);
        }
    }
}
