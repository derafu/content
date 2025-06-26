<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Plugin\Faq\Contract;

use Derafu\Content\Contract\ContentRegistryInterface;

/**
 * FAQ registry interface.
 */
interface FaqRegistryInterface extends ContentRegistryInterface
{
    /**
     * Get FAQs filtered by criteria.
     *
     * @param array<string, mixed> $filters Filter criteria.
     * @return array<FaqQuestionInterface>
     */
    public function filter(array $filters = []): array;

    /**
     * Get a FAQ by URI.
     *
     * @param string $uri URI of the FAQ.
     * @return FaqQuestionInterface
     */
    public function get(string $uri): FaqQuestionInterface;
}
