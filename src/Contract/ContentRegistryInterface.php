<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Contract;

/**
 * Content registry interface.
 */
interface ContentRegistryInterface
{
    /**
     * Get a content by ID.
     *
     * @param string $uri URI of the content.
     * @return ContentItemInterface
     */
    public function get(string $uri): ContentItemInterface;

    /**
     * Get all content items.
     *
     * @return array<ContentItemInterface>
     */
    public function all(): array;

    /**
     * Walk through the items.
     *
     * @param callable $callback Callback.
     * @param ContentItemInterface|null $item Item from where to start walking.
     */
    public function walk(
        callable $callback,
        ?ContentItemInterface $item = null
    ): void;

    /**
     * Get content filtered by criteria.
     *
     * @param array<string, mixed> $filters Filter criteria.
     * @return array<ContentItemInterface>
     */
    public function filter(array $filters = []): array;

    /**
     * Get the flattened array of items filtered by criteria.
     *
     * @param array<string, mixed> $filters Filter criteria.
     * @return array<ContentItemInterface>
     */
    public function flatten(array $filters = []): array;

    /**
     * Get the previous content item relative to the given ID.
     *
     * @param string $uri URI of the content.
     * @param array<string, mixed> $filters Filter criteria.
     * @return ContentItemInterface|null
     */
    public function previous(string $uri, array $filters = []): ?ContentItemInterface;

    /**
     * Get the next content item relative to the given ID.
     *
     * @param string $uri URI of the content.
     * @param array<string, mixed> $filters Filter criteria.
     * @return ContentItemInterface|null
     */
    public function next(string $uri, array $filters = []): ?ContentItemInterface;

    /**
     * Get all authors.
     *
     * @return array<string, ContentAuthorInterface>
     */
    public function authors(): array;

    /**
     * Get all tags.
     *
     * @return array<string, ContentTagInterface>
     */
    public function tags(): array;
}
