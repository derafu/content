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
use Derafu\Content\Abstract\AbstractContentPlugin;
use Derafu\Content\Abstract\AbstractContentRegistry;
use Derafu\Content\Abstract\AbstractPlugin;
use Derafu\Content\ContentAuthor;
use Derafu\Content\ContentBag;
use Derafu\Content\ContentConfig;
use Derafu\Content\ContentContext;
use Derafu\Content\ContentFilters;
use Derafu\Content\ContentLoader;
use Derafu\Content\ContentSplFileInfo;
use Derafu\Content\ContentTag;
use Derafu\Content\Plugin\Academy\AcademyCourse;
use Derafu\Content\Plugin\Academy\AcademyLesson;
use Derafu\Content\Plugin\Academy\AcademyModule;
use Derafu\Content\Plugin\Academy\AcademyPlugin;
use Derafu\Content\Plugin\Academy\AcademyRegistry;
use Derafu\Content\Plugin\Academy\Contract\AcademyRegistryInterface;
use Derafu\Support\File;
use Derafu\TestsContent\Support\ContentFixtures;
use Derafu\TestsContent\Support\RouterFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * The courses, modules and lessons know their routes and their neighbours, and
 * a directory that has no file of its own is not a course, a module or a
 * lesson, so what is inside of it is not in the registry.
 */
#[CoversClass(AcademyCourse::class)]
#[CoversClass(AcademyModule::class)]
#[CoversClass(AcademyLesson::class)]
#[CoversClass(AcademyRegistry::class)]
#[UsesClass(AbstractContentItem::class)]
#[UsesClass(AbstractContentPlugin::class)]
#[UsesClass(AbstractContentRegistry::class)]
#[UsesClass(AbstractPlugin::class)]
#[UsesClass(AcademyPlugin::class)]
#[UsesClass(ContentAuthor::class)]
#[UsesClass(ContentBag::class)]
#[UsesClass(ContentConfig::class)]
#[UsesClass(ContentContext::class)]
#[UsesClass(ContentFilters::class)]
#[UsesClass(ContentLoader::class)]
#[UsesClass(ContentSplFileInfo::class)]
#[UsesClass(ContentTag::class)]
final class AcademyRegistryNavigationTest extends TestCase
{
    private ?string $directory = null;

    protected function tearDown(): void
    {
        if ($this->directory !== null) {
            File::rmdir($this->directory);
        }
    }

    private function registry(): AcademyRegistryInterface
    {
        $plugin = new AcademyPlugin(ContentFixtures::contentContext(), ['path' => 'academy']);
        $plugin->loadContent(ContentFixtures::contentLoader());

        return $plugin->registry();
    }

    private function write(string $file, string $title): void
    {
        $path = $this->directory . '/academy/' . $file;
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, "---\ntitle: \"{$title}\"\n---\nThe text of {$title}.\n");
    }

    public function testEachLevelHasTheRouteThatTheWebsiteGeneratesItsUrlWith(): void
    {
        $registry = $this->registry();
        $router = RouterFixture::create();

        $course = $registry->get('demo-course');
        $module = $registry->get('demo-course/module-one');
        $lesson = $registry->get('demo-course/module-one/lesson-one');

        $this->assertSame('academy_course', $course->route()->name);
        $this->assertSame(['course' => 'demo-course'], $course->route()->params);
        $this->assertSame('academy_module', $module->route()->name);
        $this->assertSame(['course' => 'demo-course', 'module' => 'module-one'], $module->route()->params);
        $this->assertSame('academy_lesson', $lesson->route()->name);
        $this->assertSame(
            ['course' => 'demo-course', 'module' => 'module-one', 'lesson' => 'lesson-one'],
            $lesson->route()->params
        );

        foreach ([$course, $module, $lesson] as $item) {
            $url = $router->generate($item->route()->name, $item->route()->params);
            $this->assertStringEndsWith('/' . $item->slug(), $url);
        }
    }

    public function testEachLevelKnowsItsCategory(): void
    {
        $registry = $this->registry();

        $this->assertSame('course', $registry->get('demo-course')->category());
        $this->assertSame('module', $registry->get('demo-course/module-one')->category());
        $this->assertSame('lesson', $registry->get('demo-course/module-one/lesson-one')->category());
    }

    public function testALessonKnowsItsModuleAndItsCourse(): void
    {
        $lesson = $this->registry()->get('demo-course/module-one/lesson-one');

        $this->assertInstanceOf(AcademyLesson::class, $lesson);
        $this->assertSame('module-one', $lesson->module()->slug());
        $this->assertSame('demo-course', $lesson->module()->course()->slug());
    }

    public function testThePreviousAndTheNextOfALessonAreTheLessonsAround(): void
    {
        $registry = $this->registry();
        $filters = ['category' => 'lesson'];

        $this->assertSame(
            'demo-course/module-one/lesson-one',
            $registry->previous('demo-course/module-one/lesson-two', $filters)?->uri()
        );
        $this->assertSame(
            'demo-course/module-one/lesson-two',
            $registry->next('demo-course/module-one/lesson-one', $filters)?->uri()
        );
    }

    public function testTheLastLessonOfACourseHasNoNextAndTheFirstOneHasNoPrevious(): void
    {
        $registry = $this->registry();
        $filters = ['category' => 'lesson'];

        // The lesson that is after the last one of "course-with-draft-module"
        // in the list of the whole academy is the first one of "demo-course",
        // and it is not the next one: it is another course.
        $this->assertNull($registry->next('course-with-draft-module/visible-module/visible-lesson-a', $filters));
        $this->assertNull($registry->previous('demo-course/module-one/lesson-one', $filters));
        $this->assertNull($registry->next('demo-course/module-one/lesson-two', $filters));
        $this->assertNull($registry->previous('course-with-draft-module/visible-module/visible-lesson-a', $filters));
    }

    public function testThePreviousAndTheNextOfAModuleStayInItsCourse(): void
    {
        $registry = $this->registry();
        $filters = ['category' => 'module'];

        $this->assertNull($registry->previous('demo-course/module-one', $filters));
        $this->assertNull($registry->next('demo-course/module-one', $filters));
        $this->assertNull($registry->next('course-with-draft-module/visible-module', $filters));
        $this->assertNull($registry->previous('does-not-exist', $filters));
        $this->assertNull($registry->next('does-not-exist', $filters));
    }

    public function testWithoutFiltersTheNeighboursAreNeverACourse(): void
    {
        $registry = $this->registry();

        $this->assertNull($registry->previous('demo-course/module-one'));
        $this->assertSame('demo-course/module-one/lesson-one', $registry->next('demo-course/module-one')?->uri());
        $this->assertSame('demo-course/module-one/lesson-two', $registry->next('demo-course/module-one/lesson-one')?->uri());
    }

    public function testTheModulesAndTheLessonsOfAnotherCourseAreNotNeighbours(): void
    {
        $directory = sys_get_temp_dir() . '/academy-courses-' . uniqid();
        $this->directory = $directory;
        foreach (['one' => ['a', 'b'], 'two' => ['c']] as $course => $modules) {
            $this->write($course . '.md', $course);
            foreach ($modules as $module) {
                $this->write($course . '/' . $module . '.md', $module);
            }
        }

        $plugin = new AcademyPlugin(ContentFixtures::contentContext(), ['path' => 'academy']);
        $plugin->loadContent(new ContentLoader($directory));
        $registry = $plugin->registry();
        $filters = ['category' => 'module'];

        $this->assertSame('one/b', $registry->next('one/a', $filters)?->uri());
        $this->assertNull($registry->next('one/b', $filters));
        $this->assertNull($registry->previous('two/c', $filters));
        $this->assertSame('one/a', $registry->previous('one/b', $filters)?->uri());
    }

    public function testADirectoryWithoutItsOwnFileIsNotACourseAModuleOrALesson(): void
    {
        $directory = sys_get_temp_dir() . '/academy-navigation-' . uniqid();
        $this->directory = $directory;
        $this->write('course.md', 'Course');
        $this->write('course/module.md', 'Module');
        $this->write('course/module/lesson.md', 'Lesson');
        $this->write('course/loose-module/lesson.md', 'Lesson of a loose module');
        $this->write('course/module/loose-lesson/file.md', 'File of a loose lesson');
        $this->write('loose-course/module.md', 'Module of a loose course');

        $plugin = new AcademyPlugin(ContentFixtures::contentContext(), ['path' => 'academy']);
        $plugin->loadContent(new ContentLoader($directory));

        $uris = array_map(fn ($item) => $item->uri(), $plugin->registry()->flatten());
        sort($uris);

        $this->assertSame(['course', 'course/module', 'course/module/lesson'], $uris);
    }
}
