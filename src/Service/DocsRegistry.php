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

use Derafu\Content\Contract\DocInterface;
use Derafu\Content\Contract\DocsRegistryInterface;
use Derafu\Content\Entity\Doc;

/**
 * Docs registry.
 */
class DocsRegistry extends ContentRegistry implements DocsRegistryInterface
{
    /**
     * {@inheritDoc}
     */
    public function get(string $slug): DocInterface
    {
        $doc = parent::get($slug);

        assert($doc instanceof DocInterface);

        return $doc;
    }

    /**
     * {@inheritDoc}
     */
    protected function getContentClass(): string
    {
        return Doc::class;
    }
}
