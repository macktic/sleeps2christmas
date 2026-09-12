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
  'nasb1995' => 'youversion.php',
  'niv'   => 'youversion.php',
  'nivuk' => 'youversion.php',
  'tpt'   => 'youversion.php',
  'htb'   => 'youversion.php',
  // add more later, e.g. 'web' => 'web.php'
];

// Which keys support audio?
$AUDIO_COMPATIBLE = ['esv'];

// --- Load the YouVersion App Key
$env = @parse_ini_file(__DIR__ . '/.env', false, INI_SCANNER_RAW);
$youVersionKey = is_array($env) ? trim($env['YVP_APP_KEY'] ?? '') : '';

if ($youVersionKey === '') {
  http_response_code(500);
  exit('YouVersion API key missing');
}

// --- Fetch today's passage ID from the official YouVersion API
function fetch_youversion_votd(int $day, string $appKey): ?array {
  $url = 'https://api.youversion.com/v1/verse_of_the_days/' . $day;
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
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($code < 200 || $code >= 300 || !is_string($body) || $body === '') {
    return null;
  }

  $payload = json_decode($body, true);
  return is_array($payload) ? $payload : null;
}

// Convert a YouVersion USFM passage ID such as MAT.11.28
// into the existing human-readable format: Matthew 11:28
function passage_id_to_reference(string $passageId): ?string {
  $bookCodes = explode(
    ' ',
    'GEN EXO LEV NUM DEU JOS JDG RUT 1SA 2SA 1KI 2KI 1CH 2CH EZR NEH EST JOB PSA PRO ECC SNG ISA JER LAM EZK DAN HOS JOL AMO OBA JON MIC NAM HAB ZEP HAG ZEC MAL MAT MRK LUK JHN ACT ROM 1CO 2CO GAL EPH PHP COL 1TH 2TH 1TI 2TI TIT PHM HEB JAS 1PE 2PE 1JN 2JN 3JN JUD REV'
  );

  $bookNames = explode(
    '|',
    'Genesis|Exodus|Leviticus|Numbers|Deuteronomy|Joshua|Judges|Ruth|1 Samuel|2 Samuel|1 Kings|2 Kings|1 Chronicles|2 Chronicles|Ezra|Nehemiah|Esther|Job|Psalms|Proverbs|Ecclesiastes|Song of Solomon|Isaiah|Jeremiah|Lamentations|Ezekiel|Daniel|Hosea|Joel|Amos|Obadiah|Jonah|Micah|Nahum|Habakkuk|Zephaniah|Haggai|Zechariah|Malachi|Matthew|Mark|Luke|John|Acts|Romans|1 Corinthians|2 Corinthians|Galatians|Ephesians|Philippians|Colossians|1 Thessalonians|2 Thessalonians|1 Timothy|2 Timothy|Titus|Philemon|Hebrews|James|1 Peter|2 Peter|1 John|2 John|3 John|Jude|Revelation'
  );

  $books = array_combine($bookCodes, $bookNames);

  if (
    !is_array($books) ||
    !preg_match('/^([1-3]?[A-Z]{2,3})\.(\d+)\.(\d+(?:-\d+)?)$/', $passageId, $parts) ||
    !isset($books[$parts[1]])
  ) {
    return null;
  }

  return $books[$parts[1]] . ' ' . $parts[2] . ':' . $parts[3];
}

$today = new DateTimeImmutable('now', new DateTimeZone('Europe/London'));
$dayOfYear = ((int) $today->format('z')) + 1;

$payload = fetch_youversion_votd($dayOfYear, $youVersionKey);

if ($payload === null) {
  http_response_code(502);
  exit('VOTD fetch failed');
}

$passageId = trim($payload['passage_id'] ?? '');
$reference = passage_id_to_reference($passageId);

if ($reference === null) {
  http_response_code(500);
  exit('Could not parse VOTD reference');
}

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
