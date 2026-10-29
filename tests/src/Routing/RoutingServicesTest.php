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

use Composer\InstalledVersions;
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
use Derafu\Routing\Contract\RouteRegistrarInterface;
use Derafu\Routing\Contract\RouterInterface;
use Derafu\Routing\Contract\UrlGeneratorInterface;
use Derafu\Routing\UrlGenerator;
use Derafu\Routing\ValueObject\RequestContext;
use Derafu\TestsContent\Support\ContentFixtures;
use Derafu\TestsContent\Support\RouteRegistrarFixture;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use RuntimeException;
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
    private function container(string $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', ContentFixtures::contentPath());
        $container->setParameter('kernel.cache_dir', sys_get_temp_dir());
        $routes = Yaml::parseFile(dirname(__DIR__, 3) . '/resources/config/content-routes.yaml');
        $this->assertIsArray($routes);
        $container->setParameter('fixture.content_routes', $routes);
        (new YamlFileLoader($container, new FileLocator([
            dirname(__DIR__, 2) . '/fixtures/config',
            (InstalledVersions::getInstallPath('derafu/renderer')
                ?? throw new RuntimeException('derafu/renderer must be installed for these tests.')) . '/resources/config',
        ])))
            ->load($config);

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
        $container = $this->container('routing-standalone-integration.yaml');
        $this->compile($container);
        $router = $container->get(RouterInterface::class);
        $this->assertInstanceOf(RouterInterface::class, $router);
        $router->setContext(new RequestContext(host: 'example.test', scheme: 'https'));
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
        $container = $this->container('routing-independent.yaml');
        $this->compile($container);
        $generator = $container->get(UrlGeneratorInterface::class);
        $registrar = $container->get(RouteRegistrarInterface::class);
        $this->assertInstanceOf(UrlGenerator::class, $generator);
        $this->assertInstanceOf(RouteRegistrarFixture::class, $registrar);
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
