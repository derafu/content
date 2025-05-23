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
 * Faq interface.
 */
interface FaqInterface extends ContentItemInterface
{
    /**
     * Get the question of the faq.
     *
     * @return string
     */
    public function question(): string;

    /**
     * Get the answer of the faq.
     *
     * @return string
     */
    public function answer(): string;
}
