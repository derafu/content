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
    'Path {path} must be a readable file content.' =>
        'La ruta {path} debe ser un archivo de contenido legible.',
    'The parent of the content "{content}" can not be set after its URI, level, route or ancestors (or those of its children) were read. Set the parent before reading them.' =>
        'El padre del contenido "{content}" no se puede definir después de leer su URI, nivel, ruta o ancestros (o los de sus hijos). Define el padre antes de leerlos.',
    'Path {path} must be a readable attachment.' =>
        'La ruta {path} debe ser un adjunto legible.',
    'Plugin "{plugin}" not found. Available plugins: {plugins}.' =>
        'No se encontró el plugin "{plugin}". Plugins disponibles: {plugins}.',
    'Invalid academy test JSON: {error}' =>
        'JSON de prueba de academia inválido: {error}',
    'Invalid OpenAPI document: {error}' =>
        'Documento OpenAPI inválido: {error}',
    'No Markdown template known for content category "{category}".' =>
        'No se conoce una plantilla Markdown para la categoría de contenido "{category}".',
    'Query is required.' =>
        'La consulta es obligatoria.',
    'The "search" plugin has "llm_url" configured but no "llm_model". Set "llm_model" to the model name your LLM backend expects.' =>
        'El plugin "search" tiene "llm_url" configurado pero no "llm_model". Define "llm_model" con el nombre del modelo que espera tu backend de LLM.',
    'Key "name" is required for addRoute().' =>
        'La clave "name" es obligatoria para addRoute().',
    'Key "path" is required for addRoute().' =>
        'La clave "path" es obligatoria para addRoute().',
    'Key "handler" is required for addRoute().' =>
        'La clave "handler" es obligatoria para addRoute().',
    'Remote content at {url} could not be fetched: {error}' =>
        'No se pudo obtener el contenido remoto en {url}: {error}',
    'Remote content at {url} responded with status {status}.' =>
        'El contenido remoto en {url} respondió con el estado {status}.',
];
