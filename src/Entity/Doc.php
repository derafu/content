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
     * {@inheritDoc}
     */
    public function links(): array
    {
        if (!isset($this->links)) {
            $urlBasePath = '/docs';

            $this->links = [
                'self' => ['href' => $urlBasePath . '/' . $this->slug()],
                'collection' => ['href' => $urlBasePath],
            ];
        }

        return $this->links;
    }
}
