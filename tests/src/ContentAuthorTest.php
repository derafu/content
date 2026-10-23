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

use Derafu\Content\ContentAuthor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * An author has a name and a slug: the one that the author of the content
 * gave, or the one that is made from the name.
 */
#[CoversClass(ContentAuthor::class)]
final class ContentAuthorTest extends TestCase
{
    public function testWithoutASlugItIsMadeFromTheName(): void
    {
        $author = new ContentAuthor('Esteban De La Fuente');

        $this->assertSame('Esteban De La Fuente', $author->name());
        $this->assertSame('esteban-de-la-fuente', $author->slug());
        $this->assertSame('esteban-de-la-fuente', $author->id());
        $this->assertSame('Esteban De La Fuente', (string) $author);
    }

    public function testASlugThatIsGivenIsKept(): void
    {
        $author = new ContentAuthor('Esteban De La Fuente', 'esteban');

        $this->assertSame('esteban', $author->slug());
        $this->assertSame('esteban', $author->id());
    }

    public function testItIsExportedAsAnArrayAndAsJson(): void
    {
        $author = new ContentAuthor('Ana Pérez');
        $expected = ['name' => 'Ana Pérez', 'slug' => 'ana-perez'];

        $this->assertSame($expected, $author->toArray());
        $this->assertSame(json_encode($expected), json_encode($author));
    }
}
