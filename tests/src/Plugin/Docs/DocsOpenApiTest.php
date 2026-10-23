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
use Derafu\Translation\Contract\TranslatableInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;
use Utopia\OpenAPI\Model\Operation;

/**
 * A doc that has "openapi" in its frontmatter reads the document it points to
 * with utopia-php/openapi. What is wrong in the reference or in the document
 * is an error that says which doc it is.
 */
#[CoversClass(DocsDoc::class)]
#[UsesClass(AbstractContentItem::class)]
#[UsesClass(ContentAttachment::class)]
#[UsesClass(ContentSplFileInfo::class)]
final class DocsOpenApiTest extends TestCase
{
    private function doc(string $name): DocsDoc
    {
        return new DocsDoc(dirname(__DIR__, 3) . '/fixtures/openapi/' . $name . '.md');
    }

    /**
     * @return array<string, Operation>
     */
    private function operations(DocsDoc $doc): array
    {
        $operations = [];
        foreach ($doc->openapiSpec()?->operations() ?? [] as $operation) {
            $operations[strtoupper($operation->method->value) . ' ' . $operation->path] = $operation;
        }

        return $operations;
    }

    public function testADocWithoutOpenapiHasNoSpec(): void
    {
        $doc = new DocsDoc(dirname(__DIR__, 3) . '/fixtures/content/docs/guide.md');

        $this->assertNull($doc->openapiSpec());
        $this->assertSame([], $doc->openapiGroups());
    }

    public function testTheSpecIsReadFromYamlAndTheInfoIsThere(): void
    {
        $spec = $this->doc('full')->openapiSpec();

        $this->assertNotNull($spec);
        $this->assertSame('Full API', $spec->info->title);
        $this->assertSame('An API with a bit of everything.', $spec->info->description);
        $this->assertSame(
            ['https://api.example.test', 'https://sandbox.example.test'],
            array_map(fn ($server) => $server->url, $spec->servers)
        );
    }

    public function testTheSpecIsReadFromJson(): void
    {
        $spec = $this->doc('json-spec')->openapiSpec();

        $this->assertSame('JSON API', $spec?->info->title);
        $this->assertCount(1, $spec->operations());
    }

    public function testTheSpecIsReadOnlyOnce(): void
    {
        $doc = $this->doc('full');

        $this->assertSame($doc->openapiSpec(), $doc->openapiSpec());
    }

    public function testThePathParametersAreInheritedByTheOperationsAndTheOperationOverridesThem(): void
    {
        $operations = $this->operations($this->doc('full'));

        $get = array_map(fn ($p) => $p->name . ':' . $p->description, $operations['GET /widgets/{id}']->parameters);
        $delete = array_map(fn ($p) => $p->name, $operations['DELETE /widgets/{id}']->parameters);

        $this->assertSame(['id:Identifier of the widget.', 'q:Overridden by the operation.'], $get);
        $this->assertSame(['id', 'q'], $delete);
    }

    public function testTheInternalReferencesAreResolved(): void
    {
        $operations = $this->operations($this->doc('full'));

        $this->assertSame('limit', $operations['POST /widgets']->parameters[0]->name);
        $this->assertSame(
            'The widget does not exist.',
            $operations['GET /widgets/{id}']->responses['404']->description
        );
    }

    public function testTheOperationsAreGroupedByTheirFirstTag(): void
    {
        $groups = $this->doc('full')->openapiGroups();

        $this->assertSame(
            ['Widgets', 'Gadgets', null],
            array_map(fn (array $group) => $group['name'], $groups)
        );
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

    public function testTheTypeOfAParameterHasAName(): void
    {
        $doc = $this->doc('full');
        $parameters = [];
        foreach ($this->operations($doc)['POST /widgets']->parameters as $parameter) {
            $parameters[$parameter->name] = $doc->openapiTypeLabel($parameter->schema);
        }

        $this->assertSame([
            'limit' => 'integer',
            'tags' => 'array',
            'filter' => 'object',
            'ratio' => 'number',
            'dry' => 'boolean',
            'kind' => 'Kind',
            'free' => '',
        ], $parameters);

        $this->assertSame('string', $doc->openapiTypeLabel($this->operations($doc)['GET /widgets/{id}']->parameters[0]->schema));
    }

    public function testTheRequestBodyExampleIsTheFirstOneThatTheOperationHas(): void
    {
        $doc = $this->doc('full');
        $operations = $this->operations($doc);

        $this->assertSame(
            "{\n    \"name\": \"Widget\",\n    \"quantity\": 3\n}",
            $doc->openapiRequestExample($operations['POST /widgets'])
        );
        $this->assertSame(
            "{\n    \"gadget\": true\n}",
            $doc->openapiRequestExample($operations['GET /gadgets'])
        );
        $this->assertSame('plain text', $doc->openapiRequestExample($operations['PUT /text']));
        $this->assertNull($doc->openapiRequestExample($operations['GET /health']));
    }

    /**
     * @return array<string, array{string, non-empty-string}>
     */
    public static function provideDocsThatCanNotBeRead(): array
    {
        return [
            'a reference that is not an attachment nor an absolute URL' => [
                'site-path',
                'The "openapi" reference "/api/openapi-docs.json" of the doc "site-path" does not resolve to a document: it must be an attachment of the doc or an absolute URL.',
            ],
            'an attachment that does not exist' => [
                'missing-attachment',
                'The "openapi" reference "?attachment=nothing.yaml" of the doc "missing-attachment" does not resolve to a document: it must be an attachment of the doc or an absolute URL.',
            ],
            'a document that is not an object' => [
                'not-a-document',
                'The OpenAPI document of "not-a-document" is not valid: ',
            ],
            'a value with the wrong type' => [
                'invalid-spec',
                'The OpenAPI document of "invalid-spec" is not valid: Expected string #/info/title',
            ],
            'a YAML that is not valid' => [
                'invalid-yaml',
                'The OpenAPI document of "invalid-yaml" is not valid: ',
            ],
            'a JSON that is not valid' => [
                'invalid-json',
                'The OpenAPI document of "invalid-json" is not valid: ',
            ],
            'a reference to another document' => [
                'external-ref',
                'The OpenAPI document of "external-ref" is not valid: External reference is not configured: https://example.test/common.yaml#/components/parameters/Id',
            ],
        ];
    }

    /**
     * @param non-empty-string $message
     */
    #[DataProvider('provideDocsThatCanNotBeRead')]
    public function testADocThatCanNotBeReadIsATranslatableErrorThatSaysWhich(string $name, string $message): void
    {
        $exception = null;
        try {
            $this->doc($name)->openapiSpec();
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertInstanceOf(TranslatableInterface::class, $exception);
        $this->assertStringStartsWith($message, $exception->getMessage());
    }

    public function testAnExampleThatCanNotBeEncodedIsATranslatableErrorThatSaysWhich(): void
    {
        $doc = $this->doc('bad-example');
        $operation = $this->operations($doc)['POST /a'];

        $exception = null;
        try {
            $doc->openapiRequestExample($operation);
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertInstanceOf(TranslatableInterface::class, $exception);
        $this->assertStringStartsWith(
            'The request body example of "POST /a" in the doc "bad-example" could not be encoded as JSON: ',
            $exception->getMessage()
        );
    }

    public function testTheReferenceCanBeThePathOfAnAttachment(): void
    {
        $this->assertSame('JSON API', $this->doc('attachment-path')->openapiSpec()?->info->title);
    }
}
