<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Plugin\Faq;

use Derafu\Content\Abstract\AbstractContentRegistry;
use Derafu\Content\Plugin\Faq\Contract\FaqQuestionInterface;
use Derafu\Content\Plugin\Faq\Contract\FaqRegistryInterface;

/**
 * FAQ registry.
 */
class FaqRegistry extends AbstractContentRegistry implements FaqRegistryInterface
{
    /**
     * {@inheritDoc}
     */
    public function get(string $uri): FaqQuestionInterface
    {
        $faq = parent::get($uri);

        assert($faq instanceof FaqQuestionInterface);

        return $faq;
    }

    /**
     * {@inheritDoc}
     */
    protected function getContentClass(): string
    {
        return FaqQuestion::class;
    }
}
