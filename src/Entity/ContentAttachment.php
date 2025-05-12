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

use Derafu\Content\Contract\ContentAttachmentInterface;
use Derafu\Content\Storage\ContentSplFileInfo;
use Derafu\Content\Storage\ContentSplFileObject;
use InvalidArgumentException;

class ContentAttachment implements ContentAttachmentInterface
{
    /**
     * Representation of the attachment file info.
     *
     * @var ContentSplFileInfo
     */
    private ContentSplFileInfo $info;

    /**
     * Representation of the attachment file data.
     *
     * @var ContentSplFileObject
     */
    private ContentSplFileObject $file;

    /**
     * Constructor.
     *
     * @param ContentSplFileInfo|string $info Info of the content file.
     */
    public function __construct(ContentSplFileInfo|string $info)
    {
        if (is_string($info)) {
            $this->info = new ContentSplFileInfo($info);
            if (!$this->info->isFile() || !$this->info->isReadable()) {
                throw new InvalidArgumentException(sprintf(
                    'Path %s must be a readable attachment.',
                    $this->info->getRealPath()
                ));
            }
            $this->info->setFileClass(ContentSplFileObject::class);
        } else {
            $this->info = $info;
        }
    }

    /**
     * Get the info of the content.
     *
     * @return ContentSplFileInfo
     */
    protected function info(): ContentSplFileInfo
    {
        return $this->info;
    }

    /**
     * Get the file of the content.
     *
     * @return ContentSplFileObject
     */
    protected function file(): ContentSplFileObject
    {
        if (!isset($this->file)) {
            $file = $this->info()->openFile();
            assert($file instanceof ContentSplFileObject);
            $this->file = $file;
        }

        return $this->file;
    }

    /**
     * {@inheritDoc}
     */
    public function path(): string
    {
        return $this->info()->getRealPath();
    }

    /**
     * {@inheritDoc}
     */
    public function directory(): string
    {
        return $this->info()->getPath();
    }

    /**
     * {@inheritDoc}
     */
    public function name(): string
    {
        return $this->info()->getBasename('.' . $this->extension());
    }

    /**
     * {@inheritDoc}
     */
    public function extension(): string
    {
        return $this->info()->getExtension();
    }

    /**
     * {@inheritDoc}
     */
    public function checksum(): string
    {
        return $this->info()->getChecksum();
    }

    /**
     * {@inheritDoc}
     */
    public function raw(): string
    {
        return $this->file()->raw();
    }
}
