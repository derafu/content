<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Plugin\Search\Exception;

use Derafu\Http\Contract\HttpExceptionInterface;
use Derafu\Http\Enum\HttpStatus;
use Derafu\Translation\Contract\TranslatableInterface;
use Derafu\Translation\Exception\Core\TranslatableRuntimeException;
use Throwable;

/**
 * Exception for when a service the "search" plugin depends on (the search
 * engine or the LLM backend) fails or returns an unexpected response.
 *
 * This is deliberately not treated as an internal server error: the request
 * to this server was fine, it is whatever this server depends on that
 * failed. It is mapped to 502 Bad Gateway instead of the generic 500.
 */
class SearchUpstreamException extends TranslatableRuntimeException implements HttpExceptionInterface
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
        return 'https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/502';
    }

    /**
     * {@inheritDoc}
     */
    public function getTitle(): string
    {
        return 'Bad Gateway';
    }

    /**
     * {@inheritDoc}
     */
    public function getStatus(): HttpStatus
    {
        return HttpStatus::BAD_GATEWAY;
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
