<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent\Abstract;

use Derafu\Content\Abstract\AbstractContentItem;
use Derafu\Content\Abstract\AbstractContentRegistry;
use Derafu\Content\ContentAttachment;
use Derafu\Content\ContentBag;
use Derafu\Content\ContentConfig;
use Derafu\Content\ContentContext;
use Derafu\Content\ContentFilters;
use Derafu\Content\ContentLoader;
use Derafu\Content\ContentSplFileInfo;
use Derafu\Content\Exception\ContentNotFoundException;
use Derafu\Content\Plugin\Docs\Contract\DocsRegistryInterface;
use Derafu\Content\Plugin\Docs\DocsDoc;
use Derafu\Content\Plugin\Docs\DocsPlugin;
use Derafu\Content\Plugin\Docs\DocsRegistry;
use Derafu\TestsContent\Support\ContentFixtures;
use Derafu\Translation\Contract\TranslatableInterface;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Throwable;

/**
 * What the registry can not do with its input is an error that says why, and a
 * cache entry that is not the tree of items is as if it was not there.
 */
#[CoversClass(AbstractContentRegistry::class)]
#[CoversClass(DocsRegistry::class)]
#[UsesClass(AbstractContentItem::class)]
#[UsesClass(ContentAttachment::class)]
#[UsesClass(ContentBag::class)]
#[UsesClass(ContentLoader::class)]
#[UsesClass(ContentSplFileInfo::class)]
#[UsesClass(DocsDoc::class)]
#[UsesClass(DocsPlugin::class)]
#[UsesClass(ContentFilters::class)]
#[UsesClass(ContentConfig::class)]
#[UsesClass(ContentContext::class)]
#[UsesClass(ContentNotFoundException::class)]
final class AbstractContentRegistryFailuresTest extends TestCase
{
    private function registry(ArrayAdapter $cache): DocsRegistryInterface
    {
        $plugin = new DocsPlugin(ContentFixtures::contentContext(cache: $cache), ['path' => 'docs']);
        $plugin->loadContent(ContentFixtures::contentLoader());

        return $plugin->registry();
    }

    public function testACacheEntryThatIsNotTheTreeOfItemsIsReplacedByTheLoadedOne(): void
    {
        $cache = new ArrayAdapter();
        $key = (new ReflectionMethod(AbstractContentRegistry::class, 'cacheKey'))->invoke($this->registry($cache));
        $this->assertIsString($key);

        foreach ([['not' => 'items'], 'text', ['guide' => 5]] as $garbage) {
            $cache->save($cache->getItem($key)->set($garbage));

            $registry = $this->registry($cache);

            $this->assertSame('guide', $registry->get('guide')->slug());

            $cached = $cache->getItem($key)->get();
            $this->assertIsArray($cached);
            $this->assertContainsOnlyInstancesOf(DocsDoc::class, $cached);
        }
    }

    /**
     * Runs in its own process: PHP keeps the compiled patterns (with the JIT
     * that they had when they were compiled) for the whole process, and the
     * JIT does not use the limit of backtracking.
     */
    #[RunInSeparateProcess]
    public function testAUriThatPcreCanNotProcessIsAnErrorThatSaysWhich(): void
    {
        $registry = $this->registry(new ArrayAdapter());
        $uri = str_repeat('a', 200) . '.md';

        $jit = ini_get('pcre.jit');
        $limit = ini_get('pcre.backtrack_limit');
        ini_set('pcre.jit', '0');
        ini_set('pcre.backtrack_limit', '1');
        $exception = null;
        try {
            $registry->get($uri);
        } catch (Throwable $e) {
            $exception = $e;
        } finally {
            ini_set('pcre.jit', (string) $jit);
            ini_set('pcre.backtrack_limit', (string) $limit);
        }

        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertInstanceOf(TranslatableInterface::class, $exception);
        $this->assertSame(
            sprintf('The content "%s" could not be processed: Backtrack limit exhausted', $uri),
            $exception->getMessage()
        );
    }

    public function testAFilterWithAnotherTypeIsAnErrorThatNamesTheFilter(): void
    {
        $registry = $this->registry(new ArrayAdapter());

        $exception = null;
        try {
            $registry->filter(['search' => ['a']]);
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(InvalidArgumentException::class, $exception);
        $this->assertInstanceOf(TranslatableInterface::class, $exception);
        $this->assertSame('The key "search" of filters must be of type string, array given.', $exception->getMessage());
    }

    public function testThePageIsTheOneOfTheFilters(): void
    {
        $registry = $this->registry(new ArrayAdapter());

        $first = $registry->filter(['limit' => 1]);
        $second = $registry->filter(['limit' => 1, 'page' => 2]);

        $this->assertCount(1, $first);
        $this->assertCount(1, $second);
        $this->assertNotSame($first[0]->uri(), $second[0]->uri());
    }

    public function testAnEmptyUriIsNotFound(): void
    {
        $registry = $this->registry(new ArrayAdapter());

        $exception = null;
        try {
            $registry->get('');
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(ContentNotFoundException::class, $exception);
        $this->assertSame('Content item with URI "" not found.', $exception->getMessage());
    }
}
