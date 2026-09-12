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

$versions = [];
$pageToken = '';
$languageRanges = ['en', 'nl', 'gla', 'grc', 'hbo', 'he'];
$languageIndex = 0;

do {
    $languageRange = $languageRanges[$languageIndex];

    $url = 'https://api.youversion.com/v1/bibles'
         . '?language_ranges%5B%5D=' . rawurlencode($languageRange)
         . '&page_size=99';

    if ($pageToken !== '') {
        $url .= '&page_token=' . urlencode($pageToken);
    }

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
    curl_close($ch);

    if ($body === false) {
        fwrite(STDERR, "YouVersion request failed: $error\n");
        exit(1);
    }

    if ($status === 204) {
        break;
    }

    if ($status < 200 || $status >= 300) {
        $errorPayload = json_decode($body, true);
        $detail = $errorPayload['message']
            ?? $errorPayload['fault']['faultstring']
            ?? $errorPayload['detail'][0]['msg']
            ?? 'Unknown error';
        fwrite(STDERR, "YouVersion returned HTTP $status: $detail\n");
        exit(1);
    }

    $payload = json_decode($body, true);

    if (!is_array($payload) || !isset($payload['data']) || !is_array($payload['data'])) {
        fwrite(STDERR, "Unexpected YouVersion response\n");
        exit(1);
    }

    foreach ($payload['data'] as $version) {
        if (!is_array($version)) {
            continue;
        }

        $versions[] = [
            'id' => $version['id'] ?? '',
            'abbreviation' => $version['localized_abbreviation']
                ?? $version['abbreviation']
                ?? '',
            'language' => $version['language_tag']
                ?? $version['language']['iso_639_1']
                ?? '',
            'title' => $version['localized_title']
                ?? $version['title']
                ?? '',
        ];
    }

    $pageToken = trim($payload['next_page_token'] ?? '');

    if ($pageToken === '') {
        $languageIndex++;
    }
} while ($pageToken !== '' || isset($languageRanges[$languageIndex]));

usort($versions, static function (array $a, array $b): int {
    return [
        strtolower((string) $a['language']),
        strtolower((string) $a['title']),
    ] <=> [
        strtolower((string) $b['language']),
        strtolower((string) $b['title']),
    ];
});

$outputPath = __DIR__ . '/youversion_versions.csv';
$tempPath = tempnam(__DIR__, 'youversion_versions_');

if ($tempPath === false) {
    fwrite(STDERR, "Could not create temporary output file\n");
    exit(1);
}

$file = fopen($tempPath, 'wb');

if ($file === false) {
    @unlink($tempPath);
    fwrite(STDERR, "Could not open temporary output file\n");
    exit(1);
}

fputcsv($file, ['id', 'abbreviation', 'language', 'title']);

foreach ($versions as $version) {
    fputcsv($file, $version);
}

fclose($file);

if (!rename($tempPath, $outputPath)) {
    @unlink($tempPath);
    fwrite(STDERR, "Could not replace output file\n");
    exit(1);
}

echo 'Wrote ' . count($versions) . " versions to $outputPath\n";
