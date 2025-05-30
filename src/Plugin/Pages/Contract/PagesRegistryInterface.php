<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Plugin\Pages\Contract;

use Derafu\Content\Contract\ContentRegistryInterface;

/**
 * Pages registry interface.
 */
interface PagesRegistryInterface extends ContentRegistryInterface
{
    /**
     * Get pages filtered by criteria.
     *
     * @param array<string, mixed> $filters Filter criteria.
     * @return array<PagesPageInterface>
     */
    public function filter(array $filters = []): array;

    /**
     * Get a page by URI.
     *
     * @param string $uri URI of the page.
     * @return PagesPageInterface
     */
    public function get(string $uri): PagesPageInterface;

    /**
     * Get the previous page relative to the given ID.
     *
     * @param string $uri URI of the page.
     * @param array<string, mixed> $filters Filter criteria.
     * @return PagesPageInterface|null
     */
    public function previous(string $uri, array $filters = []): ?PagesPageInterface;

    /**
     * Get the next page relative to the given ID.
     *
     * @param string $uri URI of the page.
     * @param array<string, mixed> $filters Filter criteria.
     * @return PagesPageInterface|null
     */
    public function next(string $uri, array $filters = []): ?PagesPageInterface;
}
