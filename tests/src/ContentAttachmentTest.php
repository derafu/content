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

use Derafu\Content\Abstract\AbstractContentItem;
use Derafu\Content\ContentAttachment;
use Derafu\Content\ContentSplFileInfo;
use Derafu\Content\Plugin\Docs\DocsDoc;
use Derafu\File\Directory;
use Derafu\Http\Enum\ContentType;
use Derafu\TestsContent\Support\ContentFixtures;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * An attachment is a file that lives beside a content: it says what it is,
 * how big it is, its text and which icon the website shows for it.
 */
#[CoversClass(ContentAttachment::class)]
#[UsesClass(AbstractContentItem::class)]
#[UsesClass(ContentSplFileInfo::class)]
#[UsesClass(DocsDoc::class)]
final class ContentAttachmentTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/content-attachment-test-' . uniqid();
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        Directory::remove($this->directory);
    }

    private function parent(): DocsDoc
    {
        return new DocsDoc(ContentFixtures::contentPath('docs/api.md'));
    }

    private function fixture(): ContentAttachment
    {
        return new ContentAttachment(
            ContentFixtures::contentPath('docs/api/_attachments/openapi-schema.yaml'),
            $this->parent()
        );
    }

    public function testItDescribesTheFile(): void
    {
        $attachment = $this->fixture();
        $path = ContentFixtures::contentPath('docs/api/_attachments/openapi-schema.yaml');

        $real = realpath($path);
        $this->assertIsString($real);

        $this->assertSame($real, $attachment->path());
        $this->assertSame(dirname($real), $attachment->directory());
        $this->assertSame('openapi-schema', $attachment->name());
        $this->assertSame('yaml', $attachment->extension());
        $this->assertSame(ContentType::YAML, $attachment->type());
        $this->assertSame(filesize($path), $attachment->size());
    }

    public function testTheIdAndTheChecksumAreTheSha256OfTheFile(): void
    {
        $attachment = $this->fixture();
        $path = ContentFixtures::contentPath('docs/api/_attachments/openapi-schema.yaml');

        $this->assertSame(hash_file('sha256', $path), $attachment->id());
        $this->assertSame($attachment->id(), $attachment->id());
        $this->assertSame($attachment->id(), $attachment->checksum());
    }

    public function testTheRawTextIsTheContentOfTheFile(): void
    {
        $attachment = $this->fixture();

        $this->assertSame(
            file_get_contents(ContentFixtures::contentPath('docs/api/_attachments/openapi-schema.yaml')),
            $attachment->raw()
        );
        $this->assertSame($attachment->raw(), $attachment->raw());
    }

    public function testItKnowsTheContentItBelongsTo(): void
    {
        $parent = $this->parent();

        $this->assertSame($parent, (new ContentAttachment(
            ContentFixtures::contentPath('docs/api/_attachments/openapi-schema.yaml'),
            $parent
        ))->parent());
    }

    public function testItCanBeBuiltFromTheFileInfoThatTheContentAlreadyHas(): void
    {
        $info = new ContentSplFileInfo(ContentFixtures::contentPath('docs/api/_attachments/openapi-schema.yaml'));

        $attachment = new ContentAttachment($info, $this->parent());

        $this->assertSame('openapi-schema', $attachment->name());
    }

    public function testAPathThatDoesNotExistIsAnErrorThatSaysWhichPathIt(): void
    {
        $path = $this->directory . '/missing.pdf';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Path ' . $path . ' must be a readable attachment.');

        new ContentAttachment($path, $this->parent());
    }

    public function testAPathThatIsNotAFileIsAnError(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Path ' . $this->directory . ' must be a readable attachment.');

        new ContentAttachment($this->directory, $this->parent());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function provideFilesAndTheirIcons(): array
    {
        $code = 'fa-regular fa-file-code fa-fw';

        return [
            'json' => ['quiz.json', $code],
            'yaml' => ['schema.yaml', $code],
            'markdown' => ['notes.md', $code],
            'html' => ['page.html', $code],
            'unknown' => ['data.unknownextension', $code],
            'pdf' => ['manual.pdf', 'fa-regular fa-file-pdf fa-fw'],
            'zip' => ['backup.zip', 'fa-regular fa-file-zipper fa-fw'],
            'gzip' => ['backup.gz', 'fa-regular fa-file-archive fa-fw'],
            'png' => ['logo.png', 'fa-regular fa-file-image fa-fw'],
            'svg' => ['logo.svg', 'fa-regular fa-file-image fa-fw'],
            'mp3' => ['song.mp3', 'fa-regular fa-file-audio fa-fw'],
            'mp4' => ['clip.mp4', 'fa-regular fa-file-video fa-fw'],
            'woff2' => ['font.woff2', 'fa-regular fa-file-font fa-fw'],
            'docx' => ['letter.docx', 'fa-regular fa-file-document fa-fw'],
            'xlsx' => ['sheet.xlsx', 'fa-regular fa-file-document fa-fw'],
            'ppt' => ['slides.ppt', 'fa-regular fa-file-document fa-fw'],
            'pptx' => ['slides.pptx', 'fa-regular fa-file-document fa-fw'],
        ];
    }

    #[DataProvider('provideFilesAndTheirIcons')]
    public function testEachKindOfFileHasItsIcon(string $filename, string $icon): void
    {
        file_put_contents($this->directory . '/' . $filename, 'x');

        $attachment = new ContentAttachment($this->directory . '/' . $filename, $this->parent());

        $this->assertSame($icon, $attachment->icon());
    }
}
