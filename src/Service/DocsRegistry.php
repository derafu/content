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
    public function get(string $uri): DocInterface
    {
        $doc = parent::get($uri);

        assert($doc instanceof DocInterface);

        return $doc;
    }

    /**
     * {@inheritDoc}
     */
    public function previous(string $uri, array $filters = []): ?DocInterface
    {
        $doc = parent::previous($uri, $filters);

        if ($doc === null) {
            return null;
        }

        assert($doc instanceof DocInterface);

        return $doc;
    }

    /**
     * {@inheritDoc}
     */
    public function next(string $uri, array $filters = []): ?DocInterface
    {
        $doc = parent::next($uri, $filters);

        if ($doc === null) {
            return null;
        }

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
