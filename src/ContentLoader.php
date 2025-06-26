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

use Derafu\Content\Contract\ContentLoaderInterface;

/**
 * Content loader from filesystem.
 */
class ContentLoader implements ContentLoaderInterface
{
    /**
     * Constructor.
     *
     * @param string $basePath Base path of the content.
     */
    public function __construct(
        private readonly string $basePath
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function scan(string $path, array $include, array $exclude): array
    {
        // Get the full path of the content.
        $fullPath = $this->basePath . '/' . rtrim($path, '/') . '/';

        // Get all matching files using glob patterns
        $files = $this->findFiles($fullPath, $include, $exclude);
        $files = array_map(fn ($file) => str_replace($fullPath, '', $file), $files);

        // Build hierarchy and return items.
        return $this->buildHierarchy($fullPath, $files);
    }

    /**
     * {@inheritDoc}
     */
    public function load(string $class, array $hierarchy): array
    {
        $items = [];

        foreach ($hierarchy as $files) {
            // Skip if no main file is found.
            // This will happen if a directory exists with content but there is
            // no a file content related (same name) to the directory. This is
            // the expected behavior for a content directory, because the
            // directory is not a content item and does not have the metadata
            // required by it self, all directories must have a content file
            // related to it to be indexed into the content registry.
            if (!isset($files[0])) {
                continue;
            }

            // Create the item from the first file.
            $self = $files[0];
            $item = new $class($self);

            // Add children to the item.
            unset($files[0]);
            if (!empty($files)) {
                $children = $this->load($class, $files);
                foreach ($children as $child) {
                    $item->addChild($child);
                }
            }

            // Add the item to the items array.
            $items[$item->slug()] = $item;
        }

        // Sort items.
        uasort($items, fn ($a, $b) => $a->sidebar_position() <=> $b->sidebar_position());

        return $items;
    }

    /**
     * Find files matching include patterns and not matching exclude patterns.
     *
     * @param string $path Base path of the content.
     * @param array<string> $include Include patterns.
     * @param array<string> $exclude Exclude patterns.
     * @return array
     */
    private function findFiles(string $path, array $include, array $exclude): array
    {
        // Silently return empty array if path is not a directory or is not
        // readable. This is useful to avoid errors when the path does not
        // exist in a default configuration.
        if (!is_dir($path) || !is_readable($path)) {
            return [];
        }

        // Get all included files.
        $includedFiles = [];
        foreach ($include as $pattern) {
            $includedFiles = array_merge(
                $includedFiles,
                $this->rglob($path, $pattern, GLOB_BRACE | GLOB_ERR)
            );
        }

        // Get all excluded files.
        $excludedFiles = [];
        foreach ($exclude as $pattern) {
            $excludedFiles = array_merge(
                $excludedFiles,
                $this->rglob($path, $pattern, GLOB_BRACE | GLOB_ERR)
            );
        }

        // Get all files that are included and not excluded.
        $files = array_unique(array_diff($includedFiles, $excludedFiles));

        // Sort the files by path name.
        sort($files);

        // Return the files.
        return $files;
    }

    /**
     * Build a hierarchical tree structure from flat file paths array.
     *
     * @param string $path Base path of the content.
     * @param array $files Array of file paths from a flat array.
     * @return array Hierarchical tree structure.
     */
    private function buildHierarchy(string $path, array $files): array
    {
        $tree = [];

        foreach ($files as $file) {
            $fileInfo = new ContentSplFileInfo($path . $file);
            $fileInfo->setFileClass(ContentSplFileObject::class);
            $fileUri = $fileInfo->getUri($path);

            $keys = explode('/', $fileUri);

            /** @var array<string, mixed> $current */
            $current = &$tree;

            foreach ($keys as $key) {
                if (!isset($current[$key])) {
                    $current[$key] = [];
                }
                $current = &$current[$key];
            }

            $current[] = $fileInfo;
        }

        return $tree;
    }

    /**
     * Recursive glob.
     *
     * @param string $path Base path.
     * @param string $pattern Pattern.
     * @param int $flags Flags.
     * @return array
     */
    private function rglob(string $path, string $pattern, int $flags = 0): array
    {
        $path = rtrim($path, '/') . '/';

        $files = glob($path . $pattern, $flags);

        $dirs = glob($path . '*', GLOB_ONLYDIR);
        if (is_array($dirs)) {
            foreach ($dirs as $dir) {
                $files = array_merge($files, $this->rglob($dir, $pattern, $flags));
            }
        }

        return $files;
    }
}
