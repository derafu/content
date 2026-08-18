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

use Derafu\Content\Contract\ContentLoaderInterface;

/**
 * Real ContentLoaderInterface decorator that counts scan() calls.
 *
 * Every call still delegates to a real loader; this only counts them, so
 * tests can prove a registry cache hit skips the filesystem scan entirely
 * without mocking anything.
 */
final class CountingContentLoader implements ContentLoaderInterface
{
    public int $scans = 0;

    public function __construct(private readonly ContentLoaderInterface $loader)
    {
    }

    public function scan(string $path, array $include, array $exclude): array
    {
        $this->scans++;

        return $this->loader->scan($path, $include, $exclude);
    }

    public function load(string $class, array $hierarchy): array
    {
        return $this->loader->load($class, $hierarchy);
    }
}
