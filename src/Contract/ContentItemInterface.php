<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Contract;

use DateTimeInterface;
use JsonSerializable;
use Stringable;

/**
 * Interface for content items.
 *
 * A content item is a single item of content, such as a document, a page, a
 * blog post, etc. It's used to represent the content of a single item in the
 * content data.
 */
interface ContentItemInterface extends JsonSerializable, Stringable
{
    /**
     * Get the type of the content.
     *
     * @return string
     */
    public function type(): string;

    /**
     * Get the category of the content.
     *
     * @return string
     */
    public function category(): string;

    /**
     * Get the path of the content.
     *
     * @return string
     */
    public function path(): string;

    /**
     * Get the directory of the content.
     *
     * @return string
     */
    public function directory(): string;

    /**
     * Get the name of the content, without the file extension.
     *
     * @return string
     */
    public function name(): string;

    /**
     * Get the file extension of the content.
     *
     * @return string
     */
    public function extension(): string;

    /**
     * Calculate the checksum of the file content.
     *
     * @return string
     */
    public function checksum(): string;

    /**
     * Get the raw data of the content.
     *
     * This is the whole content of the file content, including the metadata.
     *
     * @return string
     */
    public function raw(): string;

    /**
     * Get the metadata of the content.
     *
     * This is the metadata of the content, without the data.
     *
     * @param string|null $key Key of the metadata to get.
     * @param mixed $default Default value to return if the key is not found.
     * @return mixed
     */
    public function metadata(?string $key = null, mixed $default = null): mixed;

    /**
     * Get the data of the content.
     *
     * This is the data of the content, without the metadata.
     *
     * @return string
     */
    public function data(): string;

    /**
     * The unique identifier of the content item.
     *
     * @return string
     */
    public function id(): string;

    /**
     * Get the URI of the content.
     *
     * @return string
     */
    public function uri(): string;

    /**
     * Get the slug of the content.
     *
     * @return string
     */
    public function slug(): string;

    /**
     * Get the route of the content.
     *
     * @return object
     */
    public function route(): object;

    /**
     * The text title of your document. Used for the page metadata and as a
     * fallback value in multiple places (sidebar, next/previous buttons...).
     * Automatically added at the top of your doc if it does not contain any
     * Markdown title.
     *
     * @return string
     */
    public function title(): string;

    /**
     * The description of your document, which will become the
     * <meta name="description" content="..."/> and
     * <meta property="og:description" content="..."/> in <head>, used by search
     * engines.
     *
     * @return string
     */
    public function description(): string;

    /**
     * Keywords meta tag for the document page, for search engines.
     *
     * @return string[]
     */
    public function keywords(): array;

    /**
     * Get the preview of the content.
     *
     * @param int $maxLength Maximum length of the preview.
     * @return string
     */
    public function preview(int $maxLength = 300): string;

    /**
     * Cover or thumbnail image that will be used as the
     * meta property="og:image" content="..."/> in the <head>,
     * enhancing link previews on social media and messaging platforms.
     *
     * @return string|null
     */
    public function image(): ?string;

    /**
     * Get the main video of the content, if any.
     *
     * @return string|null
     */
    public function video(): ?string;

    /**
     * A list of strings to tag your content.
     *
     * @return array<string, ContentTagInterface>
     */
    public function tags(): array;

    /**
     * The authors of the content.
     *
     * @return array<string, ContentAuthorInterface>
     */
    public function authors(): array;

    /**
     * Get the time required to read or watch the content in minutes.
     *
     * @return int
     */
    public function time(): int;

    /**
     * Draft documents will only be available during development.
     *
     * @return bool
     */
    public function draft(): bool;

    /**
     * Unlisted documents will be available in both development and production.
     *
     * They will be "hidden" in production, not indexed, excluded from sitemaps,
     * and can only be accessed by users having a direct link.
     *
     * @return bool
     */
    public function unlisted(): bool;

    /**
     * Get the created date of the content.
     *
     * @return DateTimeInterface
     */
    public function date(): DateTimeInterface;

    /**
     * The date of the last update of the content.
     *
     * @return DateTimeInterface
     */
    public function last_update(): DateTimeInterface;

    /**
     * Get the deprecated date of the content.
     *
     * @return DateTimeInterface|null
     */
    public function deprecated(): ?DateTimeInterface;

    /**
     * Get the level of the content.
     *
     * @return int
     */
    public function level(): int;

    /**
     * The text used in the document next/previous buttons for this document.
     *
     * Options:
     *
     *   - `sidebar_label`
     *   - `title`
     *
     * @return string
     */
    public function pagination_label(): string;

    /**
     * The text shown in the document sidebar for this document.
     *
     * @return string
     */
    public function sidebar_label(): string;

    /**
     * The position of the document in the sidebar.
     *
     * This is used to determine the order of the documents in the sidebar when
     * the sidebar is automatically generated.
     *
     * @return int
     */
    public function sidebar_position(): int;

    /**
     * The class name of the document sidebar when it's automatically generated.
     *
     * @return string
     */
    public function sidebar_class_name(): string;

    /**
     * The custom properties of the document sidebar when it's automatically
     * generated.
     *
     * @return array
     */
    public function sidebar_custom_props(): array;

    /**
     * Whether to hide the title at the top of the doc. It only hides a title
     * declared through the front matter, and have no effect on a Markdown title
     * at the top of your document.
     *
     * @return bool
     */
    public function hide_title(): bool;

    /**
     * Whether to hide the table of contents to the right.
     *
     * @return bool
     */
    public function hide_table_of_contents(): bool;

    /**
     * The minimum heading level shown in the table of contents.
     *
     * The value must be between 2 and 6 and lower or equal to the
     * `toc_max_heading_level` option.
     *
     * @return int
     */
    public function toc_min_heading_level(): int;

    /**
     * The maximum heading level to include in the table of contents.
     *
     * The value must be between 2 and 6 and greater or equal to the
     * `toc_min_heading_level` option.
     *
     * @return int
     */
    public function toc_max_heading_level(): int;

    /**
     * Get the ancestors of the content.
     *
     * @return array<ContentItemInterface>
     */
    public function ancestors(): array;

    /**
     * Set the parent of the content.
     *
     * @param ContentItemInterface $parent Parent.
     * @return static
     */
    public function setParent(ContentItemInterface $parent): static;

    /**
     * Get the parent of the content.
     *
     * @return ContentItemInterface|null
     */
    public function parent(): ?ContentItemInterface;

    /**
     * Add a child to the content.
     *
     * @param ContentItemInterface $child Child.
     * @return static
     */
    public function addChild(ContentItemInterface $child): static;

    /**
     * Get the children of the content.
     *
     * @return array<ContentItemInterface>
     */
    public function children(): array;

    /**
     * Get the attachments related to the content.
     *
     * @return array<string,ContentAttachmentInterface>
     */
    public function attachments(): array;

    /**
     * Get an attachment of the content.
     *
     * @param string $filename Filename of the attachment to get.
     * @return ContentAttachmentInterface|null
     */
    public function attachment(string $filename): ?ContentAttachmentInterface;

    /**
     * Get the links of the content.
     *
     * @return array
     */
    public function links(): array;

    /**
     * Get the content as an array.
     *
     * @return array
     */
    public function toArray(): array;

    /**
     * Get the content as a JSON string.
     *
     * @return array
     */
    public function jsonSerialize(): array;

    /**
     * Get the content as a string.
     *
     * @return string
     */
    public function __toString(): string;
}
