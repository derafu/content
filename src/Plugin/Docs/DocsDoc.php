<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Plugin\Docs;

use Derafu\Content\Abstract\AbstractContentItem;
use Derafu\Content\Plugin\Docs\Contract\DocsDocInterface;
use Derafu\Content\Plugin\Docs\Contract\DocsOpenApiSpecInterface;

/**
 * Class that represents a doc.
 */
class DocsDoc extends AbstractContentItem implements DocsDocInterface
{
    /**
     * OpenAPI spec of the doc, parsed from its attachment/remote
     * reference. False is used (never null) as the "not resolved yet"
     * memoization sentinel, same reasoning as AcademyLesson::$test.
     *
     * @var DocsOpenApiSpecInterface|false
     */
    private DocsOpenApiSpecInterface|false $openapiSpec;

    /**
     * {@inheritDoc}
     */
    public function type(): string
    {
        return 'docs';
    }

    /**
     * {@inheritDoc}
     */
    public function category(): string
    {
        return 'doc';
    }

    /**
     * {@inheritDoc}
     */
    public function parent(): ?DocsDocInterface
    {
        $parent = parent::parent();

        if ($parent === null) {
            return null;
        }

        assert($parent instanceof DocsDocInterface);

        return $parent;
    }

    /**
     * {@inheritDoc}
     */
    public function links(): array
    {
        if (!isset($this->links)) {
            $urlBasePath = '/docs';

            $this->links = [
                'self' => ['href' => $urlBasePath . '/' . $this->uri()],
                'collection' => ['href' => $urlBasePath],
            ];
        }

        return $this->links;
    }

    /**
     * {@inheritDoc}
     */
    public function openapiSpec(): ?DocsOpenApiSpecInterface
    {
        if (!isset($this->openapiSpec)) {
            $raw = $this->resolveReferenceContent('openapi');

            $this->openapiSpec = $raw !== null
                ? DocsOpenApiSpec::fromRaw($raw)
                : false;
        }

        return $this->openapiSpec ?: null;
    }
}
