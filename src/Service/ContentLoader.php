<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Service;

use Derafu\Content\Contract\ContentLoaderInterface;
use Derafu\Content\Entity\ContentItem;
use Derafu\Content\Storage\ContentSplFileInfo;
use Derafu\Content\Storage\ContentSplFileObject;
use DirectoryIterator;

/**
 * Content loader from filesystem.
 */
class ContentLoader implements ContentLoaderInterface
{
    /**
     * {@inheritDoc}
     */
    public function load(
        string $path,
        array $extensions = ['md'],
        string $class = ContentItem::class
    ): array {
        $items = [];

        // Silently return empty array if path is not a directory or is not
        // readable. This is useful to avoid errors when the path does not
        // exist in a default configuration.
        if (!is_dir($path) || !is_readable($path)) {
            return $items;
        }

        // Create a directory iterator with custom classes for file info and
        // file object. This class provide usefull methods related to the file
        // content in this package.
        $directoryIterator = new DirectoryIterator($path);
        $directoryIterator->setInfoClass(ContentSplFileInfo::class);

        // Iterate over the directory iterator and get the files.
        foreach ($directoryIterator as $file) {
            // Skip dot files and non-readable files.
            if ($file->isDot() || !$file->isReadable()) {
                continue;
            }

            if ($file->isFile()) {
                // Create a content item from the file.
                $fileInfo = $file->getFileInfo();
                $fileInfo->setFileClass(ContentSplFileObject::class);
                $item = new $class($fileInfo);

                // Search for children in the directory with the same name.
                $childrenPath = $file->getPathInfo()->getRealPath() . '/' . $item->name() . '/';
                $children = $this->load($childrenPath, $extensions, $class);
                if (!empty($children)) {
                    foreach ($children as $child) {
                        $item->addChild($child);
                    }
                }

                // Add the item to the items array.
                $items[$item->slug()] = $item;
            }
        }

        // Sort items.
        uasort($items, fn ($a, $b) => $a->order() <=> $b->order());

        // Return items.
        return $items;
    }
}
