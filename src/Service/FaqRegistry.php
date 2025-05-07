<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Service;

use Derafu\Content\Contract\FaqInterface;
use Derafu\Content\Contract\FaqRegistryInterface;
use Derafu\Content\Entity\Faq;

/**
 * FAQ registry.
 */
class FaqRegistry extends ContentRegistry implements FaqRegistryInterface
{
    /**
     * {@inheritDoc}
     */
    public function get(string $slug): FaqInterface
    {
        $faq = parent::get($slug);

        assert($faq instanceof FaqInterface);

        return $faq;
    }

    /**
     * {@inheritDoc}
     */
    protected function getContentClass(): string
    {
        return Faq::class;
    }
}
