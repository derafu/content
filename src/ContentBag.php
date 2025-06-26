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

use Derafu\Container\Bag;
use Derafu\Container\Contract\BagInterface;
use Derafu\Content\Contract\ContentBagInterface;

/**
 * Content bag.
 */
class ContentBag implements ContentBagInterface
{
    /**
     * Bag.
     *
     * @var BagInterface
     */
    private BagInterface $bag;

    /**
     * Constructor.
     *
     * @param mixed $data Data.
     */
    public function __construct(mixed $data)
    {
        if ($data instanceof BagInterface) {
            $this->bag = $data;
        } else {
            $this->bag = new Bag($data);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $name, mixed $default = null): mixed
    {
        return $this->bag->get($name, $default);
    }
}
