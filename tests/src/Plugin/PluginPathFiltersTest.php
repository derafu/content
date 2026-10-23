<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent\Plugin;

use Derafu\Content\Contract\ContentPluginInterface;
use Derafu\Content\Plugin\Academy\AcademyPlugin;
use Derafu\Content\Plugin\Blog\BlogPlugin;
use Derafu\Content\Plugin\Docs\DocsPlugin;
use Derafu\Content\Plugin\Faq\FaqPlugin;
use Derafu\Content\Plugin\Pages\PagesPlugin;
use Derafu\TestsContent\Support\ContentFixtures;
use Derafu\Translation\Contract\TranslatableInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * The plugins that read a directory take the patterns of "include" and
 * "exclude" as lists of texts: a pattern of another type is a configuration
 * error that names the option.
 */
#[CoversNothing]
final class PluginPathFiltersTest extends TestCase
{
    /**
     * @return array<string, array{class-string<ContentPluginInterface>, string, mixed, string}>
     */
    public static function providePatternsThatAreNotValid(): array
    {
        $cases = [];
        foreach ([
            'academy' => AcademyPlugin::class,
            'blog' => BlogPlugin::class,
            'docs' => DocsPlugin::class,
            'faq' => FaqPlugin::class,
            'pages' => PagesPlugin::class,
        ] as $name => $class) {
            foreach (['include', 'exclude'] as $option) {
                $cases[$name . ' with ' . $option . ' that has a number'] = [$class, $option, ['*.md', 7], 'int'];
            }
        }

        return $cases;
    }

    /**
     * @param class-string<ContentPluginInterface> $class
     */
    #[DataProvider('providePatternsThatAreNotValid')]
    public function testAPatternThatIsNotATextIsAnErrorThatNamesTheOption(
        string $class,
        string $option,
        mixed $value,
        string $given
    ): void {
        $exception = null;
        try {
            $plugin = new $class(ContentFixtures::contentContext(), ['path' => 'docs', $option => $value]);
            $plugin->loadContent(ContentFixtures::contentLoader());
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(TranslatableInterface::class, $exception);
        $this->assertStringContainsString($option, $exception->getMessage());
        $this->assertStringContainsString($given, $exception->getMessage());
    }
}
