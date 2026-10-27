<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent\Support;

use Derafu\Routing\Contract\RouterInterface;
use Derafu\Twig\Contract\ExtensionProviderInterface;
use Derafu\Twig\Extension\RoutingExtension;
use Derafu\Twig\Extension\TranslationExtension;
use Derafu\Twig\Extension\TwigExtension;
use Symfony\Contracts\Translation\TranslatorInterface;

/** Application extensions contributed by the rendering fixtures. */
final class FixtureExtensionProvider implements ExtensionProviderInterface
{
    public function __construct(
        private readonly ?RouterInterface $router = null,
        private readonly ?TranslatorInterface $translator = null,
        private readonly ?string $domain = 'content+intl-icu'
    ) {
    }

    public function getExtensions(array $options = []): iterable
    {
        yield new TwigExtension();
        yield new TranslationExtension($this->translator, $this->domain, $this->translator !== null ? 'es' : null);
        if ($this->router !== null) {
            yield new RoutingExtension($this->router);
        }
    }
}
