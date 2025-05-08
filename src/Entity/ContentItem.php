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
use Derafu\Content\Storage\ContentSplFileInfo;
use Derafu\Content\Storage\ContentSplFileObject;
use Derafu\Content\ValueObject\ContentAuthor;
use Derafu\Content\ValueObject\ContentTag;
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
     * Representation of the content file info.
     *
     * @var ContentSplFileInfo
     */
    private ContentSplFileInfo $info;

    /**
     * Representation of the content file data.
     *
     * @var ContentSplFileObject
     */
    private ContentSplFileObject $file;

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
     * URI of the content.
     *
     * @var string
     */
    private string $uri;

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
     * Order of the content.
     *
     * @var int
     */
    private int $order;

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
     * Video of the content.
     *
     * @var string|false
     */
    private string|false $video;

    /**
     * Author of the content.
     *
     * @var ContentAuthorInterface|false
     */
    private ContentAuthorInterface|false $author;

    /**
     * Time to read the content.
     *
     * @var int
     */
    private int $time;

    /**
     * Level of the content.
     *
     * @var int
     */
    private int $level;

    /**
     * Ancestors of the content.
     *
     * @var array<ContentItemInterface>
     */
    private array $ancestors;

    /**
     * Parent of the content.
     *
     * @var ContentItemInterface|null
     */
    private ?ContentItemInterface $parent;

    /**
     * Children of the content.
     *
     * @var array<string,ContentItemInterface>
     */
    private array $children;

    /**
     * Links of the content.
     *
     * @var array
     */
    protected array $links;

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
                    'Path %s must be a readable file content.',
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
    public function uri(): string
    {
        if (!isset($this->uri)) {
            $uriParts = [];
            $content = $this;
            do {
                $uriParts[] = $content->slug();
                $content = $content->parent();
            } while ($content);

            $this->uri = implode('/', array_reverse($uriParts));
        }

        return $this->uri;
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
            $created = $this->metadata('created');

            if ($created) {
                if (is_string($created)) {
                    $created = new DateTimeImmutable($created);
                } elseif (is_int($created)) {
                    $created = new DateTimeImmutable('@' . $created);
                }
            } else {
                $created = new DateTimeImmutable('@' . $this->info()->getCTime());
            }

            $this->created = $created;
        }

        return $this->created;
    }

    /**
     * {@inheritDoc}
     */
    public function modified(): DateTimeImmutable
    {
        if (!isset($this->modified)) {
            $modified = $this->metadata('modified');

            if ($modified) {
                if (is_string($modified)) {
                    $modified = new DateTimeImmutable($modified);
                } elseif (is_int($modified)) {
                    $modified = new DateTimeImmutable('@' . $modified);
                }
            } else {
                $modified = new DateTimeImmutable('@' . $this->info()->getMTime());
            }

            $this->modified = $modified;
        }

        return $this->modified;
    }

    /**
     * {@inheritDoc}
     */
    public function published(): DateTimeImmutable
    {
        if (!isset($this->published)) {
            $published = $this->metadata('published');

            if ($published) {
                if (is_string($published)) {
                    $published = new DateTimeImmutable($published);
                } elseif (is_int($published)) {
                    $published = new DateTimeImmutable('@' . $published);
                }
            } else {
                $published = preg_match('/^(\d{4}-\d{2}-\d{2})-/', $this->name(), $matches)
                    ? new DateTimeImmutable($matches[1])
                    : $this->created()
                ;
            }

            $this->published = $published;
        }

        return $this->published;
    }

    /**
     * {@inheritDoc}
     */
    public function deprecated(): ?DateTimeImmutable
    {
        if (!isset($this->deprecated)) {
            $deprecated = $this->metadata('deprecated', false);

            if ($deprecated) {
                if (is_string($deprecated)) {
                    $deprecated = new DateTimeImmutable($deprecated);
                } elseif (is_int($deprecated)) {
                    $deprecated = new DateTimeImmutable('@' . $deprecated);
                }
            }

            $this->deprecated = $deprecated;
        }

        return $this->deprecated ?: null;
    }

    /**
     * {@inheritDoc}
     */
    public function order(): int
    {
        if (!isset($this->order)) {
            $this->order = $this->metadata('order', -1);

            if ($this->order === -1) {
                $maxEpoch = 253402300799; // 9999-12-31 23:59:59
                $this->order = $maxEpoch - $this->published()->getTimestamp();
            }
        }

        return $this->order;
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
            $this->image = $this->metadata('image', false);
        }

        return $this->image ?: null;
    }

    /**
     * {@inheritDoc}
     */
    public function video(): ?string
    {
        if (!isset($this->video)) {
            $this->video = $this->metadata('video', false);

            if ($this->video) {
                $url = parse_url($this->video);
                if (isset($url['host']) && str_ends_with($url['host'], 'youtube.com')) {
                    parse_str($url['query'] ?? '', $query);
                    if (isset($query['v'])) {
                        $videoId = $query['v'];
                        $this->video = "https://www.youtube.com/embed/$videoId";
                    }
                }
            }
        }

        return $this->video ?: null;
    }

    /**
     * {@inheritDoc}
     */
    public function author(): ?ContentAuthorInterface
    {
        if (!isset($this->author)) {
            $author = $this->metadata('author');

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
    public function level(): int
    {
        if (!isset($this->level)) {
            $this->level = $this->parent() ? $this->parent()->level() + 1 : 1;
        }

        return $this->level;
    }

    /**
     * Get the ancestors of the content.
     *
     * @return array<ContentItemInterface>
     */
    public function ancestors(): array
    {
        if (!isset($this->ancestors)) {
            $ancestors = [];
            $content = $this;
            while ($content = $content->parent()) {
                $ancestors[] = $content;
            }
            $this->ancestors = array_reverse($ancestors);
        }

        return $this->ancestors;
    }

    /**
     * {@inheritDoc}
     */
    public function setParent(ContentItemInterface $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function parent(): ?ContentItemInterface
    {
        return $this->parent ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public function addChild(ContentItemInterface $child): static
    {
        if (!isset($this->children)) {
            $this->children = [];
        }

        $child->setParent($this);

        $this->children[$child->slug()] = $child;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function children(): array
    {
        return $this->children ?? [];
    }

    /**
     * {@inheritDoc}
     */
    public function links(): array
    {
        if (!isset($this->links)) {
            $urlBasePath = $this->urlBasePath ?? '/content';

            $this->links = [
                'self' => ['href' => $urlBasePath . '/' . $this->slug()],
                'collection' => ['href' => $urlBasePath],
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
            'checksum' => $this->checksum(),
            'slug' => $this->slug(),
            'uri' => $this->uri(),
            'title' => $this->title(),
            'author' => $this->author(),
            'image' => $this->image(),
            'video' => $this->video(),
            'time' => $this->time(),
            'summary' => $this->summary(),
            'preview' => $this->preview(),
            'tags' => $this->tags(),
            'metadata' => $this->metadata(),
            'data' => $this->data(),
            'created' => $this->created()->format('Y-m-d'),
            'modified' => $this->modified()->format('Y-m-d'),
            'published' => $this->published()->format('Y-m-d'),
            'deprecated' => $this->deprecated()?->format('Y-m-d'),
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
