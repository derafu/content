<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Plugin\Docs;

use Derafu\Content\Abstract\AbstractContentItem;
use Derafu\Content\Plugin\Docs\Contract\DocsDocInterface;

/**
 * Class that represents a doc.
 */
class DocsDoc extends AbstractContentItem implements DocsDocInterface
{
    /**
     * {@inheritDoc}
     */
    public function type(): string
    {
        return 'docs';
    }

    /**
     * {@inheritDoc}
     */
    public function category(): string
    {
        return 'doc';
    }

    /**
     * {@inheritDoc}
     */
    public function parent(): ?DocsDocInterface
    {
        $parent = parent::parent();

        if ($parent === null) {
            return null;
        }

        assert($parent instanceof DocsDocInterface);

        return $parent;
    }

    /**
     * {@inheritDoc}
     */
    public function links(): array
    {
        if (!isset($this->links)) {
            $urlBasePath = '/docs';

            $this->links = [
                'self' => ['href' => $urlBasePath . '/' . $this->uri()],
                'collection' => ['href' => $urlBasePath],
            ];
        }

        return $this->links;
    }
}
