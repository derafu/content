<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content;

use Derafu\Content\Contract\ContentConfigInterface;
use Derafu\Content\Contract\ContentContextInterface;

/**
 * Context of the content website.
 */
class ContentContext implements ContentContextInterface
{
    /**
     * Constructor.
     *
     * @param ContentConfigInterface $config The configuration of the content website.
     */
    public function __construct(private ContentConfigInterface $config)
    {
        $this->config = $config;
    }

    /**
     * {@inheritDoc}
     */
    public function config(): ContentConfigInterface
    {
        return $this->config;
    }
}
