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

use Derafu\Content\Plugin\Docs\DocsOpenApiEndpoint;
use Derafu\Content\Plugin\Docs\DocsOpenApiEndpointGroup;
use Derafu\Content\Plugin\Docs\DocsOpenApiParameter;
use Derafu\Content\Plugin\Docs\DocsOpenApiResponse;
use Derafu\Content\Plugin\Docs\DocsOpenApiSpec;
use Derafu\Content\Plugin\Docs\DocsOpenApiTag;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Exercises DocsOpenApiSpec::fromRaw()/fromArray() against a real,
 * standard OpenAPI 3.0.3 document (the same shape found in the real
 * LibreDTE and apigateway.cl specs this was built against: no "$ref",
 * everything declared inline), without needing a doc/attachment at all.
 */
#[CoversClass(DocsOpenApiSpec::class)]
#[UsesClass(DocsOpenApiEndpoint::class)]
#[UsesClass(DocsOpenApiEndpointGroup::class)]
#[UsesClass(DocsOpenApiParameter::class)]
#[UsesClass(DocsOpenApiResponse::class)]
#[UsesClass(DocsOpenApiTag::class)]
final class DocsOpenApiSpecTest extends TestCase
{
    private const YAML = <<<'YAML'
        openapi: 3.0.3
        info:
          title: Fixture API
          description: A fixture API.
          version: 1.0.0
        servers:
          - url: https://fixture.example.test
        tags:
          - name: Widgets
            description: Widget-related endpoints.
        paths:
          /widgets:
            get:
              summary: List widgets
              tags: [Widgets]
              parameters:
                - name: q
                  in: query
                  required: false
                  description: Free-text filter.
                  schema:
                    type: string
              responses:
                '200':
                  description: A list of widgets.
            post:
              summary: Create a widget
              tags: [Widgets]
              requestBody:
                content:
                  application/json:
                    example:
                      name: "Widget"
              responses:
                '201':
                  description: Widget created.
          /gadgets:
            get:
              summary: List gadgets
              responses:
                '200':
                  description: A list of gadgets.
        YAML;

    public function testParsesInfoAndServersFromYaml(): void
    {
        $spec = DocsOpenApiSpec::fromRaw(self::YAML);

        $this->assertSame('Fixture API', $spec->title());
        $this->assertSame('A fixture API.', $spec->description());
        $this->assertSame('1.0.0', $spec->version());
        $this->assertSame(['https://fixture.example.test'], $spec->servers());
    }

    public function testParsesInfoAndServersFromJson(): void
    {
        $spec = DocsOpenApiSpec::fromRaw(json_encode([
            'openapi' => '3.0.3',
            'info' => ['title' => 'JSON Fixture API', 'version' => '2.0.0'],
            'servers' => [['url' => 'https://json.example.test']],
            'paths' => [],
        ]));

        $this->assertSame('JSON Fixture API', $spec->title());
        $this->assertSame('2.0.0', $spec->version());
        $this->assertSame(['https://json.example.test'], $spec->servers());
    }

    public function testEachHttpMethodOnAPathBecomesItsOwnEndpoint(): void
    {
        $spec = DocsOpenApiSpec::fromRaw(self::YAML);

        $this->assertCount(3, $spec->endpoints());
        $this->assertSame('GET', $spec->endpoints()[0]->method());
        $this->assertSame('/widgets', $spec->endpoints()[0]->path());
        $this->assertSame('POST', $spec->endpoints()[1]->method());
    }

    public function testTopLevelTagsAreParsed(): void
    {
        $spec = DocsOpenApiSpec::fromRaw(self::YAML);

        $this->assertCount(1, $spec->tags());
        $this->assertSame('Widgets', $spec->tags()[0]->name());
        $this->assertSame('Widget-related endpoints.', $spec->tags()[0]->description());
    }

    /**
     * "/widgets" GET/POST both declare "tags: [Widgets]", so they group
     * together under a "Widgets" group carrying the top-level tag's own
     * description. "/gadgets" GET declares no tag at all, so it lands in
     * its own untagged group instead of being silently dropped or merged
     * into "Widgets".
     */
    public function testEndpointsAreGroupedByTheirPrimaryTag(): void
    {
        $spec = DocsOpenApiSpec::fromRaw(self::YAML);
        $groups = $spec->endpointGroups();

        $this->assertCount(2, $groups);

        $this->assertSame('Widgets', $groups[0]->tagName());
        $this->assertSame('Widget-related endpoints.', $groups[0]->tagDescription());
        $this->assertCount(2, $groups[0]->endpoints());

        $this->assertNull($groups[1]->tagName());
        $this->assertNull($groups[1]->tagDescription());
        $this->assertCount(1, $groups[1]->endpoints());
        $this->assertSame('/gadgets', $groups[1]->endpoints()[0]->path());
    }

    public function testEndpointParametersAreParsed(): void
    {
        $spec = DocsOpenApiSpec::fromRaw(self::YAML);
        $get = $spec->endpoints()[0];

        $this->assertCount(1, $get->parameters());
        $this->assertSame('q', $get->parameters()[0]->name());
        $this->assertSame('query', $get->parameters()[0]->in());
        $this->assertFalse($get->parameters()[0]->required());
        $this->assertSame('string', $get->parameters()[0]->type());
    }

    public function testEndpointResponsesAreParsed(): void
    {
        $spec = DocsOpenApiSpec::fromRaw(self::YAML);
        $get = $spec->endpoints()[0];

        $this->assertCount(1, $get->responses());
        $this->assertSame('200', $get->responses()[0]->statusCode());
        $this->assertSame('A list of widgets.', $get->responses()[0]->description());
    }

    public function testRequestBodyExampleIsPrettyPrintedJson(): void
    {
        $spec = DocsOpenApiSpec::fromRaw(self::YAML);
        $post = $spec->endpoints()[1];

        $this->assertStringContainsString('"name": "Widget"', $post->requestBodyExample());
    }

    public function testInvalidYamlThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Invalid OpenAPI document/');

        DocsOpenApiSpec::fromRaw("openapi: 3.0.3\n  bad indent: [");
    }
}
