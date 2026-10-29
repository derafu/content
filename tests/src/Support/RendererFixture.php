<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent\Support;

use Composer\InstalledVersions;
use Derafu\Renderer\Contract\RendererInterface;
use Derafu\Renderer\Engine\Html\TwigHtmlEngine;
use Derafu\Routing\Contract\RouterInterface;
use Derafu\Twig\Contract\TwigServiceInterface;
use RuntimeException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * Builds a real Renderer (real Twig environment, real Markdown engine),
 * pointed at both this package's own templates and the test fixture
 * layout, so controller tests render actual HTML instead of skipping the
 * rendering step or mocking RendererInterface.
 */
final class RendererFixture
{
    /**
     * The Twig environment of the real renderer that {@see self::create()}
     * builds: the one that renders the templates of this package, with its
     * functions, tags and components. For tests that read the templates
     * instead of rendering them.
     *
     * @param RouterInterface $router Real router, for the "path"/"url"/
     * "is_active_path" Twig functions.
     * @return Environment
     */
    public static function twig(RouterInterface $router): Environment
    {
        $engine = self::create($router)->getEngine('twig');
        assert($engine instanceof TwigHtmlEngine);

        return $engine->getTwig();
    }

    /**
     * Build a real renderer able to render this package's own templates
     * (docs/, faq/, pages/, etc.) on top of the fixture "layouts/default"
     * used to stand in for a website's own base layout.
     *
     * @param RouterInterface|null $router Real router to back the
     * "path"/"url"/"is_active_path" Twig functions used by the sidebar
     * templates (docs/faq). Only needed to render templates that call them.
     * @param TranslatorInterface|null $translator Translator of the
     * translation extension, in Spanish. Without it, the texts are in English,
     * as they are written in the templates.
     * @return RendererInterface
     */
    public static function create(
        ?RouterInterface $router = null,
        ?TranslatorInterface $translator = null
    ): RendererInterface {
        $container = self::container($router, $translator);
        $renderer = $container->get(RendererInterface::class);
        if (!$renderer instanceof RendererInterface) {
            throw new RuntimeException('The renderer service has an invalid type.');
        }

        return $renderer;
    }

    public static function twigService(
        ?RouterInterface $router = null,
        ?TranslatorInterface $translator = null,
        ?string $domain = 'content+intl-icu'
    ): TwigServiceInterface {
        $container = self::container($router, $translator, $domain);
        $twig = $container->get(TwigServiceInterface::class);
        if (!$twig instanceof TwigServiceInterface) {
            throw new RuntimeException('The Twig service has an invalid type.');
        }

        return $twig;
    }

    private static function container(
        ?RouterInterface $router,
        ?TranslatorInterface $translator,
        ?string $domain = 'content+intl-icu'
    ): ContainerBuilder {
        $root = dirname(__DIR__, 3);
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', ContentFixtures::contentPath());
        $container->setParameter('kernel.cache_dir', sys_get_temp_dir());
        (new YamlFileLoader($container, new FileLocator([
            $root . '/tests/fixtures/config',
            (InstalledVersions::getInstallPath('derafu/renderer')
                ?? throw new RuntimeException('derafu/renderer must be installed for these tests.')) . '/resources/config',
        ])))->load('renderer-with-router.yaml');
        $container->setParameter('fixture.translation_domain', $domain);
        $services = [
            RouterInterface::class => $router ?? RouterFixture::create(),
            TranslatorInterface::class => $translator,
        ];
        foreach ($services as $id => $service) {
            if ($service !== null) {
                $container->register($id)->setSynthetic(true)->setPublic(true);
            }
        }
        foreach ([RendererInterface::class, TwigServiceInterface::class] as $id) {
            $container->getDefinition($id)->setPublic(true);
        }
        $container->compile();
        foreach ($services as $id => $service) {
            if ($service !== null) {
                $container->set($id, $service);
            }
        }

        return $container;
    }
}
