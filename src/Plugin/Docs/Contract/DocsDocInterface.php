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
use Utopia\OpenAPI\Model\Operation;
use Utopia\OpenAPI\Model\Schema;
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
     * Get the operations of the doc's OpenAPI spec grouped by their first tag,
     * in the order of the tags of the spec and, after them, the operations
     * without tag (name null).
     *
     * @return list<array{name: string|null, description: string, operations: list<Operation>}>
     */
    public function openapiGroups(): array;

    /**
     * Get the name of the type of a schema, to show it next to a parameter.
     *
     * @param Schema|null $schema Schema of the parameter.
     * @return string
     */
    public function openapiTypeLabel(?Schema $schema): string;

    /**
     * Get a printable example of the request body of an operation: the first
     * example found across its media types, or null if it has none.
     *
     * @param Operation $operation Operation of the doc's OpenAPI spec.
     * @return string|null
     */
    public function openapiRequestExample(Operation $operation): ?string;
}
