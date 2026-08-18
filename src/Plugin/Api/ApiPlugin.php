<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Plugin\Api;

use Derafu\Content\Abstract\AbstractPlugin;
use Derafu\Content\Contract\PluginInterface;

/**
 * Plugin that exposes the content of every content plugin as a single JSON
 * feed (see ApiController). It has no options and no state of its own; the
 * aggregation lives in ContentService::allContent().
 */
class ApiPlugin extends AbstractPlugin implements PluginInterface
{
}
