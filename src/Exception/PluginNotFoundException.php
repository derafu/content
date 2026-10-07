<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Exception;

use Derafu\Http\Contract\HttpExceptionInterface;
use Derafu\Http\Enum\HttpStatus;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;

/**
 * Exception for when a plugin is requested but the website did not enable it.
 *
 * The routes of every plugin are registered, enabled or not, so a request to
 * the route of a plugin that is off reaches its controller. Mapped to 404 Not
 * Found instead of the generic 500: there is nothing at that URL for this
 * website.
 *
 * It is still an invalid argument, which is what the callers that are not a
 * request (like the tools of MCP) already catch.
 */
class PluginNotFoundException extends TranslatableInvalidArgumentException implements HttpExceptionInterface
{
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
