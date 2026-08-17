<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Content\Abstract;

use Derafu\Http\Request;

abstract class AbstractContentController
{
    protected function getPreferredFormat(Request $request): string
    {
        $preferredFormat = $request->getPreferredFormat();
        return match ($preferredFormat) {
            'json' => 'json',
            'pdf' => 'pdf',
            'markdown' => 'md',
            default => 'html',
        };
    }
}
