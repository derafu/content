<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Exception;

use Derafu\Http\Contract\HttpExceptionInterface;
use Derafu\Http\Enum\HttpStatus;
use Derafu\Translation\Contract\TranslatableInterface;
use Derafu\Translation\Exception\Core\TranslatableRuntimeException;
use Throwable;

/**
 * Exception for when a requested content item (or one of its attachments)
 * does not exist, or is not allowed to be accessed (e.g. a draft outside a
 * local environment).
 *
 * Mapped to 404 Not Found instead of the generic 500: the request was
 * perfectly valid, the resource behind it just isn't there. A draft that
 * isn't allowed is treated the same way on purpose, so its existence isn't
 * revealed through a 403 instead.
 */
class ContentNotFoundException extends TranslatableRuntimeException implements HttpExceptionInterface
{
    /**
     * Constructor.
     *
     * @param string|array|TranslatableInterface $message The exception message.
     * @param Throwable|null $previous The previous throwable used for
     * exception chaining.
     */
    public function __construct(
        string|array|TranslatableInterface $message,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * {@inheritDoc}
     */
    public function getUriReference(): string
    {
        return 'https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/404';
    }

    /**
     * {@inheritDoc}
     */
    public function getTitle(): string
    {
        return 'Not Found';
    }

    /**
     * {@inheritDoc}
     */
    public function getStatus(): HttpStatus
    {
        return HttpStatus::NOT_FOUND;
    }

    /**
     * {@inheritDoc}
     */
    public function getContext(): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function getHeaders(): array
    {
        return [];
    }
}
