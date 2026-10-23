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
    'The file "{path}" could not be read.' =>
        'No se pudo leer el archivo "{path}".',
    'The directory "{path}" could not be listed.' =>
        'No se pudo listar el directorio "{path}".',
    'The content "{content}" could not be processed: {error}' =>
        'No se pudo procesar el contenido "{content}": {error}',
    'The frontmatter of "{content}" is not valid YAML: {error}' =>
        'El frontmatter de "{content}" no es un YAML válido: {error}',
    'The frontmatter of "{content}" must be an object, {type} given.' =>
        'El frontmatter de "{content}" debe ser un objeto, se entregó {type}.',
    'Plugin "{plugin}" not found. Available plugins: {plugins}.' =>
        'No se encontró el plugin "{plugin}". Plugins disponibles: {plugins}.',
    'Invalid academy test JSON: {error}' =>
        'JSON de prueba de academia inválido: {error}',
    'The lesson "{content}" must be in a module of a course to have a route.' =>
        'La lección "{content}" debe estar en un módulo de un curso para tener una ruta.',
    'The module "{content}" must be in a course to have a route.' =>
        'El módulo "{content}" debe estar en un curso para tener una ruta.',
    'The options of the plugin "{plugin}" must be an object, {type} given.' =>
        'Las opciones del plugin "{plugin}" deben ser un objeto, se entregó {type}.',
    'The "class" of the plugin "{plugin}" must be a class name, {type} given.' =>
        'El "class" del plugin "{plugin}" debe ser el nombre de una clase, se entregó {type}.',
    'The class "{class}" of the plugin "{plugin}" does not exist.' =>
        'La clase "{class}" del plugin "{plugin}" no existe.',
    'The class "{class}" of the plugin "{plugin}" must implement {interface}.' =>
        'La clase "{class}" del plugin "{plugin}" debe implementar {interface}.',
    'The filter "{filter}" must be {expected}, {given} given.' =>
        'El filtro "{filter}" debe ser {expected}, se entregó {given}.',
    'The query "q" must be a text, {type} given.' =>
        'La consulta "q" debe ser un texto, se entregó {type}.',
    'The "search" plugin has no LLM configured: set "llm_url" and "llm_model".' =>
        'El plugin "search" no tiene un LLM configurado: define "llm_url" y "llm_model".',
    'The search engine at "{url}" returned an invalid "results" field: {error}' =>
        'El motor de búsqueda en "{url}" devolvió un campo "results" inválido: {error}',
    'The LLM backend at "{url}" returned an invalid response: {error}' =>
        'El backend LLM en "{url}" devolvió una respuesta inválida: {error}',
    'The LLM backend at "{url}" returned a "choices[0].message.content" that is not a text, {type} given.' =>
        'El backend LLM en "{url}" devolvió un "choices[0].message.content" que no es un texto, se entregó {type}.',
    'The academy test must be a JSON object, {type} given.' =>
        'La prueba de academia debe ser un objeto JSON, se entregó {type}.',
    'The doc "{content}" has "openapi" but "utopia-php/openapi" is not installed. Run: composer require utopia-php/openapi' =>
        'El documento "{content}" tiene "openapi" pero "utopia-php/openapi" no está instalado. Ejecuta: composer require utopia-php/openapi',
    'The "openapi" reference "{reference}" of the doc "{content}" does not resolve to a document: it must be an attachment of the doc or an absolute URL.' =>
        'La referencia "openapi" "{reference}" del documento "{content}" no corresponde a un documento: debe ser un adjunto del documento o una URL absoluta.',
    'The OpenAPI document of "{content}" is not valid: {error}' =>
        'El documento OpenAPI de "{content}" no es válido: {error}',
    'The request body example of "{operation}" in the doc "{content}" could not be encoded as JSON: {error}' =>
        'El ejemplo del cuerpo de la petición de "{operation}" en el documento "{content}" no se pudo codificar como JSON: {error}',
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
