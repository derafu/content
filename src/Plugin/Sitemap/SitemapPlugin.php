<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Plugin\Sitemap;

use Derafu\Content\Abstract\AbstractPlugin;
use Derafu\Content\Contract\PluginInterface;

/**
 * Plugin that exposes an XML sitemap of every indexable content item (see
 * SitemapController). It has no options and no state of its own.
 */
class SitemapPlugin extends AbstractPlugin implements PluginInterface
{
}
