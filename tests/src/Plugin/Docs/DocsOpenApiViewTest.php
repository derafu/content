<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContent\Plugin\Docs;

use Derafu\Content\Abstract\AbstractContentItem;
use Derafu\Content\ContentAttachment;
use Derafu\Content\ContentSplFileInfo;
use Derafu\Content\Plugin\Docs\DocsDoc;
use Derafu\Content\Plugin\Docs\DocsOpenApiView;
use Derafu\Translation\Contract\TranslatableInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;
use Utopia\OpenAPI\Model\Operation;

/**
 * What the exports show of an OpenAPI document: the operations by tag, the
 * type of each schema, the properties of an object and the examples. An
 * example is what the author of the document wrote, in any of the places where
 * it can be written, and nothing that is invented from a type.
 */
#[CoversClass(DocsOpenApiView::class)]
#[UsesClass(AbstractContentItem::class)]
#[UsesClass(ContentAttachment::class)]
#[UsesClass(ContentSplFileInfo::class)]
#[UsesClass(DocsDoc::class)]
final class DocsOpenApiViewTest extends TestCase
{
    private function view(string $name): DocsOpenApiView
    {
        $view = (new DocsDoc(dirname(__DIR__, 3) . '/fixtures/openapi/' . $name . '.md'))->openapiView();
        $this->assertNotNull($view);

        return $view;
    }

    private function operation(string $name, string $key): Operation
    {
        $spec = (new DocsDoc(dirname(__DIR__, 3) . '/fixtures/openapi/' . $name . '.md'))->openapiSpec();
        $this->assertNotNull($spec);

        foreach ($spec->operations() as $operation) {
            if (strtoupper($operation->method->value) . ' ' . $operation->path === $key) {
                return $operation;
            }
        }

        $this->fail('The operation ' . $key . ' is not in the document.');
    }

    public function testTheOperationsAreGroupedByTheirFirstTag(): void
    {
        $groups = $this->view('full')->groups();

        $this->assertSame(['Widgets', 'Gadgets', null], array_map(fn (array $group) => $group['name'], $groups));
        $this->assertSame(
            ['Everything about the widgets.', '', ''],
            array_map(fn (array $group) => $group['description'], $groups)
        );
        $this->assertSame(
            [['GET /widgets/{id}', 'DELETE /widgets/{id}', 'POST /widgets'], ['GET /gadgets', 'PUT /text'], ['GET /health']],
            array_map(
                fn (array $group) => array_map(
                    fn (Operation $operation) => strtoupper($operation->method->value) . ' ' . $operation->path,
                    $group['operations']
                ),
                $groups
            )
        );
    }

    public function testTheTypeOfASchemaHasAName(): void
    {
        $view = $this->view('full');
        $types = [];
        foreach ($this->operation('full', 'POST /widgets')->parameters as $parameter) {
            $types[$parameter->name] = $view->typeLabel($parameter->schema);
        }

        $this->assertSame([
            'limit' => 'integer',
            'tags' => 'array<string>',
            'filter' => 'object',
            'ratio' => 'number',
            'dry' => 'boolean',
            'kind' => 'Kind',
            'free' => '',
        ], $types);
        $this->assertSame('', $view->typeLabel(null));
    }

    public function testTheFormatAndTheNullableAreInTheType(): void
    {
        $since = $this->operation('rich', 'GET /orders')->parameters[1];
        $nullable = $this->operation('variants', 'GET /parameters')->parameters[2];

        $this->assertSame('string (date)', $this->view('rich')->typeLabel($since->schema));
        $this->assertSame('string|null', $this->view('variants')->typeLabel($nullable->schema));
    }

    public function testAParameterHasItsAllowedValuesItsDefaultAndItsExamples(): void
    {
        $view = $this->view('rich');
        $operation = $this->operation('rich', 'GET /orders');
        [$status, $since, $limit, $page] = $operation->parameters;

        $this->assertSame(
            ['enum' => ['open', 'closed'], 'default' => 'open', 'examples' => []],
            $view->parameterNotes($operation, $status)
        );
        $this->assertSame(
            [['summary' => '', 'text' => '2026-01-31']],
            $view->parameterNotes($operation, $since)['examples']
        );
        $this->assertSame(
            [['summary' => 'A few', 'text' => '10'], ['summary' => 'big', 'text' => '500']],
            $view->parameterNotes($operation, $limit)['examples']
        );
        $this->assertSame(
            [['summary' => '', 'text' => '1']],
            $view->parameterNotes($operation, $page)['examples']
        );
    }

    public function testTheExamplesOfAParameterThatIsAReferenceOrIsInThePathAreFound(): void
    {
        $view = $this->view('variants');
        $operation = $this->operation('variants', 'GET /parameters');
        [$region, $plain] = $operation->parameters;

        $this->assertSame([['summary' => 'The north', 'text' => 'N']], $view->parameterNotes($operation, $region)['examples']);
        $this->assertSame([['summary' => '', 'text' => 'one']], $view->parameterNotes($operation, $plain)['examples']);

        $delete = $this->operation('rich', 'DELETE /orders/{id}');
        $this->assertSame(
            [['summary' => '', 'text' => '7']],
            $this->view('rich')->parameterNotes($delete, $delete->parameters[0])['examples']
        );
    }

    public function testTheSameExampleInSeveralMediaTypesIsShownOnce(): void
    {
        $content = $this->operation('variants', 'POST /same')->requestBody->content ?? [];

        $examples = $this->view('variants')->examples($content);

        $this->assertSame(
            [
                ['types' => ['application/json', 'application/xml'], 'summary' => '', 'text' => "{\n    \"name\": \"Ana\"\n}"],
                ['types' => ['text/plain'], 'summary' => '', 'text' => 'plain text'],
            ],
            $examples
        );
    }

    public function testTheNamedExamplesKeepTheirSummary(): void
    {
        $content = $this->operation('rich', 'GET /orders')->responses['200']->content;

        $examples = $this->view('rich')->examples($content);

        $this->assertCount(1, $examples);
        $this->assertSame('Two orders', $examples[0]['summary']);
        $this->assertSame(['application/json'], $examples[0]['types']);
        $this->assertStringContainsString('"status": "closed"', $examples[0]['text']);
    }

    public function testTheExampleIsPutTogetherWithTheValuesThatThePropertiesSay(): void
    {
        $view = $this->view('variants');

        // The example of a property is the one of its whole branch, and a
        // default counts; a property with a type only does not.
        $nested = $view->examples($this->operation('variants', 'POST /nested')->requestBody->content ?? []);
        $this->assertSame(
            "{\n    \"parameters\": {\n        \"bag\": {\n            \"kind\": \"sales\"\n        }\n    },\n    \"options\": {\n        \"verbose\": false\n    }\n}",
            $nested[0]['text']
        );

        $composed = $view->examples($this->operation('variants', 'POST /composed')->requestBody->content ?? []);
        $this->assertSame("{\n    \"id\": 5,\n    \"extra\": \"more\"\n}", $composed[0]['text']);

        $cycle = $view->examples($this->operation('variants', 'POST /cycle')->requestBody->content ?? []);
        $this->assertSame("{\n    \"name\": \"root\"\n}", $cycle[0]['text']);
    }

    public function testWithoutAnyValueThereIsNoExample(): void
    {
        $content = $this->operation('variants', 'POST /nothing')->requestBody->content ?? [];

        $this->assertSame([], $this->view('variants')->examples($content));
        $this->assertSame([], $this->view('variants')->examples([]));
    }

    public function testEachMediaTypeHasItsOwnExampleWhenTheyDiffer(): void
    {
        $content = $this->operation('rich', 'POST /orders')->requestBody->content ?? [];

        $examples = $this->view('rich')->examples($content);

        $this->assertSame(['application/json'], $examples[0]['types']);
        $this->assertSame(['multipart/form-data'], $examples[1]['types']);
        $this->assertSame("{\n    \"customer\": \"Ana\"\n}", $examples[1]['text']);
    }

    public function testThePropertiesAreListedWithTheirDepth(): void
    {
        $view = $this->view('rich');
        $operation = $this->operation('rich', 'POST /orders');

        $rows = $view->properties($view->schemaOf($operation->requestBody->content ?? []));

        $this->assertSame(
            [
                [0, 'customer', 'string', true, 'Name of the customer.'],
                [0, 'items', 'array<object>', false, ''],
                [1, 'sku', 'string', true, ''],
                [1, 'quantity', 'integer', false, ''],
                [0, 'note', 'string', false, ''],
            ],
            array_map(fn (array $row) => [$row['depth'], $row['name'], $row['type'], $row['required'], $row['description']], $rows)
        );
        $this->assertSame('Ana', $rows[0]['example']);
        $this->assertSame('1', $rows[3]['default']);
    }

    public function testThePropertiesOfAReferenceAreTheOnesOfItsSchemaAndTheAllowedValuesAreThere(): void
    {
        $view = $this->view('rich');
        $content = $this->operation('rich', 'GET /orders')->responses['200']->content;

        $rows = $view->properties($view->schemaOf($content));

        $this->assertSame(['id', 'status', 'customer', 'name'], array_column($rows, 'name'));
        $this->assertSame(['open', 'closed'], $rows[1]['enum']);
        $this->assertSame([0, 0, 0, 1], array_column($rows, 'depth'));
    }

    public function testThePropertiesStopAtTheDepthThatIsListedAndAtACycle(): void
    {
        $view = $this->view('variants');

        $deep = $view->properties($view->schemaOf($this->operation('variants', 'POST /deep')->requestBody->content ?? []));
        $cycle = $view->properties($view->schemaOf($this->operation('variants', 'POST /cycle')->requestBody->content ?? []));

        $this->assertSame(['one', 'two', 'three'], array_column($deep, 'name'));
        $this->assertSame(['name', 'next'], array_column($cycle, 'name'));
    }

    public function testThePropertiesOfWhatIsNotAnObjectAreNone(): void
    {
        $view = $this->view('rich');

        $this->assertSame([], $view->properties(null));
        $this->assertSame([], $view->properties($this->operation('rich', 'GET /orders')->parameters[0]->schema));
        $this->assertNull($view->schemaOf([]));
    }

    public function testAChoiceHasItsCompositionAsTypeAndTheValueOfItsFirstPartThatHasOne(): void
    {
        $view = $this->view('edge');
        $content = $this->operation('edge', 'POST /one-of')->requestBody->content ?? [];

        $rows = $view->properties($view->schemaOf($content));

        $this->assertSame(['choice', 'card', 'cash', 'none'], array_column($rows, 'name'));
        $this->assertSame(['oneOf', 'string', 'boolean', 'anyOf'], array_column($rows, 'type'));
        $this->assertSame("{\n    \"choice\": {\n        \"card\": 4242\n    }\n}", $view->examples($content)[0]['text']);
    }

    public function testAParameterThatHasContentInsteadOfASchemaHasNoTypeNorNotes(): void
    {
        $view = $this->view('edge');
        $operation = $this->operation('edge', 'POST /one-of');
        $parameter = $operation->parameters[0];

        $this->assertSame('', $view->typeLabel($parameter->schema));
        $this->assertSame(['enum' => [], 'default' => null, 'examples' => []], $view->parameterNotes($operation, $parameter));
    }

    public function testTheDefaultOfASchemaThatIsAValueIsItsExample(): void
    {
        $content = $this->operation('edge', 'POST /text')->requestBody->content ?? [];

        $this->assertSame('hello', $this->view('edge')->examples($content)[0]['text']);
    }

    public function testASchemaInAnotherDocumentHasNeitherPropertiesNorExample(): void
    {
        $view = $this->view('edge');
        $content = $this->operation('edge', 'POST /external')->requestBody->content ?? [];

        $this->assertSame([], $view->properties($view->schemaOf($content)));
        $this->assertSame([], $view->examples($content));
    }

    public function testAnExampleThatIsTooDeepIsNotLookedFor(): void
    {
        $content = $this->operation('edge', 'POST /too-deep')->requestBody->content ?? [];

        $this->assertSame([], $this->view('edge')->examples($content));
    }

    public function testWithoutTheDocumentTheExamplesOfAParameterAreTheOnesOfItsSchema(): void
    {
        $doc = new DocsDoc(dirname(__DIR__, 3) . '/fixtures/openapi/rich.md');
        $spec = $doc->openapiSpec();
        $this->assertNotNull($spec);
        $view = new DocsOpenApiView($spec, [], 'rich');
        $operation = $this->operation('rich', 'GET /orders');

        // "since" has the example in its schema and "limit" only in the parameter.
        $this->assertSame([['summary' => '', 'text' => '2026-01-31']], $view->parameterNotes($operation, $operation->parameters[1])['examples']);
        $this->assertSame([], $view->parameterNotes($operation, $operation->parameters[2])['examples']);
    }

    public function testAReferenceThatCanNotBeFollowedHasNoExamples(): void
    {
        $doc = new DocsDoc(dirname(__DIR__, 3) . '/fixtures/openapi/variants.md');
        $spec = $doc->openapiSpec();
        $this->assertNotNull($spec);
        $operation = $this->operation('variants', 'GET /parameters');

        foreach (['#/components/parameters/Missing', 'https://example.test/common.json#/Region', '#/components'] as $reference) {
            $raw = ['paths' => ['/parameters' => ['get' => ['parameters' => [['$ref' => $reference]]]]]];

            $this->assertSame(
                [],
                (new DocsOpenApiView($spec, $raw, 'variants'))->parameterNotes($operation, $operation->parameters[0])['examples'],
                $reference
            );
        }
    }

    public function testAnExampleThatCanNotBeEncodedIsATranslatableErrorThatSaysWhich(): void
    {
        $doc = new DocsDoc(dirname(__DIR__, 3) . '/fixtures/openapi/bad-example.md');
        $view = $doc->openapiView();
        $this->assertNotNull($view);

        $exception = null;
        try {
            $view->examples($this->operation('bad-example', 'POST /a')->requestBody->content ?? []);
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertInstanceOf(TranslatableInterface::class, $exception);
        $this->assertStringStartsWith(
            'An example of the OpenAPI document of "bad-example" could not be encoded as JSON: ',
            $exception->getMessage()
        );
    }

    public function testASchemaThatSaysNothingHasNoType(): void
    {
        $operation = $this->operation('edge', 'POST /one-of');

        $this->assertSame('', $this->view('edge')->typeLabel($operation->parameters[1]->schema));
    }
}
