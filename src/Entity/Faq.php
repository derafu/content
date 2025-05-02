<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Entity;

use Derafu\Content\Contract\FaqInterface;

/**
 * Class that represents a FAQ.
 */
class Faq extends ContentItem implements FaqInterface
{
    /**
     * {@inheritDoc}
     */
    public function question(): string
    {
        return $this->title();
    }

    /**
     * {@inheritDoc}
     */
    public function answer(): string
    {
        return $this->data();
    }
}
