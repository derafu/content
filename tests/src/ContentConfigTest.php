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

use Derafu\Content\ContentConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The configuration of the website has what the application gave and, for
 * what it did not give, the values that the package defines.
 */
#[CoversClass(ContentConfig::class)]
final class ContentConfigTest extends TestCase
{
    public function testWhatTheApplicationDidNotGiveHasItsDefault(): void
    {
        $config = new ContentConfig(['title' => 'My Site', 'url' => 'https://example.com']);

        $this->assertSame('My Site', $config->title());
        $this->assertSame('https://example.com', $config->url());
        $this->assertSame('', $config->baseUrl());
        $this->assertNull($config->favicon());
        $this->assertNull($config->tagline());
        $this->assertSame('|', $config->titleDelimiter());
        $this->assertSame('{id}.html', $config->uriFormat());
        $this->assertFalse($config->noIndex());
        $this->assertSame('throw', $config->onBrokenLinks());
        $this->assertTrue($config->cacheEnabled());
        $this->assertSame([], $config->markdown());
        $this->assertSame([], $config->plugins());
        $this->assertSame([], $config->i18n());
        $this->assertSame([], $config->customFields());
    }

    public function testWhatTheApplicationGaveIsKept(): void
    {
        $config = new ContentConfig([
            'title' => 'My Site',
            'url' => 'https://example.com',
            'baseUrl' => '/docs',
            'favicon' => '/favicon.ico',
            'titleDelimiter' => '-',
            'tagline' => 'Where knowledge becomes product',
            'uriFormat' => '{id}',
            'noIndex' => true,
            'onBrokenLinks' => 'warn',
            'markdown' => ['extensions' => ['tables']],
            'plugins' => ['docs' => ['path' => 'docs']],
            'i18n' => ['locale' => 'es'],
            'cache' => ['enabled' => false],
            'customFields' => ['analytics' => 'G-1'],
        ]);

        $this->assertSame('/docs', $config->baseUrl());
        $this->assertSame('/favicon.ico', $config->favicon());
        $this->assertSame('-', $config->titleDelimiter());
        $this->assertSame('Where knowledge becomes product', $config->tagline());
        $this->assertSame('{id}', $config->uriFormat());
        $this->assertTrue($config->noIndex());
        $this->assertSame('warn', $config->onBrokenLinks());
        $this->assertSame(['extensions' => ['tables']], $config->markdown());
        $this->assertSame(['docs' => ['path' => 'docs']], $config->plugins());
        $this->assertSame(['locale' => 'es'], $config->i18n());
        $this->assertFalse($config->cacheEnabled());
        $this->assertSame(['analytics' => 'G-1'], $config->customFields());
    }

    public function testACustomFieldHasADefaultWhenItIsNotThere(): void
    {
        $config = new ContentConfig([
            'title' => 'My Site',
            'url' => 'https://example.com',
            'customFields' => ['analytics' => 'G-1'],
        ]);

        $this->assertSame('G-1', $config->customField('analytics'));
        $this->assertNull($config->customField('missing'));
        $this->assertSame('x', $config->customField('missing', 'x'));
    }
}
