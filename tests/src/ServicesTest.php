<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent;

use Composer\InstalledVersions;
use Derafu\Content\Contract\ContentConfigInterface;
use Derafu\Content\Contract\ContentContextInterface;
use Derafu\Content\Contract\ContentServiceInterface;
use Derafu\Content\Exception\PluginNotFoundException;
use Derafu\Content\Plugin\Api\ApiController;
use Derafu\Content\Plugin\Docs\DocsController;
use Derafu\Content\Plugin\Pages\PagesController;
use Derafu\Content\Plugin\Sitemap\SitemapController;
use Derafu\Content\Translation\ContentTranslationResourceProvider;
use Derafu\Http\Request;
use Derafu\Routing\Contract\RouteRegistrarInterface;
use Derafu\Routing\Contract\RouterInterface;
use Derafu\Routing\Contract\UrlGeneratorInterface;
use Derafu\Routing\ValueObject\RequestContext;
use Derafu\TestsContent\Support\ContentFixtures;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The complete package YAML with real application dependencies and fixture content.
 */
#[CoversNothing]
final class ServicesTest extends TestCase
{
    private string $cacheDirectory;

    protected function setUp(): void
    {
        $this->cacheDirectory = sys_get_temp_dir() . '/derafu-content-services-' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->cacheDirectory);
    }

    private function container(bool $pages = false): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', ContentFixtures::contentPath());
        $container->setParameter('kernel.cache_dir', $this->cacheDirectory);
        (new YamlFileLoader($container, new FileLocator([
            dirname(__DIR__, 2) . '/tests/fixtures/config',
            (InstalledVersions::getInstallPath('derafu/renderer')
                ?? throw new RuntimeException('derafu/renderer must be installed for these tests.')) . '/resources/config',
        ])))
            ->load('services.yaml');
        $configuration = $container->getParameter('derafu.content.config');
        $this->assertIsArray($configuration);
        $this->assertIsArray($configuration['plugins']);
        $configuration['title'] = 'Services Website';
        $configuration['url'] = 'https://example.test';
        foreach (['academy', 'blog', 'docs', 'faq'] as $name) {
            $configuration['plugins'][$name] = ['path' => $name];
        }
        if ($pages) {
            $configuration['plugins']['pages'] = ['path' => 'pages'];
        }
        $container->setParameter('derafu.content.config', $configuration);
        $this->assertArrayHasKey(
            ContentTranslationResourceProvider::class,
            $container->findTaggedServiceIds('derafu_translation.resource_provider')
        );
        foreach ($container->getDefinitions() as $definition) {
            $definition->setPublic(true);
        }
        foreach ($container->getAliases() as $alias) {
            $alias->setPublic(true);
        }
        $container->compile();
        $router = $container->get(RouterInterface::class);
        $this->assertInstanceOf(RouterInterface::class, $router);
        $router->setContext(new RequestContext(host: 'example.test', scheme: 'https'));

        return $container;
    }

    public function testAllServicesAreConstructedAndContextDependenciesAreShared(): void
    {
        $container = $this->container();
        foreach ($container->getDefinitions() as $id => $definition) {
            if (!str_starts_with($id, 'Derafu\\Content\\') && $id !== 'derafu_content.cache') {
                continue;
            }
            $class = $definition->getClass();
            $this->assertNotNull($class);
            $this->assertTrue(class_exists($class));
            $this->assertInstanceOf($class, $container->get($id), $id);
        }
        $context = $container->get(ContentContextInterface::class);
        $this->assertInstanceOf(ContentContextInterface::class, $context);
        $this->assertSame($container->get(ContentConfigInterface::class), $context->config());
        $this->assertSame($container->get('derafu_content.cache'), $context->cache());
        $this->assertInstanceOf(FilesystemAdapter::class, $context->cache());
        $this->assertSame('Services Website', $context->config()->title());
        $this->assertSame($container->get(RouterInterface::class), $container->get(UrlGeneratorInterface::class));
        $this->assertSame($container->get(RouterInterface::class), $container->get(RouteRegistrarInterface::class));
    }

    public function testDefaultPluginsLoadFixtureContentAndRenderDocsThroughTheConfiguredController(): void
    {
        $container = $this->container();
        $service = $container->get(ContentServiceInterface::class);
        $this->assertInstanceOf(ContentServiceInterface::class, $service);
        $this->assertSame(['academy', 'api', 'blog', 'docs', 'faq', 'sitemap', 'storage'], array_keys($service->plugins()));
        $docs = $container->get(DocsController::class);
        $this->assertInstanceOf(DocsController::class, $docs);
        $html = $docs->show(new Request('GET', 'https://example.test/docs/guide.html'), 'guide.html');
        $this->assertIsString($html);
        $this->assertStringContainsString('Guide', $html);
        $this->assertStringContainsString('The content of the guide section.', $html);
        $api = $container->get(ApiController::class);
        $this->assertInstanceOf(ApiController::class, $api);
        $this->assertNotEmpty($api->index(new Request('GET', 'https://example.test/api/content.json'))['data']);
    }

    public function testExplicitlyEnabledPagesParticipateInRenderingAndTheSitemap(): void
    {
        $container = $this->container(pages: true);
        $pages = $container->get(PagesController::class);
        $this->assertInstanceOf(PagesController::class, $pages);
        $html = $pages->show(new Request('GET', 'https://example.test/pages/about.html'), 'about.html');
        $this->assertIsString($html);
        $this->assertStringContainsString('About us', $html);
        $sitemap = $container->get(SitemapController::class);
        $this->assertInstanceOf(SitemapController::class, $sitemap);
        $response = $sitemap->index();
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('application/xml', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('<loc>https://example.test/pages/about</loc>', (string) $response->getBody());
    }

    public function testTaggedResourcesTranslateAnExceptionForADisabledPlugin(): void
    {
        $container = $this->container();
        $translator = $container->get(TranslatorInterface::class);
        $this->assertInstanceOf(Translator::class, $translator);
        $service = $container->get(ContentServiceInterface::class);
        $this->assertInstanceOf(ContentServiceInterface::class, $service);
        try {
            $service->plugin('pages');
            $this->fail('The Pages plugin was enabled without configuration.');
        } catch (PluginNotFoundException $exception) {
            $this->assertSame(
                'Plugin "pages" not found. Available plugins: academy, api, blog, docs, faq, sitemap, storage.',
                $exception->getMessage()
            );
            $this->assertSame(
                'No se encontró el plugin "pages". Plugins disponibles: academy, api, blog, docs, faq, sitemap, storage.',
                $exception->trans($translator)
            );
        }
    }
}
