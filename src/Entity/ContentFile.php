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

use DateTimeImmutable;
use DateTimeInterface;
use Derafu\Content\Contract\ContentFileInterface;
use Derafu\Support\Str;
use InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;

/**
 * Entity for the representation of a content file.
 */
class ContentFile implements ContentFileInterface
{
    /**
     * Path of the content file.
     *
     * @var string
     */
    private string $path;

    /**
     * Directory of the content file.
     *
     * @var string
     */
    private string $directory;

    /**
     * Name of the content file.
     *
     * @var string
     */
    private string $name;

    /**
     * Extension of the content file.
     *
     * @var string
     */
    private string $extension;

    /**
     * Slug of the content file.
     *
     * @var string
     */
    private string $slug;

    /**
     * Data of the content file.
     *
     * @var string
     */
    private string $data;

    /**
     * Metadata of the content file.
     *
     * @var array
     */
    private array $metadata;

    /**
     * Content of the content file.
     *
     * This is the payload or body of the file, without the metadata.
     *
     * @var string
     */
    private string $content;

    /**
     * Summary of the content file.
     *
     * @var string
     */
    private string $summary;

    /**
     * Preview of the content file.
     *
     * @var string
     */
    private string $preview;

    /**
     * Checksum of the content file.
     *
     * @var string
     */
    private string $checksum;

    /**
     * Created date of the content file.
     *
     * @var DateTimeInterface
     */
    private DateTimeInterface $created;

    /**
     * Modified date of the content file.
     *
     * @var DateTimeInterface
     */
    private DateTimeInterface $modified;

    /**
     * Published date of the content file.
     *
     * @var DateTimeInterface
     */
    private DateTimeInterface $published;

    /**
     * Deprecated date of the content file.
     *
     * @var DateTimeInterface|false
     */
    private DateTimeInterface|false $deprecated;

    /**
     * Tags of the content file.
     *
     * @var array<ContentTag>
     */
    private array $tags;

    /**
     * Constructor.
     *
     * @param string|array $path Path of the content file.
     */
    public function __construct(string|array $path)
    {
        if (is_string($path)) {
            $this->path = $path;
        } else {
            $attributes = [
                'path',
                'directory',
                'name',
                'extension',
                'slug',
                'data',
                'metadata',
                'content',
                'preview',
                'checksum',
                'created',
                'modified',
                'published',
                'deprecated',
                'tags',
            ];

            foreach ($attributes as $attribute) {
                if (isset($path[$attribute])) {
                    $this->{$attribute} = $path[$attribute];
                }
            }
        }

        if (!is_string($this->path) || !is_readable($this->path)) {
            throw new InvalidArgumentException('Path must be a string and readable.');
        }
    }


    /**
     * {@inheritDoc}
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * {@inheritDoc}
     */
    public function directory(): string
    {
        if (!isset($this->directory)) {
            $this->directory = dirname($this->path);
        }

        return $this->directory;
    }

    /**
     * {@inheritDoc}
     */
    public function name(): string
    {
        if (!isset($this->name)) {
            $this->name = pathinfo($this->path, PATHINFO_FILENAME);
        }

        return $this->name;
    }

    /**
     * {@inheritDoc}
     */
    public function extension(): string
    {
        if (!isset($this->extension)) {
            $this->extension = pathinfo($this->path, PATHINFO_EXTENSION);
        }

        return $this->extension;
    }

    /**
     * {@inheritDoc}
     */
    public function slug(): string
    {
        if (!isset($this->slug)) {
            $this->slug = $this->metadata('slug') ?? Str::slug($this->name());
        }

        return $this->slug;
    }

    /**
     * {@inheritDoc}
     */
    public function data(): string
    {
        if (!isset($this->data)) {
            $this->data = file_get_contents($this->path);
        }

        return $this->data;
    }

    /**
     * {@inheritDoc}
     */
    public function metadata(?string $key = null, mixed $default = null): mixed
    {
        if (!isset($this->metadata)) {
            $data = $this->data();
            if (preg_match('/^---\n(.*?)\n---\n/s', $data, $matches)) {
                $yaml = $matches[1];
                $this->metadata = Yaml::parse($yaml);
            } else {
                $this->metadata = [];
            }
        }

        if (is_null($key)) {
            return $this->metadata;
        }

        return $this->metadata[$key] ?? $default;
    }

    /**
     * {@inheritDoc}
     */
    public function content(): string
    {
        if (!isset($this->content)) {
            $this->content = trim(preg_replace('/^---\n(.*?)\n---\n/s', '', $this->data()));
        }

        return $this->content;
    }

    /**
     * {@inheritDoc}
     */
    public function summary(): string
    {
        if (!isset($this->summary)) {
            $this->summary = $this->metadata('summary') ?? $this->preview();
        }

        return $this->summary;
    }

    /**
     * {@inheritDoc}
     */
    public function preview(int $maxLength = 300): string
    {
        if (!isset($this->preview)) {

            $content = $this->content();

            // Divide the content into paragraphs by double line break.
            $paragraphs = preg_split("/\n\s*\n/", $content, 2);
            $preview = $paragraphs[0] ?? '';

            // Clean titles and lists from preview.
            $preview = preg_replace('/^(#|\*|-|\d+\.)\s*/m', '', $preview);

            // If the preview is shorter than the limit, return it complete.
            if (mb_strlen($preview) <= $maxLength) {
                $this->preview = trim($preview);
            } else {
                // Search the first space after maxLength to not cut words.
                $cutPosition = mb_strpos($preview, ' ', $maxLength);

                // If no space is found, cut at the exact limit.
                if ($cutPosition === false) {
                    $cutPosition = $maxLength;
                }

                // Cut the paragraph safely.
                $preview = mb_substr($preview, 0, $cutPosition);

                // Ensure the preview does not end with an incomplete Markdown
                // character.
                $preview = rtrim($preview, " `*_[");

                $this->preview = trim($preview) . '...';
            }
        }

        return $this->preview;
    }

    /**
     * {@inheritDoc}
     */
    public function checksum(): string
    {
        if (!isset($this->checksum)) {
            $this->checksum = hash_file('sha256', $this->path());
        }

        return $this->checksum;
    }

    /**
     * {@inheritDoc}
     */
    public function created(): DateTimeInterface
    {
        if (!isset($this->created)) {
            $this->created = new DateTimeImmutable('@' . filectime($this->path()));
        }

        return $this->created;
    }

    /**
     * {@inheritDoc}
     */
    public function modified(): DateTimeInterface
    {
        if (!isset($this->modified)) {
            $this->modified = new DateTimeImmutable('@' . filemtime($this->path()));
        }

        return $this->modified;
    }

    /**
     * {@inheritDoc}
     */
    public function published(): DateTimeImmutable
    {
        if (!isset($this->published)) {
            if (preg_match('/^(\d{4}-\d{2}-\d{2})-/', $this->name(), $matches)) {
                $this->published = new DateTimeImmutable($matches[1]);
            } else {
                $this->published = $this->created();
            }
        }

        return $this->published;
    }

    /**
     * {@inheritDoc}
     */
    public function deprecated(): ?DateTimeInterface
    {
        if (!isset($this->deprecated)) {
            $this->deprecated = $this->metadata('deprecated', false);
        }

        return $this->deprecated ?: null;
    }

    /**
     * {@inheritDoc}
     */
    public function tags(): array
    {
        if (!isset($this->tags)) {
            $this->tags = array_map(function (string $tag) {
                return new ContentTag($tag);
            }, $this->metadata('tags') ?? []);
        }

        return $this->tags;
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'directory' => $this->directory,
            'name' => $this->name,
            'extension' => $this->extension,
            'slug' => $this->slug,
            'data' => $this->data,
            'metadata' => $this->metadata,
            'content' => $this->content,
            'summary' => $this->summary,
            'preview' => $this->preview,
            'checksum' => $this->checksum,
            'created' => $this->created->format('Y-m-d'),
            'modified' => $this->modified->format('Y-m-d'),
            'published' => $this->published->format('Y-m-d'),
            'deprecated' => $this->deprecated ? $this->deprecated->format('Y-m-d') : null,
            'tags' => $this->tags,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * {@inheritDoc}
     */
    public function __toString(): string
    {
        return $this->data;
    }
}
