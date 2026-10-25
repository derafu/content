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
$logTo = $_GET['log_to'] ?? null;
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
if (is_string($logTo) && is_string($requestUri)) {
    file_put_contents(
        $logTo,
        parse_url($requestUri, PHP_URL_PATH) . "\n",
        FILE_APPEND
    );
}

$scenario = $_GET['scenario'] ?? 'results_ok';

[$status, $body] = match ($scenario) {
    // "type"/"uri" point at real docs fixtures (both fully visible: not
    // draft, not unlisted, searchable) so SearchResultsFilter lets both
    // through unchanged — this scenario is about the raw engine response
    // shape, not about the post-filter, which has its own scenario below.
    'results_ok' => [200, ['results' => [
        ['id' => 'doc-1', 'type' => 'docs', 'uri' => 'guide', 'title' => 'Fixture result one', 'score' => 0.91],
        ['id' => 'doc-2', 'type' => 'docs', 'uri' => 'index', 'title' => 'Fixture result two', 'score' => 0.77],
    ]]],
    // Mixed results for SearchController's post-filter: one real,
    // fully-visible doc ("guide"), one searchable:false, one draft (stale
    // index entry that later became draft), one unlisted, and one whose
    // uri no longer exists in the registry at all (another stale entry).
    'results_mixed_searchability' => [200, ['results' => [
        ['type' => 'docs', 'uri' => 'guide', 'title' => 'Guide', 'score' => 0.95],
        ['type' => 'docs', 'uri' => 'not-searchable', 'title' => 'Not Searchable', 'score' => 0.9],
        ['type' => 'docs', 'uri' => 'draft-parent', 'title' => 'Draft section', 'score' => 0.85],
        ['type' => 'docs', 'uri' => 'not-listed', 'title' => 'Not Listed', 'score' => 0.8],
        ['type' => 'docs', 'uri' => 'ghost-nonexistent', 'title' => 'Ghost', 'score' => 0.75],
    ]]],
    'results_missing_field' => [200, ['unexpected' => true]],
    // Results with a long text, and with a text that is not one.
    'results_long_data' => [200, ['results' => [
        ['type' => 'docs', 'uri' => 'guide', 'title' => 'Guide', 'data' => '  ' . 'word ' . str_repeat('x', 400) . '  '],
        ['type' => 'docs', 'uri' => 'index', 'title' => 'Index', 'data' => ['not', 'a', 'text']],
        ['type' => 'docs', 'uri' => 'guide/getting-started', 'title' => 'Child', 'data' => 'Short text.'],
    ]]],
    'results_not_list' => [200, ['results' => 'none']],
    'results_item_not_object' => [200, ['results' => [['type' => 'docs', 'uri' => 'guide'], 'guide']]],
    'results_item_list' => [200, ['results' => [['guide']]]],
    'results_item_numeric_key' => [200, ['results' => [['id' => 'doc-1', 7 => 'extra']]]],
    // Results whose type or uri is not a text: SearchResultsFilter drops them.
    'results_bad_types' => [200, ['results' => [
        ['type' => 5, 'uri' => 'guide'],
        ['type' => 'docs', 'uri' => ['guide']],
        ['type' => 'docs', 'uri' => 'guide', 'title' => 'Guide'],
    ]]],
    'results_bad_status' => [503, ['detail' => 'Search backend unavailable']],
    'results_bad_status_unknown' => [502, ['unexpected' => true]],
    'results_bad_status_text' => [502, null],
    'results_bad_status_message' => [500, ['message' => 'Index is rebuilding']],
    'chat_ok' => [200, ['choices' => [
        ['message' => ['content' => 'Hello, this is a test answer.']],
    ]]],
    'chat_missing_field' => [200, ['unexpected' => true]],
    'chat_content_not_text' => [200, ['choices' => [['message' => ['content' => ['not', 'a', 'text']]]]]],
    'chat_choices_not_list' => [200, ['choices' => 'none']],
    'chat_choice_list' => [200, ['choices' => [['answer']]]],
    'chat_choice_numeric_key' => [200, ['choices' => [['message' => ['content' => 'answer'], 7 => 'extra']]]],
    'chat_message_list' => [200, ['choices' => [['message' => ['answer']]]]],
    'chat_message_numeric_key' => [200, ['choices' => [['message' => ['content' => 'answer', 7 => 'extra']]]]],
    'chat_error_detail' => [400, ['detail' => 'Model not found']],
    'chat_error_object' => [429, ['error' => ['message' => 'Rate limited', 'type' => 'rate_limit_error']]],
    'chat_error_string' => [400, ['error' => 'Bad request']],
    'chat_error_list' => [422, ['detail' => [['msg' => 'field required']]]],
    'chat_error_unknown' => [500, ['unexpected' => true]],
    'chat_error_text' => [502, null],
    'invalid_json' => [200, null],
    'academy_test_ok' => [200, [
        'title' => 'Remote quiz',
        'questions' => [
            [
                'type' => 'true_false',
                'text' => 'Did this quiz come from a remote server?',
                'answer' => true,
                'explanation' => 'Yes, it was served by the HTTP fixture of the tests.',
            ],
        ],
    ]],
    default => [404, ['detail' => 'Unknown scenario']],
};

http_response_code($status);

if ($scenario === 'results_bad_status_text') {
    echo 'Bad gateway';
    exit;
}

if ($scenario === 'chat_error_text') {
    echo 'Bad gateway';
    exit;
}

if ($scenario === 'invalid_json') {
    echo '{this is not valid json';
    exit;
}

echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
