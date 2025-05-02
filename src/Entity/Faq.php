<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Entity;

use Derafu\Content\Contract\FaqInterface;

/**
 * Class that represents a faq.
 */
final class Faq extends ContentFile implements FaqInterface
{
    /**
     * Question of the faq.
     *
     * @var string
     */
    private string $question;

    /**
     * {@inheritDoc}
     */
    public function question(): string
    {
        if (!isset($this->question)) {
            $this->question = $this->metadata('question') ?? $this->name();
        }

        return $this->question;
    }

    /**
     * {@inheritDoc}
     */
    public function answer(): string
    {
        return $this->content();
    }
}
