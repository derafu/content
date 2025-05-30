<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Plugin\Docs;

use Derafu\Content\Abstract\AbstractContentRegistry;
use Derafu\Content\Plugin\Docs\Contract\DocsDocInterface;
use Derafu\Content\Plugin\Docs\Contract\DocsRegistryInterface;

/**
 * Docs registry.
 */
class DocsRegistry extends AbstractContentRegistry implements DocsRegistryInterface
{
    /**
     * {@inheritDoc}
     */
    public function get(string $uri): DocsDocInterface
    {
        $doc = parent::get($uri);

        assert($doc instanceof DocsDocInterface);

        return $doc;
    }

    /**
     * {@inheritDoc}
     */
    public function previous(string $uri, array $filters = []): ?DocsDocInterface
    {
        $doc = parent::previous($uri, $filters);

        if ($doc === null) {
            return null;
        }

        assert($doc instanceof DocsDocInterface);

        return $doc;
    }

    /**
     * {@inheritDoc}
     */
    public function next(string $uri, array $filters = []): ?DocsDocInterface
    {
        $doc = parent::next($uri, $filters);

        if ($doc === null) {
            return null;
        }

        assert($doc instanceof DocsDocInterface);

        return $doc;
    }

    /**
     * {@inheritDoc}
     */
    protected function getContentClass(): string
    {
        return DocsDoc::class;
    }
}
