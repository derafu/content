<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Contract;

/**
 * Docs registry interface.
 */
interface DocsRegistryInterface extends ContentRegistryInterface
{
    /**
     * Get docs filtered by criteria.
     *
     * @param array<string, mixed> $filters Filter criteria.
     * @return array<DocInterface>
     */
    public function filter(array $filters = []): array;

    /**
     * Get a doc by URI.
     *
     * @param string $uri URI of the doc.
     * @return DocInterface
     */
    public function get(string $uri): DocInterface;

    /**
     * Get the previous doc relative to the given URI.
     *
     * @param string $uri URI of the doc.
     * @param array<string, mixed> $filters Filter criteria.
     * @return DocInterface|null
     */
    public function previous(string $uri, array $filters = []): ?DocInterface;

    /**
     * Get the next doc relative to the given URI.
     *
     * @param string $uri URI of the doc.
     * @param array<string, mixed> $filters Filter criteria.
     * @return DocInterface|null
     */
    public function next(string $uri, array $filters = []): ?DocInterface;
}
