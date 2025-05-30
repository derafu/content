<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Plugin\Faq;

use Derafu\Content\Abstract\AbstractContentItem;
use Derafu\Content\Plugin\Faq\Contract\FaqQuestionInterface;

/**
 * Class that represents a FAQ.
 */
class FaqQuestion extends AbstractContentItem implements FaqQuestionInterface
{
    /**
     * {@inheritDoc}
     */
    protected array $metadataSchema = [
        '__allowUndefinedKeys' => true,
        'hide_table_of_contents' => [
            'types' => 'bool',
            'required' => true,
            'default' => true,
        ],
    ];

    /**
     * {@inheritDoc}
     */
    public function type(): string
    {
        return 'faq';
    }

    /**
     * {@inheritDoc}
     */
    public function category(): string
    {
        return 'question';
    }

    /**
     * {@inheritDoc}
     */
    public function parent(): ?FaqQuestionInterface
    {
        $parent = parent::parent();

        if ($parent === null) {
            return null;
        }

        assert($parent instanceof FaqQuestionInterface);

        return $parent;
    }

    /**
     * {@inheritDoc}
     */
    public function links(): array
    {
        if (!isset($this->links)) {
            $urlBasePath = '/faq';

            $this->links = [
                'self' => ['href' => $urlBasePath . '/' . $this->uri()],
                'collection' => ['href' => $urlBasePath],
            ];
        }

        return $this->links;
    }
}
