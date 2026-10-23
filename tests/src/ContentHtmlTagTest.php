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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * A tag is written with its attributes, and the value of an attribute is
 * escaped so it can not close the tag or add another attribute.
 */
#[CoversClass(ContentHtmlTag::class)]
final class ContentHtmlTagTest extends TestCase
{
    public function testItKeepsItsNameAndItsAttributes(): void
    {
        $tag = new ContentHtmlTag('meta', ['name' => 'robots']);

        $this->assertSame('meta', $tag->tagName());
        $this->assertSame(['name' => 'robots'], $tag->attributes());
    }

    public function testAnAttributeThatIsTrueIsWrittenWithoutValue(): void
    {
        $this->assertSame('<script src="/a.js" async>', (new ContentHtmlTag('script', ['src' => '/a.js', 'async' => true]))->toHtml());
    }

    public function testAnAttributeThatIsFalseIsNotWritten(): void
    {
        $this->assertSame('<script src="/a.js">', (new ContentHtmlTag('script', ['src' => '/a.js', 'async' => false]))->toHtml());
    }

    public function testTheValueOfAnAttributeIsEscaped(): void
    {
        $tag = new ContentHtmlTag('meta', ['content' => '"><script>alert(1)</script> & more']);

        $this->assertSame(
            '<meta content="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt; &amp; more">',
            $tag->toHtml()
        );
    }
}
