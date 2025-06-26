<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Abstract;

use DateTime;
use DateTimeInterface;
use Derafu\Config\Options;
use Derafu\Content\ContentAttachment;
use Derafu\Content\ContentAuthor;
use Derafu\Content\ContentHtmlTag;
use Derafu\Content\ContentHtmlTags;
use Derafu\Content\ContentSplFileInfo;
use Derafu\Content\ContentSplFileObject;
use Derafu\Content\ContentTag;
use Derafu\Content\Contract\ContentAttachmentInterface;
use Derafu\Content\Contract\ContentAuthorInterface;
use Derafu\Content\Contract\ContentHtmlTagsInterface;
use Derafu\Content\Contract\ContentItemInterface;
use Derafu\Support\Str;
use InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;

/**
 * Entity for the representation of a content.
 */
abstract class AbstractContentItem implements ContentItemInterface
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
     * Raw content of the content.
     *
     * @var string
     */
    private string $raw;

    /**
     * Metadata of the content.
     *
     * @var Options
     */
    private Options $metadata;

    /**
     * Schema of the metadata of the content.
     *
     * @var array
     */
    protected array $metadataSchema = [
        '__allowUndefinedKeys' => true,
    ];

    /**
     * Data of the content.
     *
     * This is the data of the content, without the metadata.
     *
     * @var string
     */
    private string $data;

    /**
     * URI of the content.
     *
     * @var string
     */
    private string $uri;

    /**
     * Slug of the content.
     *
     * @var string
     */
    private string $slug;

    /**
     * Route of the content.
     *
     * @var object
     */
    private object $route;

    /**
     * Title of the content.
     *
     * @var string
     */
    private string $title;

    /**
     * Description of the content.
     *
     * @var string
     */
    private string $description;

    /**
     * Keywords of the content.
     *
     * @var array
     */
    private array $keywords;

    /**
     * Preview of the content.
     *
     * @var string
     */
    private string $preview;

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
     * Tags of the content.
     *
     * @var array<ContentTag>
     */
    private array $tags;

    /**
     * Authors of the content.
     *
     * @var array<ContentAuthorInterface>
     */
    private array $authors;

    /**
     * Time to read the content.
     *
     * @var int
     */
    private int $time;

    /**
     * Draft of the content.
     *
     * @var bool
     */
    private bool $draft;

    /**
     * Unlisted of the content.
     *
     * @var bool
     */
    private bool $unlisted;

    /**
     * Created date of the content.
     *
     * @var DateTimeInterface
     */
    private DateTimeInterface $date;

    /**
     * Last update date of the content.
     *
     * @var DateTimeInterface
     */
    private DateTimeInterface $last_update;

    /**
     * Deprecated date of the content.
     *
     * @var DateTimeInterface|false
     */
    private DateTimeInterface|false $deprecated;

    /**
     * Level of the content.
     *
     * @var int
     */
    private int $level;

    /**
     * Pagination label of the content.
     *
     * @var string
     */
    private string $pagination_label;

    /**
     * Sidebar label of the content.
     *
     * @var string
     */
    private string $sidebar_label;

    /**
     * Sidebar position of the content.
     *
     * @var int
     */
    private int $sidebar_position;

    /**
     * Sidebar class name of the content.
     *
     * @var string|false
     */
    private string|false $sidebar_class_name;

    /**
     * Sidebar custom props of the content.
     *
     * @var array
     */
    private array $sidebar_custom_props;

    /**
     * Hide title of the content.
     *
     * @var bool
     */
    private bool $hide_title;

    /**
     * Hide table of contents of the content.
     *
     * @var bool
     */
    private bool $hide_table_of_contents;

    /**
     * Minimum heading level of the table of contents.
     *
     * @var int
     */
    private int $toc_min_heading_level;

    /**
     * Maximum heading level of the table of contents.
     *
     * @var int
     */
    private int $toc_max_heading_level;

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
     * Attachments of the content.
     *
     * @var array<string,ContentAttachmentInterface>
     */
    private array $attachments;

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
        if (!isset($this->raw)) {
            $this->raw = file_get_contents($this->info()->getRealPath());
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
                $this->metadata = new Options(
                    Yaml::parse($yaml),
                    $this->metadataSchema
                );
            } else {
                $this->metadata = new Options([], $this->metadataSchema);
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
    public function id(): string
    {
        return sprintf(
            '%s_%s_%s',
            $this->type(),
            $this->category(),
            str_replace('/', '_', $this->uri())
        );
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
    public function route(): object
    {
        if (!isset($this->route)) {
            $this->route = (object) [
                'name' => $this->type() . '_' . $this->category(),
                'params' => [$this->category() => $this->uri()],
            ];
        }

        return $this->route;
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
    public function description(): string
    {
        if (!isset($this->description)) {
            $this->description = $this->metadata('description')
                ?? $this->metadata('summary') // TODO: Remove this in the future.
                ?? $this->preview()
            ;
        }

        return $this->description;
    }

    /**
     * {@inheritDoc}
     */
    public function keywords(): array
    {
        if (!isset($this->keywords)) {
            $this->keywords = $this->metadata('keywords') ?? [];
        }

        return $this->keywords;
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
    public function tags(): array
    {
        if (!isset($this->tags)) {
            $this->tags = array_map(
                fn (string $tag) => new ContentTag($tag),
                $this->metadata('tags')?->all() ?? []
            );
        }

        return $this->tags;
    }

    /**
     * {@inheritDoc}
     */
    public function authors(): array
    {
        if (!isset($this->authors)) {
            $this->authors = [];
            $authors = $this->metadata('authors')
                ?? $this->metadata('author') // TODO: Remove this in the future.
                ?? []
            ;
            if (!empty($authors)) {
                if (is_string($authors)) {
                    $authors = [$authors];
                }
                foreach ($authors as $author) {
                    if (is_string($author)) {
                        $this->authors[] = new ContentAuthor($author);
                    } else {
                        $name = $author['name'] ?? $author[0];
                        $slug = $author['slug'] ?? $author[1] ?? null;
                        $this->authors[] = new ContentAuthor($name, $slug);
                    }
                }
            } else {
                $this->authors = [new ContentAuthor('Anonymous')];
            }
        }

        return $this->authors;
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
    public function draft(): bool
    {
        if (!isset($this->draft)) {
            $this->draft = $this->metadata('draft', false);
        }

        return $this->draft;
    }

    /**
     * {@inheritDoc}
     */
    public function unlisted(): bool
    {
        if (!isset($this->unlisted)) {
            $this->unlisted = $this->metadata('unlisted', false);
        }

        return $this->unlisted;
    }

    /**
     * {@inheritDoc}
     */
    public function date(): DateTimeInterface
    {
        if (!isset($this->date)) {
            $date =
                $this->metadata('date')
                ?? $this->metadata('created') // TODO: Remove this in the future.
                ?? null
            ;

            if ($date) {
                if (is_string($date)) {
                    $date = new DateTime($date);
                } elseif (is_int($date)) {
                    $date = new DateTime('@' . $date);
                }
            } else {
                $date = preg_match('/^(\d{4}-\d{2}-\d{2})-/', $this->name(), $matches)
                    ? new DateTime($matches[1])
                    : new DateTime('@' . $this->info()->getCTime())
                ;
            }

            $this->date = $date;
        }

        return $this->date;
    }

    /**
     * {@inheritDoc}
     */
    public function last_update(): DateTimeInterface
    {
        if (!isset($this->last_update)) {
            $last_update = $this->metadata('last_update');

            if ($last_update) {
                if (is_string($last_update)) {
                    $last_update = new DateTime($last_update);
                } elseif (is_int($last_update)) {
                    $last_update = new DateTime('@' . $last_update);
                }
            } else {
                $last_update = new DateTime('@' . $this->info()->getMTime());
            }

            $this->last_update = $last_update;
        }

        return $this->last_update;
    }

    /**
     * {@inheritDoc}
     */
    public function deprecated(): ?DateTimeInterface
    {
        if (!isset($this->deprecated)) {
            $deprecated = $this->metadata('deprecated', false);

            if ($deprecated) {
                if (is_string($deprecated)) {
                    $deprecated = new DateTime($deprecated);
                } elseif (is_int($deprecated)) {
                    $deprecated = new DateTime('@' . $deprecated);
                }
            }

            $this->deprecated = $deprecated;
        }

        return $this->deprecated ?: null;
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
     * {@inheritDoc}
     */
    public function pagination_label(): string
    {
        if (!isset($this->pagination_label)) {
            $this->pagination_label =
                $this->metadata('pagination_label')
                ?? $this->title()
            ;
        }

        return $this->pagination_label;
    }

    /**
     * {@inheritDoc}
     */
    public function sidebar_label(): string
    {
        if (!isset($this->sidebar_label)) {
            $this->sidebar_label = $this->metadata('sidebar_label', $this->title());
        }

        return $this->sidebar_label;
    }

    /**
     * {@inheritDoc}
     */
    public function sidebar_position(): int
    {
        if (!isset($this->sidebar_position)) {
            $this->sidebar_position =
                $this->metadata('sidebar_position')
                ?? $this->metadata('order') // TODO: Remove this in the future.
                ?? -1
            ;

            if ($this->sidebar_position === -1) {
                $maxEpoch = 253402300799; // 9999-12-31 23:59:59
                $this->sidebar_position = $maxEpoch - $this->date()->getTimestamp();
            }
        }

        return $this->sidebar_position;
    }

    /**
     * {@inheritDoc}
     */
    public function sidebar_class_name(): ?string
    {
        if (!isset($this->sidebar_class_name)) {
            $this->sidebar_class_name = $this->metadata('sidebar_class_name', false);
        }

        return $this->sidebar_class_name ?: null;
    }

    /**
     * {@inheritDoc}
     */
    public function sidebar_custom_props(): array
    {
        if (!isset($this->sidebar_custom_props)) {
            $this->sidebar_custom_props =
                $this->metadata('sidebar_custom_props')?->all()
                ?? []
            ;
        }

        return $this->sidebar_custom_props;
    }

    /**
     * {@inheritDoc}
     */
    public function hide_title(): bool
    {
        if (!isset($this->hide_title)) {
            $this->hide_title = $this->metadata('hide_title', false);
        }

        return $this->hide_title;
    }

    /**
     * {@inheritDoc}
     */
    public function hide_table_of_contents(): bool
    {
        if (!isset($this->hide_table_of_contents)) {
            $this->hide_table_of_contents = $this->metadata('hide_table_of_contents', false);
        }

        return $this->hide_table_of_contents;
    }

    /**
     * {@inheritDoc}
     */
    public function toc_min_heading_level(): int
    {
        if (!isset($this->toc_min_heading_level)) {
            $this->toc_min_heading_level = min(6, max(2, $this->metadata('toc_min_heading_level', 2)));
        }

        return $this->toc_min_heading_level;
    }

    /**
     * {@inheritDoc}
     */
    public function toc_max_heading_level(): int
    {
        if (!isset($this->toc_max_heading_level)) {
            $this->toc_max_heading_level = min(6, max(2, $this->metadata('toc_max_heading_level', 6)));
        }

        return $this->toc_max_heading_level;
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
    public function attachments(): array
    {
        if (!isset($this->attachments)) {
            $this->attachments = [];
            $files = glob($this->directory() . '/' . $this->name() . '/_attachments/*');
            foreach ($files as $file) {
                $this->attachments[basename($file)] = new ContentAttachment($file, $this);
            }
        }

        return $this->attachments;
    }

    /**
     * {@inheritDoc}
     */
    public function attachment(string $filename): ?ContentAttachmentInterface
    {
        return $this->attachments()[$filename] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public function links(): array
    {
        if (!isset($this->links)) {
            $urlBasePath = $this->urlBasePath ?? '/' . $this->type();

            $this->links = [
                'self' => ['href' => $urlBasePath . '/' . $this->uri()],
                'collection' => ['href' => $urlBasePath],
            ];
        }

        return $this->links;
    }

    /**
     * {@inheritDoc}
     *
     * By default this method adds the following HTML tags to the head of the
     * page:
     *
     *   - <meta name="description" content="..."/> and <meta property="og:description" content="..."/>
     *   - <meta property="og:image" content="..."/>
     *   - <meta name="keywords" content="...">
     *   - <meta name="author" content="...">
     */
    public function htmlTags(): ContentHtmlTagsInterface
    {
        $htmlTags = new ContentHtmlTags();

        // <meta name="description" content="..."/> and <meta property="og:description" content="..."/>
        if ($this->description()) {
            $htmlTags->addHeadTag(new ContentHtmlTag('meta', [
                'name' => 'description',
                'content' => $this->description(),
            ]));
            $htmlTags->addHeadTag(new ContentHtmlTag('meta', [
                'property' => 'og:description',
                'content' => $this->description(),
            ]));
        }

        // <meta property="og:image" content="..."/>
        if ($this->image()) {
            $htmlTags->addHeadTag(new ContentHtmlTag('meta', [
                'property' => 'og:image',
                'content' => $this->image(),
            ]));
        }

        // <meta name="keywords" content="...">
        if ($this->keywords() || $this->tags()) {
            $htmlTags->addHeadTag(new ContentHtmlTag('meta', [
                'name' => 'keywords',
                'content' => implode(
                    ', ',
                    array_merge(
                        $this->keywords(),
                        array_map(fn ($tag) => $tag->name(), $this->tags())
                    )
                ),
            ]));
        }

        // <meta name="author" content="...">
        if ($this->authors() || $this->tags()) {
            $htmlTags->addHeadTag(new ContentHtmlTag('meta', [
                'name' => 'author',
                'content' => implode(',', array_map(fn ($author) => $author->name(), $this->authors())),
            ]));
        }

        return $htmlTags;
    }

    /**
     * {@inheritDoc}
     */
    public function allowed(): bool
    {
        if (!$this->draft()) {
            return true;
        }

        if (isset($_SERVER['APP_ENV']) && $_SERVER['APP_ENV'] === 'dev') {
            return true;
        }

        if (isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] === 'localhost') {
            return true;
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id(),
            'checksum' => $this->checksum(),
            'type' => $this->type(),
            'category' => $this->category(),
            'uri' => $this->uri(),
            'slug' => $this->slug(),
            'title' => $this->title(),
            'description' => $this->description(),
            'keywords' => $this->keywords(),
            'preview' => $this->preview(),
            'image' => $this->image(),
            'video' => $this->video(),
            'tags' => $this->tags(),
            'authors' => $this->authors(),
            'time' => $this->time(),
            'draft' => $this->draft(),
            'unlisted' => $this->unlisted(),
            'date' => $this->date()->format('Y-m-d'),
            'last_update' => $this->last_update()->format('Y-m-d'),
            'deprecated' => $this->deprecated()?->format('Y-m-d'),
            'metadata' => $this->metadata(),
            'data' => $this->data(),
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
