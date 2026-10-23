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

use Derafu\Content\ContentSplFileInfo;
use Derafu\TestsContent\Support\ContentFixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The file of a content gives its URI inside the content directory and its
 * checksum.
 */
#[CoversClass(ContentSplFileInfo::class)]
final class ContentSplFileInfoTest extends TestCase
{
    public function testTheUriIsTheRelativePathWithoutTheExtension(): void
    {
        $info = new ContentSplFileInfo(ContentFixtures::contentPath('docs/guide.md'));

        $this->assertSame('docs/guide', $info->getUri(ContentFixtures::contentPath()));
    }

    public function testTheChecksumIsTheSha256OfTheFile(): void
    {
        $path = ContentFixtures::contentPath('docs/guide.md');

        $this->assertSame(hash_file('sha256', $path), (new ContentSplFileInfo($path))->getChecksum());
    }
}
