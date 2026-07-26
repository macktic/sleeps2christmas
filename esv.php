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

$apiKey = 'Token 96b063e1cba6a76ab050b4f578760f30c89bd052'; // from https://api.esv.org

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
    http_response_code(502);
    exit("ESV fetch failed");
}

$data = json_decode($json, true);

if ($format === 'html') {
    header('Content-Type: text/html; charset=UTF-8');
    $html = trim($data['passages'][0] ?? '');
    if ($html === '') {
        http_response_code(502);
        exit("ESV HTML response empty");
    }
    echo $html;
    exit;
} else {
    header('Content-Type: text/plain; charset=UTF-8');
    $text = trim($data['passages'][0] ?? '');
    if ($text === '') {
        http_response_code(502);
        exit("ESV response empty");
    }
    // Final format: Reference — Verse text, (ESV) — Reference
    echo $ref . " — " . $text . " — " . $ref;
    exit;
}
