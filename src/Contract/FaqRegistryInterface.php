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
 * FAQ registry interface.
 */
interface FaqRegistryInterface extends ContentRegistryInterface
{
    /**
     * Get FAQs filtered by criteria.
     *
     * @param array<string, mixed> $filters Filter criteria.
     * @return array<FaqInterface>
     */
    public function filter(array $filters = []): array;

    /**
     * Get a FAQ by URI.
     *
     * @param string $uri URI of the FAQ.
     * @return FaqInterface
     */
    public function get(string $uri): FaqInterface;
}
