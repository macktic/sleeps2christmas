<?php
// esv.php — expects $ref set by votd_ref.php, or ?ref=...
$format = strtolower($_GET['format'] ?? 'text');

if (!isset($ref) || !$ref) {
    $ref = urldecode($_GET['ref'] ?? '');
}
if ($ref === '') {
    http_response_code(400);
    exit("Missing ref");
}

require_once __DIR__ . '/bible_reference.php';

if (!is_valid_bible_reference($ref)) {
    http_response_code(400);
    exit(invalid_reference_message('en'));
}

if (bible_reference_category($ref) === 'apocrypha') {
    http_response_code(404);
    exit(category_unavailable_message('apocrypha', 'en'));
}

$env = @parse_ini_file(__DIR__ . '/.env', false, INI_SCANNER_RAW);
$apiKey = is_array($env) ? trim($env['ESV_API_KEY'] ?? '') : '';
$apiKey = preg_replace('/^Token\s+/i', '', $apiKey);

if ($apiKey === '') {
    http_response_code(500);
    exit('ESV API key missing');
}

switch ($format) {
    case 'audio':
        $url = 'https://api.esv.org/v3/passage/audio/?q=' . urlencode($ref);
        // Directly fetch and output the mp3 file
        $opts = [
            "http" => [
                "method"  => "GET",
                "header"  => "Authorization: Token $apiKey\r\nAccept: audio/mpeg\r\n",
                "timeout" => 10,
            ]
        ];
        $ctx  = stream_context_create($opts);
        $mp3data = @file_get_contents($url, false, $ctx);
        if ($mp3data === false) {
            if (response_http_status($http_response_header ?? []) === 404) {
                http_response_code(502);
                exit(reference_retrieval_message('en'));
            }
            http_response_code(502);
            exit("ESV audio fetch failed");
        }
        header('Content-Type: audio/mpeg');
        echo $mp3data;
        exit;
    case 'html':
        $url = 'https://api.esv.org/v3/passage/html/?q=' . urlencode($ref)
             . '&include-footnotes=false'
             . '&include-headings=true'
             . '&include-verse-numbers=true'
             . '&include-passage-references=false';
        break;
    default:
        $url = 'https://api.esv.org/v3/passage/text/?q=' . urlencode($ref)
             . '&include-footnotes=false'
             . '&include-headings=false'
             . '&include-verse-numbers=false'
             . '&include-passage-references=false';
        break;
}

$opts = [
    "http" => [
        "method"  => "GET",
        "header"  => "Authorization: Token $apiKey\r\nAccept: application/json\r\n",
        "timeout" => 10,
    ]
];
$ctx  = stream_context_create($opts);
$json = @file_get_contents($url, false, $ctx);
if ($json === false) {
    if (response_http_status($http_response_header ?? []) === 404) {
        http_response_code(502);
        exit(reference_retrieval_message('en'));
    }
    http_response_code(502);
    exit("ESV fetch failed");
}

$data = json_decode($json, true);

if ($format === 'html') {
    header('Content-Type: text/html; charset=UTF-8');
    $html = trim($data['passages'][0] ?? '');
    if ($html === '') {
        http_response_code(502);
        exit(reference_retrieval_message('en'));
    }
    echo $html;
    exit;
} else {
    header('Content-Type: text/plain; charset=UTF-8');
    $text = trim($data['passages'][0] ?? '');
    if ($text === '') {
        http_response_code(502);
        exit(reference_retrieval_message('en'));
    }
    // Final format: Reference — Verse text, (ESV) — Reference
    echo $ref . " — " . $text . " — " . $ref;
    exit;
}
