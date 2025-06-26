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

use Derafu\Content\ContentLoader;
use Derafu\Content\Plugin\Blog\BlogRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Test for the BlogRegistry class.
 */
#[CoversClass(BlogRegistry::class)]
#[CoversClass(ContentLoader::class)]
final class BlogTest extends TestCase
{
    public function testBlogWithoutPosts(): void
    {
        $contentLoader = new ContentLoader('/tmp');
        $blogRegistry = new BlogRegistry($contentLoader, '/blog-fake-path', [], []);

        $this->assertCount(0, $blogRegistry->all());
    }
}
