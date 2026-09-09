<?php

declare(strict_types=1);

/**
 * Derafu: Content - Where knowledge becomes product.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

return [
    'Content item with URI "{uri}" (slug: {slug}) is not allowed.' =>
        'El elemento de contenido con URI "{uri}" (slug: {slug}) no está permitido.',
    'Content item with URI "{uri}" not found.' =>
        'No se encontró el elemento de contenido con URI "{uri}".',
    'Content item with URI "{uri}" (slug: {slug}) not found.' =>
        'No se encontró el elemento de contenido con URI "{uri}" (slug: {slug}).',
    'Content not found.' =>
        'Contenido no encontrado.',
    'Attachment for the content not found.' =>
        'No se encontró el adjunto del contenido.',
    'Attachment not found.' =>
        'Adjunto no encontrado.',
    'Invalid archive "{date}": expected it to start with a "YYYYMM" prefix.' =>
        'Archivo "{date}" inválido: se esperaba que comenzara con un prefijo "AAAAMM".',
    'Module "{module}" not found.' =>
        'No se encontró el módulo "{module}".',
    'Lesson "{lesson}" not found.' =>
        'No se encontró la lección "{lesson}".',
    'The search engine at "{url}" could not be reached: {reason}.' =>
        'No se pudo contactar al motor de búsqueda en "{url}": {reason}.',
    'The search engine at "{url}" returned HTTP {status}{detail}.' =>
        'El motor de búsqueda en "{url}" respondió HTTP {status}{detail}.',
    'The search engine at "{url}" returned a response without a "results" field.' =>
        'El motor de búsqueda en "{url}" respondió sin un campo "results".',
    'The LLM backend at "{url}" could not be reached: {reason}.' =>
        'No se pudo contactar al backend de LLM en "{url}": {reason}.',
    'The LLM backend at "{url}" returned HTTP {status}{detail}.' =>
        'El backend de LLM en "{url}" respondió HTTP {status}{detail}.',
    'The LLM backend at "{url}" returned a response without a "choices[0].message.content" field.' =>
        'El backend de LLM en "{url}" respondió sin un campo "choices[0].message.content".',
];
