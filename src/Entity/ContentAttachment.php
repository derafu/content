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
use Derafu\Content\Contract\ContentItemInterface;
use Derafu\Content\Storage\ContentSplFileInfo;
use Derafu\Content\Storage\ContentSplFileObject;
use Derafu\Http\Enum\ContentType;
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
     * Parent of the attachment.
     *
     * @var ContentItemInterface
     */
    private ContentItemInterface $parent;

    /**
     * Constructor.
     *
     * @param ContentSplFileInfo|string $info Info of the content file.
     * @param ContentItemInterface $parent Parent of the attachment.
     */
    public function __construct(ContentSplFileInfo|string $info, ContentItemInterface $parent)
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

        $this->parent = $parent;
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

    /**
     * {@inheritDoc}
     */
    public function type(): ContentType
    {
        return ContentType::fromFilename($this->path());
    }

    /**
     * {@inheritDoc}
     */
    public function size(): int
    {
        return $this->info()->getSize();
    }

    /**
     * {@inheritDoc}
     */
    public function icon(): string
    {
        return match ($this->type()) {
            // Application types.
            ContentType::JSON => 'fa-regular fa-file-code fa-fw',
            ContentType::YAML => 'fa-regular fa-file-code fa-fw',
            ContentType::XML => 'fa-regular fa-file-code fa-fw',
            ContentType::FORM => 'fa-regular fa-file-code fa-fw',
            ContentType::PDF => 'fa-regular fa-file-pdf fa-fw',
            ContentType::ZIP => 'fa-regular fa-file-zipper fa-fw',
            ContentType::RAR => 'fa-regular fa-file-archive fa-fw',
            ContentType::TAR => 'fa-regular fa-file-archive fa-fw',
            ContentType::GZIP => 'fa-regular fa-file-archive fa-fw',
            ContentType::JAVASCRIPT => 'fa-regular fa-file-code fa-fw',
            ContentType::TYPESCRIPT => 'fa-regular fa-file-code fa-fw',
            ContentType::WASM => 'fa-regular fa-file-code fa-fw',
            ContentType::OCTET_STREAM => 'fa-regular fa-file-code fa-fw',

            // Application types RFC 7807.
            ContentType::PROBLEM_JSON => 'fa-regular fa-file-code fa-fw',
            ContentType::PROBLEM_XML => 'fa-regular fa-file-code fa-fw',

            // Text types.
            ContentType::HTML => 'fa-regular fa-file-code fa-fw',
            ContentType::PLAIN => 'fa-regular fa-file-code fa-fw',
            ContentType::CSS => 'fa-regular fa-file-code fa-fw',
            ContentType::CSV => 'fa-regular fa-file-code fa-fw',
            ContentType::MARKDOWN => 'fa-regular fa-file-code fa-fw',
            ContentType::XML_TEXT => 'fa-regular fa-file-code fa-fw',

            // Image types.
            ContentType::PNG => 'fa-regular fa-file-image fa-fw',
            ContentType::JPEG => 'fa-regular fa-file-image fa-fw',
            ContentType::GIF => 'fa-regular fa-file-image fa-fw',
            ContentType::SVG => 'fa-regular fa-file-image fa-fw',
            ContentType::WEBP => 'fa-regular fa-file-image fa-fw',
            ContentType::ICO => 'fa-regular fa-file-image fa-fw',
            ContentType::BMP => 'fa-regular fa-file-image fa-fw',
            ContentType::TIFF => 'fa-regular fa-file-image fa-fw',
            ContentType::AVIF => 'fa-regular fa-file-image fa-fw',

            // Audio types.
            ContentType::MP3 => 'fa-regular fa-file-audio fa-fw',
            ContentType::WAV => 'fa-regular fa-file-audio fa-fw',
            ContentType::OGG_AUDIO => 'fa-regular fa-file-audio fa-fw',
            ContentType::AAC => 'fa-regular fa-file-audio fa-fw',
            ContentType::FLAC => 'fa-regular fa-file-audio fa-fw',

            // Video types.
            ContentType::MP4 => 'fa-regular fa-file-video fa-fw',
            ContentType::WEBM => 'fa-regular fa-file-video fa-fw',
            ContentType::OGG_VIDEO => 'fa-regular fa-file-video fa-fw',
            ContentType::AVI => 'fa-regular fa-file-video fa-fw',
            ContentType::MOV => 'fa-regular fa-file-video fa-fw',

            // Font types.
            ContentType::WOFF => 'fa-regular fa-file-font fa-fw',
            ContentType::WOFF2 => 'fa-regular fa-file-font fa-fw',
            ContentType::TTF => 'fa-regular fa-file-font fa-fw',
            ContentType::OTF => 'fa-regular fa-file-font fa-fw',
            ContentType::EOT => 'fa-regular fa-file-font fa-fw',

            // Document types.
            ContentType::DOC => 'fa-regular fa-file-document fa-fw',
            ContentType::DOCX => 'fa-regular fa-file-document fa-fw',
            ContentType::XLS => 'fa-regular fa-file-document fa-fw',
            ContentType::XLSX => 'fa-regular fa-file-document fa-fw',
            ContentType::PPT => 'fa-regular fa-file-document fa-fw',
            ContentType::PPTX => 'fa-regular fa-file-document fa-fw',

            // Special types.
            ContentType::MULTIPART_FORM => 'fa-regular fa-file-code fa-fw',
            ContentType::EVENT_STREAM => 'fa-regular fa-file-code fa-fw',

            // Default.
            default => 'fa-regular fa-file fa-fw',
        };
    }

    /**
     * {@inheritDoc}
     */
    public function parent(): ContentItemInterface
    {
        return $this->parent;
    }
}
