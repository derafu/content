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
 * Faq registry interface.
 */
interface FaqRegistryInterface
{
    /**
     * Get all faqs.
     *
     * @return array<FaqInterface>
     */
    public function getFaqs(): array;

    /**
     * Get a faq by slug.
     *
     * @param string $slug Slug of the faq.
     * @return FaqInterface
     */
    public function getFaq(string $slug): FaqInterface;
}
