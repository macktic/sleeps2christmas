<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script must be run from the command line.\n");
}

$env = @parse_ini_file(__DIR__ . '/.env', false, INI_SCANNER_RAW);
$appKey = is_array($env) ? trim($env['YVP_APP_KEY'] ?? '') : '';

if ($appKey === '') {
    fwrite(STDERR, "YouVersion API key missing\n");
    exit(1);
}

$url = 'https://api.youversion.com/v1/bibles/1531/passages/JHN.3.16';

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'X-YVP-App-Key: ' . $appKey,
    ],
]);

$body = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

if ($body === false) {
    fwrite(STDERR, "Request failed: $error\n");
    exit(1);
}

if ($status < 200 || $status >= 300) {
    fwrite(STDERR, "YouVersion returned HTTP $status: $body\n");
    exit(1);
}

$payload = json_decode($body, true);

if (!is_array($payload) || empty($payload['content'])) {
    fwrite(STDERR, "Unexpected YouVersion response\n");
    exit(1);
}

echo ($payload['reference'] ?? 'John 3:16')
    . " (NIVUK)\n"
    . trim($payload['content'])
    . "\n";
