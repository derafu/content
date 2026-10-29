<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent\Provider;

use Composer\InstalledVersions;
use Derafu\Content\Plugin\Docs\DocsController;
use Derafu\Content\Provider\ContentTemplatePathProvider;
use Derafu\Http\Request;
use Derafu\Renderer\Contract\RendererInterface;
use Derafu\Renderer\Engine\Html\TwigHtmlEngine;
use Derafu\Routing\Contract\RouterInterface;
use Derafu\Routing\ValueObject\RequestContext;
use Derafu\TestsContent\Support\ContentFixtures;
use Derafu\Twig\Contract\TwigServiceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesNamespace;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

#[CoversClass(ContentTemplatePathProvider::class)]
#[UsesNamespace('Derafu\Content')]
final class ContentTemplatePathProviderTest extends TestCase
{
    private string $cacheDirectory;

    protected function setUp(): void
    {
        $this->cacheDirectory = sys_get_temp_dir() . '/content-path-provider-' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->cacheDirectory);
    }

    private function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $routes = Yaml::parseFile(dirname(__DIR__, 3) . '/resources/config/content-routes.yaml');
        $this->assertIsArray($routes);
        $container->setParameter('fixture.content_routes', $routes);
        $container->setParameter('kernel.project_dir', ContentFixtures::contentPath());
        $container->setParameter('kernel.cache_dir', $this->cacheDirectory);
        (new YamlFileLoader($container, new FileLocator([
            dirname(__DIR__, 2) . '/fixtures/config',
            (InstalledVersions::getInstallPath('derafu/renderer')
                ?? throw new RuntimeException('derafu/renderer must be installed for these tests.')) . '/resources/config',
        ])))->load('provider-integration.yaml');
        foreach ($container->getDefinitions() as $definition) {
            $definition->setPublic(true);
        }
        foreach ($container->getAliases() as $alias) {
            $alias->setPublic(true);
        }
        $container->compile();

        return $container;
    }

    private function assertRenderedContributions(ContainerInterface $container): void
    {
        $router = $container->get(RouterInterface::class);
        self::assertInstanceOf(RouterInterface::class, $router);
        $router->setContext(new RequestContext(host: 'example.test', scheme: 'https', pathInfo: '/docs/guide.html'));
        $controller = $container->get(DocsController::class);
        self::assertInstanceOf(DocsController::class, $controller);
        $html = $controller->show(new Request('GET', 'https://example.test/docs/guide.html'), 'guide.html');
        self::assertIsString($html);
        self::assertStringContainsString('Guide', $html);
        self::assertStringContainsString('The content of the guide section.', $html);
        $renderer = $container->get(RendererInterface::class);
        $twig = $container->get(TwigServiceInterface::class);
        self::assertInstanceOf(RendererInterface::class, $renderer);
        self::assertInstanceOf(TwigServiceInterface::class, $twig);
        $engine = $renderer->getEngine('twig');
        self::assertInstanceOf(TwigHtmlEngine::class, $engine);
        self::assertSame($twig->getTwig(), $engine->getTwig());
        $component = $renderer->renderFromString('<twig:block-alert content="Shared component templates" />');
        self::assertStringContainsString('derafu-block-alert', $component);
        self::assertStringContainsString('Shared component templates', $component);
    }

    public function testContentAndComponentsContributeTemplatesToTheSharedEnvironment(): void
    {
        $this->assertRenderedContributions($this->container());
    }

    public function testContributionsWorkWithTheCompiledContainer(): void
    {
        $container = $this->container();
        $class = 'ContentTemplatePathsIntegrationContainer';
        $code = '';
        if (!class_exists($class, false)) {
            $dump = (new PhpDumper($container))->dump(['class' => $class]);
            self::assertIsString($dump);
            $code = substr($dump, 5);
        }
        $compiled = eval($code . "\nreturn new " . $class . '();');
        self::assertInstanceOf(ContainerInterface::class, $compiled);
        $this->assertRenderedContributions($compiled);
    }
}
