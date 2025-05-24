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

use Derafu\Content\Contract\DocInterface;

/**
 * Class that represents a doc.
 */
class Doc extends ContentItem implements DocInterface
{
    /**
     * Default metadata.
     *
     * @var array<string, mixed>
     */
    protected array $defaultMetadata = [
        'show_toc' => true,
        'show_children' => false,
    ];

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
