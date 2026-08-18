<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent\Plugin\Faq;

use Derafu\Content\ContentBag;
use Derafu\Content\ContentConfig;
use Derafu\Content\ContentContext;
use Derafu\Content\ContentLoader;
use Derafu\Content\ContentSplFileInfo;
use Derafu\Content\ContentTag;
use Derafu\Content\Exception\ContentNotFoundException;
use Derafu\Content\Plugin\Faq\FaqPlugin;
use Derafu\Content\Plugin\Faq\FaqQuestion;
use Derafu\Content\Plugin\Faq\FaqRegistry;
use Derafu\TestsContent\Support\ContentFixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the FAQ plugin against real fixture questions on disk, no mocks.
 */
#[CoversClass(FaqPlugin::class)]
#[CoversClass(FaqRegistry::class)]
#[CoversClass(FaqQuestion::class)]
#[UsesClass(ContentBag::class)]
#[UsesClass(ContentConfig::class)]
#[UsesClass(ContentContext::class)]
#[UsesClass(ContentLoader::class)]
#[UsesClass(ContentSplFileInfo::class)]
#[UsesClass(ContentTag::class)]
#[UsesClass(ContentNotFoundException::class)]
final class FaqRegistryTest extends TestCase
{
    private FaqPlugin $plugin;

    protected function setUp(): void
    {
        $this->plugin = new FaqPlugin(
            ContentFixtures::contentContext(),
            ['path' => 'faq']
        );
        $this->plugin->loadContent(new ContentLoader(ContentFixtures::contentPath()));
    }

    public function testBothFixtureQuestionsAreLoaded(): void
    {
        $this->assertCount(2, $this->plugin->registry()->all());
    }

    public function testHideTableOfContentsDefaultsToTrueForFaq(): void
    {
        $question = $this->plugin->registry()->get('index');

        $this->assertTrue($question->hide_table_of_contents());
    }

    public function testTagsAreAggregatedWithCounts(): void
    {
        $tags = $this->plugin->registry()->tags();

        $this->assertArrayHasKey('tests', $tags);
        $this->assertSame(1, $tags['tests']->count());
    }

    public function testFilterByTagReturnsOnlyTaggedQuestions(): void
    {
        $matches = $this->plugin->registry()->filter(['tag' => 'demo']);

        $this->assertCount(1, $matches);
        $this->assertSame('pregunta-uno', $matches[0]->slug());
    }

    public function testUnknownUriThrowsContentNotFoundException(): void
    {
        $this->expectException(ContentNotFoundException::class);

        $this->plugin->registry()->get('no-existe');
    }
}
