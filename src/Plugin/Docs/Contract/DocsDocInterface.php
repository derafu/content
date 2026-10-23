<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Plugin\Docs\Contract;

use Derafu\Content\Contract\ContentItemInterface;
use Derafu\Content\Plugin\Docs\DocsOpenApiView;
use Utopia\OpenAPI\Specification;

/**
 * Docs doc interface.
 */
interface DocsDocInterface extends ContentItemInterface
{
    /**
     * Get the parent of the content.
     *
     * @return DocsDocInterface|null
     */
    public function parent(): ?DocsDocInterface;

    /**
     * Get the children of the content.
     *
     * @return array<DocsDocInterface>
     */
    public function children(): array;

    /**
     * Get the doc's OpenAPI spec, parsed from its "openapi" frontmatter
     * reference (a local attachment or an absolute URL), or null if it has
     * none. It needs "utopia-php/openapi".
     *
     * @return Specification|null
     */
    public function openapiSpec(): ?Specification;

    /**
     * Get what the Markdown and the PDF show of the doc's OpenAPI spec: the
     * operations by tag, the types, the properties and the examples. It is
     * null if the doc has no spec.
     *
     * @return DocsOpenApiView|null
     */
    public function openapiView(): ?DocsOpenApiView;
}
