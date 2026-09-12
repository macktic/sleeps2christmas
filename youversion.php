<?php
// youversion.php — fetches Bible text from the YouVersion Platform API.
// Expects $passageId from votd_rev.php, or ?passage=JHN.3.16.

header('Content-Type: text/plain; charset=UTF-8');

$versions = [
    'nasb1995' => ['id' => 100,  'label' => 'NASB1995'],
    'niv'   => ['id' => 111,  'label' => 'NIV'],
    'nivuk' => ['id' => 113,  'label' => 'NIVUK'],
    'tpt'   => ['id' => 1849, 'label' => 'TPT'],
    'htb'   => ['id' => 75,   'label' => 'HTB'],
];

$key = strtolower($_GET['key'] ?? 'nasb1995');

if (!isset($versions[$key])) {
    http_response_code(400);
    exit('Unsupported YouVersion translation');
}

if (!isset($passageId) || $passageId === '') {
    $passageId = trim($_GET['passage'] ?? '');
}

if ($passageId === '') {
    http_response_code(400);
    exit('Missing passage');
}

$env = @parse_ini_file(__DIR__ . '/.env', false, INI_SCANNER_RAW);
$appKey = is_array($env) ? trim($env['YVP_APP_KEY'] ?? '') : '';

if ($appKey === '') {
    http_response_code(500);
    exit('YouVersion API key missing');
}

$version = $versions[$key];
$url = 'https://api.youversion.com/v1/bibles/'
     . $version['id']
     . '/passages/'
     . rawurlencode($passageId)
     . '?format=text';

$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'X-YVP-App-Key: ' . $appKey,
    ],
]);

$body = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($body === false || $status < 200 || $status >= 300) {
    http_response_code(502);
    exit('YouVersion passage fetch failed');
}

$payload = json_decode($body, true);
$text = trim($payload['content'] ?? '');
$reference = trim($payload['reference'] ?? '');

if ($text === '') {
    http_response_code(502);
    exit('YouVersion passage response empty');
}

if ($reference === '') {
    $reference = isset($ref) && $ref !== '' ? $ref : $passageId;
}

echo $reference
    . ' — '
    . $text
    . ' ('
    . $version['label']
    . ') — '
    . $reference;
