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
    public function type(): string
    {
        return 'faq';
    }

    /**
     * {@inheritDoc}
     */
    public function category(): string
    {
        return 'question';
    }

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

    /**
     * {@inheritDoc}
     */
    public function links(): array
    {
        if (!isset($this->links)) {
            $urlBasePath = '/faq';

            $this->links = [
                'self' => ['href' => $urlBasePath . '/' . $this->uri()],
                'collection' => ['href' => $urlBasePath],
            ];
        }

        return $this->links;
    }
}
