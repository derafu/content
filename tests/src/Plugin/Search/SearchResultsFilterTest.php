<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent\Plugin\Search;

use Derafu\Content\Abstract\AbstractContentItem;
use Derafu\Content\Abstract\AbstractContentPlugin;
use Derafu\Content\Abstract\AbstractContentRegistry;
use Derafu\Content\Abstract\AbstractPlugin;
use Derafu\Content\ContentAuthor;
use Derafu\Content\ContentBag;
use Derafu\Content\ContentConfig;
use Derafu\Content\ContentContext;
use Derafu\Content\ContentFilters;
use Derafu\Content\ContentLoader;
use Derafu\Content\ContentService;
use Derafu\Content\ContentSplFileInfo;
use Derafu\Content\ContentTag;
use Derafu\Content\Exception\ContentNotFoundException;
use Derafu\Content\Exception\PluginNotFoundException;
use Derafu\Content\Plugin\Docs\DocsDoc;
use Derafu\Content\Plugin\Docs\DocsPlugin;
use Derafu\Content\Plugin\Docs\DocsRegistry;
use Derafu\Content\Plugin\Search\SearchPlugin;
use Derafu\Content\Plugin\Search\SearchResultsFilter;
use Derafu\TestsContent\Support\ContentFixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * The index of the search engine can be older than the content: only what is
 * still searchable, allowed and listed in the registry gets to the user, and
 * a result that the registry does not know is dropped, not an error.
 */
#[CoversClass(SearchResultsFilter::class)]
#[UsesClass(AbstractContentItem::class)]
#[UsesClass(AbstractContentPlugin::class)]
#[UsesClass(AbstractContentRegistry::class)]
#[UsesClass(AbstractPlugin::class)]
#[UsesClass(ContentAuthor::class)]
#[UsesClass(ContentBag::class)]
#[UsesClass(ContentConfig::class)]
#[UsesClass(ContentContext::class)]
#[UsesClass(ContentFilters::class)]
#[UsesClass(ContentLoader::class)]
#[UsesClass(ContentNotFoundException::class)]
#[UsesClass(ContentService::class)]
#[UsesClass(ContentSplFileInfo::class)]
#[UsesClass(ContentTag::class)]
#[UsesClass(DocsDoc::class)]
#[UsesClass(DocsPlugin::class)]
#[UsesClass(DocsRegistry::class)]
#[UsesClass(PluginNotFoundException::class)]
#[UsesClass(SearchPlugin::class)]
final class SearchResultsFilterTest extends TestCase
{
    /**
     * @param array<int, array<string, mixed>> $results
     * @return list<string>
     */
    private function surviving(array $results): array
    {
        $docs = new DocsPlugin(ContentFixtures::contentContext(), ['path' => 'docs']);
        $docs->loadContent(ContentFixtures::contentLoader());

        $service = ContentFixtures::contentService([
            'docs' => $docs,
            'search' => new SearchPlugin(ContentFixtures::contentContext(), ['url' => 'http://localhost/?q=%s']),
        ]);

        $uris = [];
        foreach (SearchResultsFilter::filter($service, $results) as $result) {
            $this->assertIsString($result['uri']);
            $uris[] = $result['uri'];
        }

        return $uris;
    }

    public function testOnlyWhatIsStillVisibleInTheRegistryIsKept(): void
    {
        $surviving = $this->surviving([
            ['type' => 'docs', 'uri' => 'guide'],
            ['type' => 'docs', 'uri' => 'not-searchable'],
            ['type' => 'docs', 'uri' => 'draft-parent'],
            ['type' => 'docs', 'uri' => 'not-listed'],
            ['type' => 'docs', 'uri' => 'ghost-nonexistent'],
        ]);

        $this->assertSame(['guide'], $surviving);
    }

    public function testTheOrderOfTheEngineIsKept(): void
    {
        $this->assertSame(['index', 'guide'], $this->surviving([
            ['type' => 'docs', 'uri' => 'index'],
            ['type' => 'docs', 'uri' => 'guide'],
        ]));
    }

    public function testAResultOfASourceThatDoesNotExistOrIsNotContentIsDropped(): void
    {
        $this->assertSame(['guide'], $this->surviving([
            ['type' => 'nothing', 'uri' => 'guide'],
            ['type' => 'search', 'uri' => 'guide'],
            ['type' => 'docs', 'uri' => 'guide'],
        ]));
    }

    public function testAResultWithoutTypeOrUriOrWithOtherTypesIsDropped(): void
    {
        $this->assertSame(['guide'], $this->surviving([
            ['uri' => 'guide'],
            ['type' => 'docs'],
            ['type' => 7, 'uri' => 'guide'],
            ['type' => 'docs', 'uri' => ['guide']],
            ['type' => 'docs', 'uri' => 'guide'],
        ]));
    }
}
