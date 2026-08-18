<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Plugin\Storage;

use Derafu\Content\Abstract\AbstractPlugin;
use Derafu\Content\Contract\PluginInterface;

/**
 * Plugin that serves attachment downloads for any content item (see
 * StorageController). It has no options and no state of its own.
 */
class StoragePlugin extends AbstractPlugin implements PluginInterface
{
}
