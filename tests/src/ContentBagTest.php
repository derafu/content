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

use ArrayObject;
use Derafu\Container\Bag;
use Derafu\Content\ContentBag;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The bag gives the content of a plugin by name, from an array, from anything
 * that can be read as one or from a bag that already exists.
 */
#[CoversClass(ContentBag::class)]
final class ContentBagTest extends TestCase
{
    public function testItReadsAnArray(): void
    {
        $bag = new ContentBag(['a' => 1]);

        $this->assertSame(1, $bag->get('a'));
    }

    public function testItReadsAnObjectThatCanBeReadAsAnArray(): void
    {
        $this->assertSame('x', (new ContentBag(new ArrayObject(['a' => 'x'])))->get('a'));
    }

    public function testItUsesABagThatAlreadyExists(): void
    {
        $this->assertSame('x', (new ContentBag(new Bag(['a' => 'x'])))->get('a'));
    }

    public function testWhatIsNotThereTakesTheDefault(): void
    {
        $bag = new ContentBag([]);

        $this->assertNull($bag->get('missing'));
        $this->assertSame('fallback', $bag->get('missing', 'fallback'));
    }
}
