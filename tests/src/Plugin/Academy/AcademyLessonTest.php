<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent\Plugin\Academy;

use Derafu\Content\Abstract\AbstractContentItem;
use Derafu\Content\ContentAttachment;
use Derafu\Content\ContentSplFileInfo;
use Derafu\Content\Plugin\Academy\AcademyLesson;
use Derafu\Content\Plugin\Academy\AcademyTest;
use Derafu\Content\Plugin\Academy\AcademyTestOption;
use Derafu\Content\Plugin\Academy\AcademyTestQuestion;
use Derafu\File\Directory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * A lesson has a video and a test that its frontmatter points to, the test can
 * be an attachment of the lesson in two ways, and the icon of the listings
 * tells which of them it has without reading the test.
 */
#[CoversClass(AcademyLesson::class)]
#[UsesClass(AbstractContentItem::class)]
#[UsesClass(AcademyTest::class)]
#[UsesClass(AcademyTestOption::class)]
#[UsesClass(AcademyTestQuestion::class)]
#[UsesClass(ContentAttachment::class)]
#[UsesClass(ContentSplFileInfo::class)]
final class AcademyLessonTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/academy-lesson-' . uniqid();
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        Directory::remove($this->directory);
    }

    private function lesson(string $name, string $frontmatter = '', bool $withQuiz = false): AcademyLesson
    {
        file_put_contents(
            $this->directory . '/' . $name . '.md',
            "---\ntitle: \"Lesson\"\n" . $frontmatter . "---\nThe text of the lesson.\n"
        );

        if ($withQuiz) {
            mkdir($this->directory . '/' . $name . '/_attachments', 0777, true);
            file_put_contents(
                $this->directory . '/' . $name . '/_attachments/quiz.json',
                '{"id": "q", "title": "A quiz", "questions": []}'
            );
        }

        return new AcademyLesson($this->directory . '/' . $name . '.md');
    }

    public function testItIsALesson(): void
    {
        $lesson = $this->lesson('plain');

        $this->assertSame('academy', $lesson->type());
        $this->assertSame('lesson', $lesson->category());
    }

    public function testWithoutTestThereIsNoTestNorAttachment(): void
    {
        $lesson = $this->lesson('plain');

        $this->assertNull($lesson->test());
        $this->assertNull($lesson->testAttachment());
    }

    public function testTheTestCanBeAnAttachmentByItsName(): void
    {
        $lesson = $this->lesson('by-name', "test: \"?attachment=quiz.json\"\n", true);

        $this->assertSame('A quiz', $lesson->test()?->title());
        $this->assertSame('quiz.json', $lesson->testAttachment()?->name() . '.' . $lesson->testAttachment()?->extension());
    }

    public function testTheTestCanBeAnAttachmentByItsPath(): void
    {
        $lesson = $this->lesson('by-path', "test: \"/academy/by-path/_attachments/quiz.json\"\n", true);

        $this->assertSame('A quiz', $lesson->test()?->title());
        $this->assertSame('quiz', $lesson->testAttachment()?->name());
    }

    public function testATestThatIsNotAnAttachmentHasNoAttachment(): void
    {
        $lesson = $this->lesson('remote', "test: \"https://example.test/quiz.json\"\n");

        $this->assertNull($lesson->testAttachment());
    }

    public function testTheIconTellsIfTheLessonHasAVideoATestOrOnlyText(): void
    {
        $this->assertSame('fa-solid fa-play fa-fw', $this->lesson('video', "video: \"https://example.test/v.mp4\"\n")->icon());
        $this->assertSame(
            'fa-solid fa-question-circle fa-fw',
            $this->lesson('test', "test: \"https://example.test/quiz.json\"\n")->icon()
        );
        $this->assertSame('fa-solid fa-file-lines fa-fw', $this->lesson('text')->icon());
    }
}
