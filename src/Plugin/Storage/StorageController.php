<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Plugin\Storage;

use Derafu\Content\Contract\ContentPluginInterface;
use Derafu\Content\Contract\ContentServiceInterface;
use Derafu\Http\Request;
use Derafu\Http\Response;
use InvalidArgumentException;

class StorageController
{
    /**
     * Constructor.
     *
     * @param ContentServiceInterface $contentService Content service.
     */
    public function __construct(
        private readonly ContentServiceInterface $contentService
    ) {
    }

    /**
     * Download action.
     *
     * @param Request $request Request.
     * @param string $type Type.
     * @param string $uri URI of the content.
     * @param string $attachment Attachment file name.
     * @return Response
     */
    public function download(Request $request, string $type, string $uri, string $attachment): Response
    {
        $plugin = $this->contentService->plugin('storage');
        assert($plugin instanceof StoragePlugin);

        $filters = [
            ...array_filter($request->all(), fn ($value) => $value !== ''),
            'type' => $type,
            'uri' => $uri,
            'attachment' => $attachment,
        ];

        $plugin = $this->contentService->plugin($type);
        assert($plugin instanceof ContentPluginInterface);

        $knowledge = $plugin->registry()->filter($filters);

        if (!isset($knowledge[0])) {
            throw new InvalidArgumentException('Content not found.');
        }

        // Filter by attachment.
        if (!empty($filters['attachment'])) {
            $content = $knowledge[0];
            $attachment = $content->attachment($filters['attachment']);
            if (!$attachment) {
                throw new InvalidArgumentException('Attachment for the content not found.');
            }

            $response = new Response();
            $response->asText($attachment->raw(), $attachment->type());

            return $response;
        }

        throw new InvalidArgumentException('Attachment not found.');
    }
}
