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
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

/**
 * Context of the content website.
 */
class ContentContext implements ContentContextInterface
{
    /**
     * Default TTL, in seconds, of the default filesystem cache pool.
     *
     * Only applies when no cache pool is injected. It only bounds how long
     * an item may live in the absence of an explicit expiration; the
     * content registries set their own explicit TTL on every entry they
     * save (see AbstractContentRegistry).
     *
     * @var int
     */
    private const DEFAULT_CACHE_TTL = 60;

    /**
     * Cache pool used to avoid re-scanning and re-parsing the content on
     * every request.
     *
     * @var CacheItemPoolInterface
     */
    private CacheItemPoolInterface $cache;

    /**
     * Constructor.
     *
     * @param ContentConfigInterface $config The configuration of the content website.
     * @param CacheItemPoolInterface|null $cache Cache pool. Defaults to a
     * filesystem-backed pool (no external service required) if not given.
     */
    public function __construct(
        private ContentConfigInterface $config,
        ?CacheItemPoolInterface $cache = null
    ) {
        $this->cache = $cache ?? new FilesystemAdapter(
            'derafu_content',
            self::DEFAULT_CACHE_TTL,
            sys_get_temp_dir()
        );
    }

    /**
     * {@inheritDoc}
     */
    public function config(): ContentConfigInterface
    {
        return $this->config;
    }

    /**
     * {@inheritDoc}
     */
    public function cache(): CacheItemPoolInterface
    {
        return $this->cache;
    }
}
