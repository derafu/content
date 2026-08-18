<?php

declare(strict_types=1);

/**
 * Tiny real HTTP endpoint used by the test suite to exercise SearchEngine
 * and OpenAiCompatibleLlmClient against an actual socket (no mocked PSR-18
 * client), meant to be run through `php -S 127.0.0.1:<port> router.php`.
 *
 * The response is selected through a "scenario" query parameter, so each
 * test controls exactly what comes back without needing a stateful fake.
 */

header('Content-Type: application/json');

// Logs every request path to a fixed file so tests can assert, from the
// outside, exactly which URL a client actually requested (e.g. a custom
// "llm_completions_path"), without the client's own return value having
// to carry that information.
if (isset($_GET['log_to'])) {
    file_put_contents(
        $_GET['log_to'],
        parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) . "\n",
        FILE_APPEND
    );
}

$scenario = $_GET['scenario'] ?? 'results_ok';

[$status, $body] = match ($scenario) {
    'results_ok' => [200, ['results' => [
        ['id' => 'doc-1', 'title' => 'Fixture result one', 'score' => 0.91],
        ['id' => 'doc-2', 'title' => 'Fixture result two', 'score' => 0.77],
    ]]],
    'results_missing_field' => [200, ['unexpected' => true]],
    'results_bad_status' => [503, ['detail' => 'Search backend unavailable']],
    'chat_ok' => [200, ['choices' => [
        ['message' => ['content' => 'Hola, esta es una respuesta de prueba.']],
    ]]],
    'chat_missing_field' => [200, ['unexpected' => true]],
    'chat_error_detail' => [400, ['detail' => 'Model not found']],
    'chat_error_object' => [429, ['error' => ['message' => 'Rate limited', 'type' => 'rate_limit_error']]],
    'chat_error_string' => [400, ['error' => 'Bad request']],
    'invalid_json' => [200, null],
    default => [404, ['detail' => 'Unknown scenario']],
};

http_response_code($status);

if ($scenario === 'invalid_json') {
    echo '{this is not valid json';
    exit;
}

echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
