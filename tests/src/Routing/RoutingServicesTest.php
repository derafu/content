<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent\Routing;

use Derafu\Content\Contract\ContentServiceInterface;
use Derafu\Content\Plugin\Academy\AcademyController;
use Derafu\Content\Plugin\Api\ApiController;
use Derafu\Content\Plugin\Blog\BlogController;
use Derafu\Content\Plugin\Docs\DocsController;
use Derafu\Content\Plugin\Faq\FaqController;
use Derafu\Content\Plugin\Mcp\McpController;
use Derafu\Content\Plugin\Pages\PagesController;
use Derafu\Content\Plugin\Search\SearchController;
use Derafu\Content\Plugin\Sitemap\SitemapController;
use Derafu\Content\Plugin\Storage\StorageController;
use Derafu\Http\Request;
use Derafu\Renderer\Contract\RendererInterface;
use Derafu\Routing\Contract\RouteRegistrarInterface;
use Derafu\Routing\Contract\RouterInterface;
use Derafu\Routing\Contract\UrlGeneratorInterface;
use Derafu\Routing\RouteCollectionFactory;
use Derafu\Routing\Router;
use Derafu\Routing\UrlGenerator;
use Derafu\Routing\ValueObject\RequestContext;
use Derafu\TestsContent\Support\ContentFixtures;
use Derafu\TestsContent\Support\RendererFixture;
use Derafu\TestsContent\Support\RouteAddingPlugin;
use Derafu\TestsContent\Support\RouteRegistrarFixture;
use Derafu\TestsContent\Support\RouterFixture;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Yaml\Yaml;

/**
 * The actual package YAML supports both a standalone router and independent
 * URL-generation and route-registration services supplied by an integration.
 */
#[CoversNothing]
final class RoutingServicesTest extends TestCase
{
    private function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', ContentFixtures::contentPath());
        $container->setParameter('kernel.cache_dir', sys_get_temp_dir());
        (new YamlFileLoader($container, new FileLocator(dirname(__DIR__, 3) . '/resources/config')))
            ->load('content-services.yaml');
        $container->setParameter('derafu.content.config', [
            'title' => 'Fixture Website',
            'url' => 'https://example.test',
            'plugins' => [
                'api' => null,
                'custom' => [
                    'class' => RouteAddingPlugin::class,
                    'name' => 'custom',
                    'path' => 'docs',
                ],
            ],
        ]);
        // Keep caching isolated while preserving the real package services.
        $container->register('derafu_content.cache', ArrayAdapter::class)->setSynthetic(true)->setPublic(true);
        $container->register(RendererInterface::class)->setSynthetic(true)->setPublic(true);

        return $container;
    }

    private function compile(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() as $definition) {
            $definition->setPublic(true);
        }
        foreach ($container->getAliases() as $alias) {
            $alias->setPublic(true);
        }
        $container->compile();
        $container->set('derafu_content.cache', new ArrayAdapter());
        $container->set(RendererInterface::class, RendererFixture::create());
    }

    private function assertControllersCanBeConstructed(ContainerBuilder $container): void
    {
        foreach ([AcademyController::class, ApiController::class, BlogController::class,
            DocsController::class, FaqController::class, McpController::class,
            PagesController::class, SearchController::class, SitemapController::class,
            StorageController::class] as $class) {
            $this->assertInstanceOf($class, $container->get($class));
        }
    }

    public function testTheExistingRouterServiceRemainsSufficient(): void
    {
        $container = $this->container();
        $router = RouterFixture::create();
        $router->setContext(new RequestContext(host: 'example.test', scheme: 'https'));
        $container->register(RouterInterface::class, Router::class)->setSynthetic(true)->setPublic(true);
        $this->compile($container);
        $container->set(RouterInterface::class, $router);
        $this->assertControllersCanBeConstructed($container);

        $this->assertSame($router, $container->get(UrlGeneratorInterface::class));
        $this->assertSame($router, $container->get(RouteRegistrarInterface::class));
        $service = $container->get(ContentServiceInterface::class);
        $this->assertInstanceOf(ContentServiceInterface::class, $service);
        $this->assertArrayHasKey('custom', $service->plugins());
        $this->assertSame('/custom/7', $router->generate('custom_route', ['id' => 7]));
    }

    public function testAnIntegrationCanReplaceBothCapabilitiesWithoutRegisteringARouter(): void
    {
        $container = $this->container();
        $routes = Yaml::parseFile(dirname(__DIR__, 3) . '/resources/config/content-routes.yaml');
        $this->assertIsArray($routes);
        $collection = (new RouteCollectionFactory())->create($routes);
        $generator = new UrlGenerator($collection, new RequestContext(
            baseUrl: '/app',
            host: 'example.test',
            scheme: 'https'
        ));
        $registrar = new RouteRegistrarFixture($collection);
        $container->register(UrlGeneratorInterface::class, UrlGenerator::class)->setSynthetic(true)->setPublic(true);
        $container->register(RouteRegistrarInterface::class, RouteRegistrarFixture::class)->setSynthetic(true)->setPublic(true);
        $this->compile($container);
        $container->set(UrlGeneratorInterface::class, $generator);
        $container->set(RouteRegistrarInterface::class, $registrar);
        $this->assertControllersCanBeConstructed($container);

        $this->assertFalse($container->has(RouterInterface::class));
        $this->assertSame($generator, $container->get(UrlGeneratorInterface::class));
        $this->assertSame($registrar, $container->get(RouteRegistrarInterface::class));
        $service = $container->get(ContentServiceInterface::class);
        $this->assertInstanceOf(ContentServiceInterface::class, $service);
        $this->assertArrayHasKey('custom', $service->plugins());
        $this->assertSame('/app/custom/7', $generator->generate('custom_route', ['id' => 7]));

        $controller = $container->get(ApiController::class);
        $this->assertInstanceOf(ApiController::class, $controller);
        $data = $controller->index(new Request('GET', 'https://example.test/app/api/content.json'))['data'];
        $this->assertNotEmpty($data);
        foreach ($data as $item) {
            $this->assertIsString($item['link']);
            $this->assertStringStartsWith('https://example.test/app/docs/', $item['link']);
        }
    }
}
