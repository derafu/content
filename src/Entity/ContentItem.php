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
use Derafu\Content\Contract\ContentAuthorInterface;
use Derafu\Content\Contract\ContentItemInterface;
use Derafu\Support\Str;
use InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;

/**
 * Entity for the representation of a content.
 */
class ContentItem implements ContentItemInterface
{
    /**
     * Reading speed of the content.
     *
     * This is the number of words per minute expected to read the content.
     *
     * @var int
     */
    private const READING_SPEED = 200;

    /**
     * Path of the content.
     *
     * @var string
     */
    private string $path;

    /**
     * Directory of the content.
     *
     * @var string
     */
    private string $directory;

    /**
     * Name of the content.
     *
     * @var string
     */
    private string $name;

    /**
     * Extension of the content.
     *
     * @var string
     */
    private string $extension;

    /**
     * Checksum of the content.
     *
     * @var string
     */
    private string $checksum;

    /**
     * Raw content of the content.
     *
     * This is the whole content of the file content, including the metadata.
     *
     * @var string
     */
    private string $raw;

    /**
     * Metadata of the content.
     *
     * This is the metadata of the content, without the data.
     *
     * @var array
     */
    private array $metadata;

    /**
     * Data of the content.
     *
     * This is the data of the content, without the metadata.
     *
     * @var string
     */
    private string $data;

    /**
     * Slug of the content.
     *
     * @var string
     */
    private string $slug;

    /**
     * Title of the content.
     *
     * @var string
     */
    private string $title;

    /**
     * Summary of the content.
     *
     * @var string
     */
    private string $summary;

    /**
     * Preview of the content.
     *
     * @var string
     */
    private string $preview;

    /**
     * Created date of the content.
     *
     * @var DateTimeImmutable
     */
    private DateTimeImmutable $created;

    /**
     * Modified date of the content.
     *
     * @var DateTimeImmutable
     */
    private DateTimeImmutable $modified;

    /**
     * Published date of the content.
     *
     * @var DateTimeImmutable
     */
    private DateTimeImmutable $published;

    /**
     * Deprecated date of the content.
     *
     * @var DateTimeImmutable|false
     */
    private DateTimeImmutable|false $deprecated;

    /**
     * Tags of the content.
     *
     * @var array<ContentTag>
     */
    private array $tags;

    /**
     * Image of the content.
     *
     * @var string|false
     */
    private string|false $image;

    /**
     * Author of the content.
     *
     * @var ContentAuthor|false
     */
    private ContentAuthor|false $author;

    /**
     * Time to read the content.
     *
     * @var int
     */
    private int $time;

    /**
     * Links of the content.
     *
     * @var array
     */
    private array $links;

    /**
     * Constructor.
     *
     * @param string|array $path Path of the content.
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
                'checksum',
                'raw',
                'metadata',
                'data',
                'slug',
                'title',
                'summary',
                'preview',
                'created',
                'modified',
                'published',
                'deprecated',
                'tags',
                'image',
                'author',
                'time',
                'links',
            ];

            foreach ($attributes as $attribute) {
                if (isset($path[$attribute])) {
                    $this->{$attribute} = $path[$attribute];
                }
            }
        }

        if (!is_string($this->path) || !is_readable($this->path)) {
            throw new InvalidArgumentException(sprintf(
                'Path %s must be a readable file content.',
                $this->path
            ));
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
    public function raw(): string
    {
        if (!isset($this->raw)) {
            $this->raw = file_get_contents($this->path);
        }

        return $this->raw;
    }

    /**
     * {@inheritDoc}
     */
    public function metadata(?string $key = null, mixed $default = null): mixed
    {
        if (!isset($this->metadata)) {
            $data = $this->raw();
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
    public function data(): string
    {
        if (!isset($this->data)) {
            $this->data = trim(preg_replace('/^---\n(.*?)\n---\n/s', '', $this->raw()));
        }

        return $this->data;
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
    public function title(): string
    {
        if (!isset($this->title)) {
            $this->title = $this->metadata('title') ?? $this->name();
        }

        return $this->title;
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

            $content = $this->data();

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
    public function created(): DateTimeImmutable
    {
        if (!isset($this->created)) {
            $this->created = new DateTimeImmutable('@' . filectime($this->path()));
        }

        return $this->created;
    }

    /**
     * {@inheritDoc}
     */
    public function modified(): DateTimeImmutable
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
    public function deprecated(): ?DateTimeImmutable
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
            $this->tags = array_map(fn (string $tag) => new ContentTag($tag), $this->metadata('tags') ?? []);
        }

        return $this->tags;
    }

    /**
     * {@inheritDoc}
     */
    public function image(): ?string
    {
        if (!isset($this->image)) {
            $this->image = $this->metadata()['image'] ?? false;
        }

        return $this->image ?: null;
    }

    /**
     * {@inheritDoc}
     */
    public function author(): ?ContentAuthorInterface
    {
        if (!isset($this->author)) {
            $author = $this->metadata()['author'] ?? null;

            if (empty($author)) {
                $this->author = false;
            } elseif (is_string($author)) {
                $this->author = new ContentAuthor($author);
            } else {
                $name = $author['name'] ?? $author[0];
                $slug = $author['slug'] ?? $author[1] ?? null;
                $this->author = new ContentAuthor($name, $slug);
            }
        }

        return $this->author ?: null;
    }

    /**
     * {@inheritDoc}
     */
    public function time(): int
    {
        if (!isset($this->time)) {
            // Get the time from the metadata (accurate).
            $this->time = $this->metadata('time', -1);

            // If the time is not set, calculate it from the content (rough).
            if ($this->time === -1) {
                $content = $this->data();
                $wordCount = str_word_count(strip_tags($content));
                $readingSpeed = self::READING_SPEED;
                $this->time = (int) max(1, ceil($wordCount / $readingSpeed));
            }
        }

        return $this->time;
    }

    /**
     * {@inheritDoc}
     */
    public function links(): array
    {
        if (!isset($this->links)) {
            $this->links = [
                'self' => ['href' => '/blog/' . $this->slug()],
                'collection' => ['href' => '/blog'],
            ];
        }

        return $this->links;
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
            'checksum' => $this->checksum,
            'raw' => $this->raw,
            'data' => $this->data,
            'metadata' => $this->metadata,
            'slug' => $this->slug,
            'summary' => $this->summary,
            'preview' => $this->preview,
            'created' => $this->created->format('Y-m-d'),
            'modified' => $this->modified->format('Y-m-d'),
            'published' => $this->published->format('Y-m-d'),
            'deprecated' => $this->deprecated ? $this->deprecated->format('Y-m-d') : null,
            'tags' => $this->tags,
            'title' => $this->title(),
            'author' => $this->author(),
            'image' => $this->image(),
            'time' => $this->time(),
            '_links' => $this->links(),
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
