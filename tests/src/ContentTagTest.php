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

use Derafu\Content\ContentTag;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * A tag has the name that the author wrote, a slug made from it and the
 * number of contents that have it.
 */
#[CoversClass(ContentTag::class)]
final class ContentTagTest extends TestCase
{
    public function testTheSlugIsMadeFromTheNameAndItIsTheId(): void
    {
        $tag = new ContentTag('Hello World Ñandú');

        $this->assertSame('Hello World Ñandú', $tag->name());
        $this->assertSame('hello-world-nandu', $tag->slug());
        $this->assertSame('hello-world-nandu', $tag->id());
        $this->assertSame('Hello World Ñandú', (string) $tag);
    }

    public function testTheCountStartsWhereItIsToldAndGrows(): void
    {
        $tag = new ContentTag('php');
        $this->assertSame(0, $tag->count());

        $tag->increment();
        $tag->increment();

        $this->assertSame(2, $tag->count());
        $this->assertSame(5, (new ContentTag('php', 5))->count());
    }

    public function testItIsExportedAsAnArrayAndAsJson(): void
    {
        $tag = new ContentTag('Open Source', 3);
        $expected = ['name' => 'Open Source', 'slug' => 'open-source', 'count' => 3];

        $this->assertSame($expected, $tag->toArray());
        $this->assertSame(json_encode($expected), json_encode($tag));
    }
}
