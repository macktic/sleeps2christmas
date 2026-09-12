<?php
/**
 * votd_ref.php
 * - No key: prints the YouVersion VOTD reference (e.g., "Philippians 2:5")
 * - key=esv  : includes ./esv.php (with $ref set) to print the full verse line or mp3
 * - key=esvuk: includes ./esvuk.php (when you add it)
 */

header('Content-Type: text/plain; charset=UTF-8');

// Whitelist: map allowed keys to specific local files (same dir)
$ALLOWED = [
  'esv'   => 'esv.php',
//  'esvuk' => 'esvuk.php',
  'svv'   => 'svv.php',     // Dutch (Statenvertaling)
  // add more later, e.g. 'web' => 'web.php'
];

// Which keys support audio?
$AUDIO_COMPATIBLE = ['esv'];

// --- tiny fetch helper
function fetch_html(string $url): ?string {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_USERAGENT => 'Mozilla/5.0',
    CURLOPT_HTTPHEADER => [
      'Accept: text/html,application/xhtml+xml;q=0.9,*/*;q=0.8',
      'Accept-Language: en-GB,en;q=0.8',
      'Accept-Encoding: identity',
    ],
  ]);
  $body = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  return ($code >= 200 && $code < 400 && $body) ? $body : null;
}

// --- 1) Get YouVersion page
$html = fetch_html('https://www.bible.com/verse-of-the-day');
if (!$html) { http_response_code(502); exit("VOTD fetch failed"); }

// --- 2) Extract reference (robust: use meta first, then body fallback)
$reference = '';

// Current YouVersion title:
// "Verse of the Day - Revelation 4:11 - Bible App"
if (preg_match(
  '/<title>\s*Verse of the Day\s*-\s*([1-3]?\s?[A-Za-z][A-Za-z ]+\s+\d+:\d+(?:[-–]\d+)?)\s*-\s*Bible App\s*<\/title>/i',
  $html,
  $m
)) {
  $reference = trim($m[1]);
}

// Try og/twitter description meta
if ($reference === '' && preg_match('/<meta\s+(?:property|name)=["\'](?:og|twitter):description["\']\s+content=["\']([^"\']+)["\']/i', $html, $m)) {
  $line = html_entity_decode($m[1], ENT_QUOTES|ENT_HTML5, 'UTF-8');
  if (preg_match('/^([1-3]?\s?[A-Za-z][A-Za-z ]+\s+\d+:\d+(?:[-–]\d+)?)/u', $line, $r)) {
    $reference = trim($r[1]);
  }
}

if ($reference === '') {
  // Fallback: slice body text between "Verse of the Day" and the next section
  $body = preg_replace("/\r\n?/", "\n", strip_tags($html));
  if (preg_match('/Verse of the Day(.*?)(?:This Weeks Bible Verses|Download The Bible App)/is', $body, $b)) {
    $blk = trim($b[1]);
    // Drop date line if present
    $blk = preg_replace('/^[A-Z][a-z]+ \d{1,2}, \d{4}\s*\n/u', '', $blk);
    $lines = array_values(array_filter(array_map('trim', explode("\n", $blk))));
    foreach ($lines as $L) {
      if (preg_match('/^[1-3]?\s?[A-Za-z][A-Za-z ]+\s+\d+:\d+(?:[-–]\d+)?(?:\s*\([A-Z]{2,}\))?$/u', $L)) {
        $reference = trim(preg_replace('/\s*\([A-Z]{2,}\)\s*$/', '', $L)); // strip "(ESV)"
        break;
      }
    }
  }
}

if ($reference === '') { http_response_code(500); exit("Could not parse VOTD reference"); }

// --- 3) If no/unknown key: just output the reference
$key = isset($_GET['key']) ? strtolower($_GET['key']) : '';
if ($key === '' || !array_key_exists($key, $ALLOWED)) {
  echo $reference;
  exit;
}

// --- 4) Check format
$format = strtolower($_GET['format'] ?? 'text');
if ($format === 'audio' && !in_array($key, $AUDIO_COMPATIBLE)) {
    // Silently drop audio for incompatible versions, fallback to text
    $format = 'text';
}

// --- 5) Safe include: pass $ref and $format to the mapped local script
$includeFile = $ALLOWED[$key];
$fullPath    = __DIR__ . DIRECTORY_SEPARATOR . $includeFile;

if (!is_file($fullPath)) { http_response_code(501); exit("Backend '$includeFile' missing"); }

// Provide $ref and $format to the included file:
$ref = $reference;
$_GET['format'] = $format; // Pass format to included script
include $fullPath;
// included file should echo the final line (and not exit with errors)
