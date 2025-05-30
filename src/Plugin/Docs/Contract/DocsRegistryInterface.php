<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Plugin\Docs\Contract;

use Derafu\Content\Contract\ContentRegistryInterface;

/**
 * Docs registry interface.
 */
interface DocsRegistryInterface extends ContentRegistryInterface
{
    /**
     * Get docs filtered by criteria.
     *
     * @param array<string, mixed> $filters Filter criteria.
     * @return array<DocsDocInterface>
     */
    public function filter(array $filters = []): array;

    /**
     * Get a doc by ID.
     *
     * @param string $uri URI of the doc.
     * @return DocsDocInterface
     */
    public function get(string $uri): DocsDocInterface;

    /**
     * Get the previous doc relative to the given ID.
     *
     * @param string $uri URI of the doc.
     * @param array<string, mixed> $filters Filter criteria.
     * @return DocsDocInterface|null
     */
    public function previous(string $uri, array $filters = []): ?DocsDocInterface;

    /**
     * Get the next doc relative to the given ID.
     *
     * @param string $uri URI of the doc.
     * @param array<string, mixed> $filters Filter criteria.
     * @return DocsDocInterface|null
     */
    public function next(string $uri, array $filters = []): ?DocsDocInterface;
}
