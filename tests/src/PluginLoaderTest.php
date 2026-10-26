<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent;

use ArrayObject;
use Derafu\Content\Abstract\AbstractContentItem;
use Derafu\Content\Abstract\AbstractContentPlugin;
use Derafu\Content\Abstract\AbstractContentRegistry;
use Derafu\Content\Abstract\AbstractPlugin;
use Derafu\Content\ContentBag;
use Derafu\Content\ContentConfig;
use Derafu\Content\ContentContext;
use Derafu\Content\ContentFilters;
use Derafu\Content\ContentLoader;
use Derafu\Content\ContentService;
use Derafu\Content\ContentSplFileInfo;
use Derafu\Content\Plugin\Docs\DocsDoc;
use Derafu\Content\Plugin\Docs\DocsPlugin;
use Derafu\Content\Plugin\Docs\DocsRegistry;
use Derafu\Content\Plugin\Pages\PagesPage;
use Derafu\Content\Plugin\Pages\PagesPlugin;
use Derafu\Content\Plugin\Pages\PagesRegistry;
use Derafu\Content\PluginLoader;
use Derafu\Routing\Collection;
use Derafu\Routing\UrlGenerator;
use Derafu\TestsContent\Support\ContentFixtures;
use Derafu\TestsContent\Support\RouteAddingPlugin;
use Derafu\TestsContent\Support\RouteRegistrarFixture;
use Derafu\TestsContent\Support\RouterFixture;
use Derafu\Translation\Contract\TranslatableInterface;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Throwable;

/**
 * The plugins are created from the configuration: what is wrong in the options
 * of a plugin, or in the class that it names, is an error that says which
 * plugin it is.
 */
#[CoversClass(PluginLoader::class)]
#[UsesClass(ContentConfig::class)]
#[UsesClass(ContentContext::class)]
#[UsesClass(ContentService::class)]
#[UsesClass(ContentLoader::class)]
#[UsesClass(AbstractContentItem::class)]
#[UsesClass(AbstractContentPlugin::class)]
#[UsesClass(AbstractContentRegistry::class)]
#[UsesClass(AbstractPlugin::class)]
#[UsesClass(ContentBag::class)]
#[UsesClass(ContentSplFileInfo::class)]
#[UsesClass(DocsDoc::class)]
#[UsesClass(DocsPlugin::class)]
#[UsesClass(DocsRegistry::class)]
#[UsesClass(PagesPlugin::class)]
#[UsesClass(PagesRegistry::class)]
#[UsesClass(PagesPage::class)]
#[UsesClass(ContentFilters::class)]
final class PluginLoaderTest extends TestCase
{
    /**
     * @param array<string, mixed> $plugins
     */
    private function failureOf(array $plugins): Throwable
    {
        $context = new ContentContext(
            new ContentConfig(['title' => 'Fixture Website', 'url' => 'http://localhost', 'plugins' => $plugins]),
            new ArrayAdapter()
        );
        $loader = new PluginLoader(ContentFixtures::contentLoader(), RouterFixture::create());

        try {
            $loader->loadAll(new ContentService($context, $loader));
        } catch (Throwable $e) {
            return $e;
        }

        $this->fail('The plugins were loaded.');
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function providePluginsThatAreNotValid(): array
    {
        return [
            'options that are not an object' => [
                ['docs' => 'yes'],
                'The options of the plugin "docs" must be an object, string given.',
            ],
            'a class that is not a name' => [
                ['docs' => ['class' => 7]],
                'The "class" of the plugin "docs" must be a class name, int given.',
            ],
            'a class that does not exist' => [
                ['docs' => ['class' => 'Does\\Not\\Exist']],
                'The class "Does\\Not\\Exist" of the plugin "docs" does not exist.',
            ],
            'a class that is not a plugin' => [
                ['docs' => ['class' => ArrayObject::class]],
                'The class "ArrayObject" of the plugin "docs" must implement Derafu\\Content\\Contract\\PluginInterface.',
            ],
            'a name without plugin' => [
                ['nothing' => []],
                'The class "Derafu\\Content\\Plugin\\Nothing\\NothingPlugin" of the plugin "nothing" does not exist.',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $plugins
     */
    #[DataProvider('providePluginsThatAreNotValid')]
    public function testAPluginThatIsNotValidIsATranslatableErrorThatSaysWhich(array $plugins, string $message): void
    {
        $exception = $this->failureOf($plugins);

        $this->assertInstanceOf(InvalidArgumentException::class, $exception);
        $this->assertInstanceOf(TranslatableInterface::class, $exception);
        $this->assertSame($message, $exception->getMessage());
    }

    /**
     * @param array<string, mixed> $plugins
     * @return array{array<string, \Derafu\Content\Contract\PluginInterface>, \Derafu\Routing\Router}
     */
    private function load(array $plugins): array
    {
        $context = new ContentContext(
            new ContentConfig(['title' => 'Fixture Website', 'url' => 'http://localhost', 'plugins' => $plugins]),
            new ArrayAdapter()
        );
        $router = RouterFixture::create();
        $loader = new PluginLoader(ContentFixtures::contentLoader(), $router);

        return [$loader->loadAll(new ContentService($context, $loader)), $router];
    }

    public function testThePluginsAreCreatedFromTheConfigurationWithTheirContentLoaded(): void
    {
        [$plugins] = $this->load(['docs' => ['path' => 'docs'], 'pages' => ['path' => 'pages']]);

        $this->assertSame(['docs', 'pages'], array_keys($plugins));
        $this->assertInstanceOf(DocsPlugin::class, $plugins['docs']);
        $this->assertSame('guide', $plugins['docs']->registry()->get('guide')->slug());
    }

    public function testAPluginWithoutOptionsHasTheDefaultOnes(): void
    {
        [$plugins] = $this->load(['docs' => null]);

        $this->assertInstanceOf(DocsPlugin::class, $plugins['docs']);
    }

    public function testAPluginCanHaveAnotherNameThanItsClass(): void
    {
        [$plugins] = $this->load(['manual' => ['class' => DocsPlugin::class, 'name' => 'manual', 'path' => 'docs']]);

        $this->assertSame(['manual'], array_keys($plugins));
        $this->assertInstanceOf(DocsPlugin::class, $plugins['manual']);
        $this->assertSame('manual', $plugins['manual']->name());
    }

    public function testAPluginCanAddARouteToTheWebsiteWhenItsContentIsLoaded(): void
    {
        [, $router] = $this->load(['custom' => ['class' => RouteAddingPlugin::class, 'name' => 'custom', 'path' => 'docs']]);

        $this->assertSame('/custom/7', $router->generate('custom_route', ['id' => '7']));
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function provideRoutesThatAreIncomplete(): array
    {
        return [
            'without name' => [['path' => '/a', 'handler' => 'h'], 'Key "name" is required for addRoute().'],
            'without path' => [['name' => 'a', 'handler' => 'h'], 'Key "path" is required for addRoute().'],
            'without handler' => [['name' => 'a', 'path' => '/a'], 'Key "handler" is required for addRoute().'],
        ];
    }

    /**
     * @param array<string, mixed> $route
     */
    #[DataProvider('provideRoutesThatAreIncomplete')]
    public function testARouteWithoutWhatItNeedsIsAnError(array $route, string $message): void
    {
        $exception = null;
        try {
            $this->load(['custom' => ['class' => RouteAddingPlugin::class, 'name' => 'custom', 'path' => 'docs', 'route' => $route]]);
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(InvalidArgumentException::class, $exception);
        $this->assertSame($message, $exception->getMessage());
    }

    public function testPluginsCanRegisterRoutesWithoutARouterOrUrlGenerator(): void
    {
        $collection = new Collection();
        $registrar = new RouteRegistrarFixture($collection);
        $context = new ContentContext(new ContentConfig([
            'title' => 'Fixture Website',
            'url' => 'http://localhost',
            'plugins' => [
                'custom' => [
                    'class' => RouteAddingPlugin::class,
                    'name' => 'custom',
                    'path' => 'docs',
                    'route' => [
                        'name' => 'custom_route',
                        'path' => '/custom/{id}',
                        'handler' => 'custom.handler',
                        'defaults' => ['id' => 7],
                        'methods' => ['POST'],
                    ],
                ],
            ],
        ]), new ArrayAdapter());
        $loader = new PluginLoader(ContentFixtures::contentLoader(), $registrar);

        $plugins = $loader->loadAll(new ContentService($context, $loader));
        $route = $collection->getByName('custom_route');

        $this->assertArrayHasKey('custom', $plugins);
        $this->assertSame('custom.handler', $route->getHandler());
        $this->assertSame(['id' => 7], $route->getDefaults());
        $this->assertSame(['POST'], $route->getMethods());
        $this->assertSame('/custom/7', (new UrlGenerator($collection))->generate('custom_route'));
    }
}
