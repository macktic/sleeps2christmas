<?php
/**
 * potd.php
 * - No key: prints the Proverb of the Day reference (e.g., "Proverbs 28")
 * - key=esv  : includes ./esv.php (with $ref set) to print the full chapter or mp3
 * - key=svv  : includes ./svv.php (with $ref set) for Dutch (no mp3)
 * - YouVersion keys: nasb1995, niv, nivuk, tpt, htb (text only)
 */

header('Content-Type: text/plain; charset=UTF-8');

// Whitelist: map allowed keys to specific local files (same dir)
$ALLOWED = [
  'esv'      => 'esv.php',
  'svv'      => 'svv.php',
  'nasb1995' => 'youversion.php',
  'niv'      => 'youversion.php',
  'nivuk'    => 'youversion.php',
  'tpt'      => 'youversion.php',
  'htb'      => 'youversion.php',
  // add more later
];

// Which keys support audio?
$AUDIO_COMPATIBLE = ['esv'];

// --- 1) Get today's Proverb chapter
$day = (int)date('j'); // 1-31
$reference = "Proverbs $day";
$passageId = "PRO.$day";

// --- 2) If no/unknown key: default to 'esv'
$key = isset($_GET['key']) ? strtolower($_GET['key']) : 'esv';
if (!array_key_exists($key, $ALLOWED)) {
  $key = 'esv';
}

// --- 3) Check format
$format = strtolower($_GET['format'] ?? 'text');
if ($format === 'audio' && !in_array($key, $AUDIO_COMPATIBLE)) {
    // Silently drop audio for incompatible versions, fallback to text
    $format = 'text';
}

// --- 4) Safe include: pass $ref and $format to the mapped local script
$includeFile = $ALLOWED[$key];
$fullPath    = __DIR__ . DIRECTORY_SEPARATOR . $includeFile;

if (!is_file($fullPath)) { http_response_code(501); exit("Backend '$includeFile' missing"); }

// Provide $ref and $format to the included file:
$ref = $reference;
$_GET['format'] = $format; // Pass format to included script
include $fullPath;
// included file should echo the final line (and not exit with errors)
