<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent;

use Derafu\Content\ContentHtmlTag;
use Derafu\Content\ContentHtmlTags;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * The tags that a plugin injects in the head, before the body and after it
 * are written in the order in which they were added, as tags or as text.
 */
#[CoversClass(ContentHtmlTags::class)]
#[UsesClass(ContentHtmlTag::class)]
final class ContentHtmlTagsTest extends TestCase
{
    public function testThePlacesAreEmptyWithoutTags(): void
    {
        $tags = new ContentHtmlTags();

        $this->assertSame('', $tags->headTagsToHtml());
        $this->assertSame('', $tags->preBodyTagsToHtml());
        $this->assertSame('', $tags->postBodyTagsToHtml());
    }

    public function testTheTagsOfTheHeadAreWrittenInOrder(): void
    {
        $tags = (new ContentHtmlTags())
            ->addHeadTag(new ContentHtmlTag('meta', ['name' => 'robots', 'content' => 'noindex']))
            ->addHeadTag(new ContentHtmlTag('link', ['rel' => 'canonical', 'href' => 'https://example.com/a']));

        $this->assertSame(
            '<meta name="robots" content="noindex"><link rel="canonical" href="https://example.com/a">',
            $tags->headTagsToHtml()
        );
    }

    public function testBeforeAndAfterTheBodyAcceptTagsAndText(): void
    {
        $tags = (new ContentHtmlTags())
            ->addPreBodyTag('<!-- start -->')
            ->addPreBodyTag(new ContentHtmlTag('noscript', ['hidden' => true]))
            ->addPostBodyTag(new ContentHtmlTag('script', ['src' => '/a.js', 'defer' => true]))
            ->addPostBodyTag('<!-- end -->');

        $this->assertSame('<!-- start --><noscript hidden>', $tags->preBodyTagsToHtml());
        $this->assertSame('<script src="/a.js" defer><!-- end -->', $tags->postBodyTagsToHtml());
    }
}
