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
     * Get a doc by slug.
     *
     * @param string $slug Slug of the doc.
     * @return DocInterface
     */
    public function get(string $slug): DocInterface;
}
