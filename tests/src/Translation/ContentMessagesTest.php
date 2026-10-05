<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent\Translation;

use Derafu\Content\Translation\ContentTranslationResourceProvider;
use Derafu\TestsContent\Support\RendererFixture;
use Derafu\TestsContent\Support\RouterFixture;
use Derafu\Twig\Lint\TwigTranslationAudit;
use Mcp\Exception\ToolCallException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The package is translated: every message of its code and of its templates has
 * its Spanish translation, the catalogue has nothing that they do not use, and
 * every message can be checked by reading.
 *
 * It is found by reading the code and the templates, so a new message without an
 * entry in the catalogue fails here, instead of showing in the original language
 * when it is shown.
 *
 * The texts of the Markdown and XML templates are not checked: the scanner of
 * texts reads HTML.
 */
#[CoversClass(ContentTranslationResourceProvider::class)]
final class ContentMessagesTest extends TestCase
{
    public function testThePackageIsTranslated(): void
    {
        $root = dirname(__DIR__, 3);

        $report = (new TwigTranslationAudit())->audit(
            $root . '/src',
            $root . '/resources/templates',
            new ContentTranslationResourceProvider(),
            RendererFixture::twig(RouterFixture::create()),
            allowedThrowables: [
                // The tools of the MCP plugin throw the exception of the MCP SDK,
                // a final class that can not be translatable: the message goes
                // to the MCP client (an agent) and it is always in English.
                ToolCallException::class,
            ]
        );

        // Finding nothing would look like a clean result.
        $this->assertFalse($report->nothingFound);
        $this->assertSame([], $report->describe($report->dynamicMessages));
        $this->assertSame([], $report->describe($report->missingTranslations));
        $this->assertSame([], $report->describe($report->notUsedBySources));

        $this->assertSame([], $report->describe($report->notTranslatable));

        // The texts of the HTML templates (the Markdown and XML ones are not read:
        // they are not HTML).
        $this->assertSame([], $report->describe($report->untranslatedTexts));
    }
}
