<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent\Abstract;

use Derafu\Content\Abstract\AbstractContentItem;
use Derafu\Content\ContentAttachment;
use Derafu\Content\ContentSplFileInfo;
use Derafu\Content\Plugin\Docs\DocsDoc;
use Derafu\Translation\Contract\TranslatableInterface;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use Throwable;

/**
 * What can go wrong when a content is read (its frontmatter, its file, its text)
 * is a translatable error that says which content or file it is.
 */
#[CoversClass(AbstractContentItem::class)]
#[CoversClass(ContentAttachment::class)]
#[CoversClass(ContentSplFileInfo::class)]
#[UsesClass(DocsDoc::class)]
final class AbstractContentItemFailuresTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/' . uniqid('derafu_content_failures_');
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            chmod($file, 0644);
            unlink($file);
        }
        rmdir($this->directory);
    }

    private function item(string $name): DocsDoc
    {
        return new DocsDoc(dirname(__DIR__, 2) . '/fixtures/frontmatter/' . $name . '.md');
    }

    /**
     * A file that exists when the content is created and that can not be read
     * later.
     */
    private function unreadable(string $name): string
    {
        $path = $this->directory . '/' . $name;
        file_put_contents($path, "---\ntitle: Hola\n---\n\nContent.\n");

        return $path;
    }

    private function failureOf(callable $call): Throwable
    {
        // The warning of PHP about the file is not what is tested.
        set_error_handler(static fn (): bool => true);
        try {
            $call();
        } catch (Throwable $e) {
            return $e;
        } finally {
            restore_error_handler();
        }

        $this->fail('It should fail.');
    }

    /**
     * Runs a call with the limits of PCRE so low that any pattern fails (without
     * the JIT, which does not use the limit of backtracking).
     *
     * @template T
     * @param callable(): T $call
     * @return T
     */
    private function withPcreLimits(callable $call): mixed
    {
        $jit = ini_get('pcre.jit');
        $limit = ini_get('pcre.backtrack_limit');
        ini_set('pcre.jit', '0');
        ini_set('pcre.backtrack_limit', '1');
        try {
            return $call();
        } finally {
            ini_set('pcre.jit', (string) $jit);
            ini_set('pcre.backtrack_limit', (string) $limit);
        }
    }

    /**
     * @param class-string<Throwable> $class
     */
    private function assertTranslatable(string $class, string $message, Throwable $exception): void
    {
        $this->assertInstanceOf($class, $exception);
        $this->assertInstanceOf(TranslatableInterface::class, $exception);
        $this->assertSame($message, $exception->getMessage());
    }

    public function testAnEmptyFrontmatterIsNoMetadata(): void
    {
        $item = $this->item('frontmatter-empty');

        $this->assertSame('frontmatter-empty', $item->title());
        $this->assertSame('Content of a document with an empty frontmatter.', $item->data());
    }

    public function testAFrontmatterThatIsNotValidYamlSaysWhichContentItIs(): void
    {
        $exception = $this->failureOf(fn () => $this->item('invalid-yaml')->title());

        $this->assertInstanceOf(InvalidArgumentException::class, $exception);
        $this->assertInstanceOf(TranslatableInterface::class, $exception);
        $this->assertStringStartsWith('The frontmatter of "docs/invalid-yaml" is not valid YAML: ', $exception->getMessage());
        $this->assertNotNull($exception->getPrevious());
    }

    public function testAFrontmatterThatIsNotAnObjectSaysWhichContentItIs(): void
    {
        $this->assertTranslatable(
            InvalidArgumentException::class,
            'The frontmatter of "docs/frontmatter-text" must be an object, string given.',
            $this->failureOf(fn () => $this->item('frontmatter-text')->title())
        );
        $this->assertTranslatable(
            InvalidArgumentException::class,
            'The frontmatter of "docs/frontmatter-list" must be an object, list given.',
            $this->failureOf(fn () => $this->item('frontmatter-list')->title())
        );
    }

    public function testAYoutubeUrlWhoseVIsNotATextIsKeptAsItIs(): void
    {
        $this->assertSame(
            'https://www.youtube.com/watch?v[]=a&v[]=b',
            $this->item('youtube-array')->video()
        );
    }

    /**
     * Runs in its own process: PHP keeps the compiled patterns (with the JIT
     * that they had when they were compiled) for the whole process, and the
     * JIT does not use the limit of backtracking.
     */
    #[RunInSeparateProcess]
    public function testThePreviewOfATextThatPcreCanNotProcessSaysWhichContentItIs(): void
    {
        $item = $this->item('pcre-oneline');
        $item->data();

        $exception = $this->withPcreLimits(fn (): Throwable => $this->failureOf(fn () => $item->preview()));

        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertInstanceOf(TranslatableInterface::class, $exception);
        $this->assertSame(
            sprintf('The content "docs/pcre-oneline" could not be processed: %s', 'Backtrack limit exhausted'),
            $exception->getMessage()
        );
    }

    /**
     * Runs in its own process: PHP keeps the compiled patterns (with the JIT
     * that they had when they were compiled) for the whole process, and the
     * JIT does not use the limit of backtracking.
     *
     * With this text the split into paragraphs is what fails, not the cleaning
     * of the titles, so an empty preview is not what it gives.
     */
    #[RunInSeparateProcess]
    public function testThePreviewOfATextThatPcreCanNotSplitIntoParagraphsIsAnError(): void
    {
        $item = $this->item('pcre-heavy');
        $item->data();

        $exception = $this->withPcreLimits(fn (): Throwable => $this->failureOf(fn () => $item->preview()));

        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertSame(
            'The content "docs/pcre-heavy" could not be processed: Backtrack limit exhausted',
            $exception->getMessage()
        );
    }

    /**
     * Runs in its own process: PHP keeps the compiled patterns (with the JIT
     * that they had when they were compiled) for the whole process, and the
     * JIT does not use the limit of backtracking.
     */
    #[RunInSeparateProcess]
    public function testEachRewriteOfTheUrlsOfTheTextIsAnErrorIfPcreCanNotProcessIt(): void
    {
        $item = $this->item('pcre-heavy');
        $item->data();

        foreach (['asset_base_url', 'link_base_url'] as $option) {
            $exception = $this->withPcreLimits(
                fn (): Throwable => $this->failureOf(fn () => $item->data([$option => 'https://example.com']))
            );

            $this->assertInstanceOf(RuntimeException::class, $exception);
        }
    }

    /**
     * Runs in its own process: PHP keeps the compiled patterns (with the JIT
     * that they had when they were compiled) for the whole process, and the
     * JIT does not use the limit of backtracking.
     */
    #[RunInSeparateProcess]
    public function testTheTextOfAContentThatPcreCanNotProcessIsAnError(): void
    {
        $item = $this->item('valid-all');

        $exception = $this->withPcreLimits(fn (): Throwable => $this->failureOf(fn () => $item->data()));

        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertStringStartsWith('The content "docs/valid-all" could not be processed: ', $exception->getMessage());
    }

    public function testAContentFileThatCanNotBeReadSaysWhichFileItIs(): void
    {
        $path = $this->unreadable('docs.md');
        $item = new DocsDoc($path);
        chmod($path, 0000);
        if (is_readable($path)) {
            $this->markTestSkipped('The file is readable by this user (root).');
        }

        $this->assertTranslatable(
            RuntimeException::class,
            sprintf('The file "%s" could not be read.', $path),
            $this->failureOf(fn () => $item->raw())
        );
    }

    public function testAnAttachmentThatCanNotBeReadSaysWhichFileItIs(): void
    {
        $parent = $this->item('minimal');
        $path = $this->unreadable('attachment.json');
        $attachment = new ContentAttachment($path, $parent);
        chmod($path, 0000);
        if (is_readable($path)) {
            $this->markTestSkipped('The file is readable by this user (root).');
        }
        $message = sprintf('The file "%s" could not be read.', realpath($path));

        $this->assertTranslatable(RuntimeException::class, $message, $this->failureOf(fn () => $attachment->raw()));
        $this->assertTranslatable(RuntimeException::class, $message, $this->failureOf(fn () => $attachment->id()));
    }

    public function testTheChecksumOfAFileThatCanNotBeReadSaysWhichFileItIs(): void
    {
        $path = $this->unreadable('checksum.md');
        $info = new ContentSplFileInfo($path);
        chmod($path, 0000);
        if (is_readable($path)) {
            $this->markTestSkipped('The file is readable by this user (root).');
        }

        $this->assertTranslatable(
            RuntimeException::class,
            sprintf('The file "%s" could not be read.', $path),
            $this->failureOf(fn () => $info->getChecksum())
        );
    }

    public function testASnapshotWithoutTheFileIsAnError(): void
    {
        $item = (new ReflectionClass(DocsDoc::class))->newInstanceWithoutConstructor();

        $this->assertTranslatable(
            InvalidArgumentException::class,
            'The key "info" of content snapshot is required.',
            $this->failureOf(fn () => $item->__unserialize([]))
        );
        $this->assertTranslatable(
            InvalidArgumentException::class,
            'The key "info" of content snapshot must be of type string, int given.',
            $this->failureOf(fn () => $item->__unserialize(['info' => 5]))
        );
    }

    public function testAnItemSurvivesBeingSerialized(): void
    {
        $item = $this->item('valid-all');
        $item->title();

        $copy = unserialize(serialize($item));

        $this->assertInstanceOf(DocsDoc::class, $copy);
        $this->assertSame('All good', $copy->title());
        $this->assertSame('2026-01-15', $copy->date()->format('Y-m-d'));
    }
}
