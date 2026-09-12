<?php
// svv.php — Dutch Statenvertaling (SVV) via Bible SuperSearch
// Expects $ref from includer or ?ref=... (either Dutch or English book name)
// Outputs: "<ref> — <text> — <ref>"

header('Content-Type: text/plain; charset=UTF-8');

if (!isset($ref) || !$ref) {
    $ref = urldecode($_GET['ref'] ?? '');
}
$debug = isset($_GET['debug']);

if ($ref === '') {
    http_response_code(400);
    exit('Missing ref');
}

require_once __DIR__ . '/bible_reference.php';

if (!is_valid_bible_reference($ref)) {
    http_response_code(400);
    exit(invalid_reference_message('nl'));
}

if (bible_reference_category($ref) === 'apocrypha') {
    http_response_code(404);
    exit(category_unavailable_message('apocrypha', 'nl'));
}

function fetch_json(string $ref): ?array {
    $api = 'https://api.biblesupersearch.com/api';
    $qs  = http_build_query([
        'bible'           => 'stve',          // Dutch Statenvertaling
        'reference'       => $ref,
        'text_only'       => 1,               // prefer plain text
        'verse_numbers'   => 0,
        'formatting'      => 'plain',
        'data_format'     => 'passage',       // grouped output
        'output'          => 'json',          // force JSON
    ]);
    $json = @file_get_contents($api.'?'.$qs);
    if ($json === false) return null;
    $payload = json_decode($json, true);
    return is_array($payload) ? $payload : null;
}

// 1) Try as-is (probably English book from YouVersion, e.g., "Philippians 2:5")
$payload = fetch_json($ref);

// 2) If not OK, try with Dutch book names
$map = [
    'Genesis'=>'Genesis','Exodus'=>'Exodus','Leviticus'=>'Leviticus','Numbers'=>'Numeri','Deuteronomy'=>'Deuteronomium',
    'Joshua'=>'Jozua','Judges'=>'Richteren','Ruth'=>'Ruth','1 Samuel'=>'1 Samuël','2 Samuel'=>'2 Samuël',
    '1 Kings'=>'1 Koningen','2 Kings'=>'2 Koningen','1 Chronicles'=>'1 Kronieken','2 Chronicles'=>'2 Kronieken',
    'Ezra'=>'Ezra','Nehemiah'=>'Nehemia','Esther'=>'Ester','Job'=>'Job','Psalms'=>'Psalmen','Psalm'=>'Psalm',
    'Proverbs'=>'Spreuken','Ecclesiastes'=>'Prediker','Song of Solomon'=>'Hooglied','Isaiah'=>'Jesaja',
    'Jeremiah'=>'Jeremia','Lamentations'=>'Klaagliederen','Ezekiel'=>'Ezechiël','Daniel'=>'Daniël',
    'Hosea'=>'Hosea','Joel'=>'Joël','Amos'=>'Amos','Obadiah'=>'Obadja','Jonah'=>'Jona','Micah'=>'Micha',
    'Nahum'=>'Nahum','Habakkuk'=>'Habakuk','Zephaniah'=>'Zefanja','Haggai'=>'Haggai','Zechariah'=>'Zacharia',
    'Malachi'=>'Maleachi',
    'Matthew'=>'Mattheüs','Mark'=>'Markus','Luke'=>'Lukas','John'=>'Johannes','Acts'=>'Handelingen',
    'Romans'=>'Romeinen','1 Corinthians'=>'1 Korinthe','2 Corinthians'=>'2 Korinthe','Galatians'=>'Galaten',
    'Ephesians'=>'Efeze','Philippians'=>'Filippenzen','Colossians'=>'Kolossenzen','1 Thessalonians'=>'1 Thessalonicenzen',
    '2 Thessalonians'=>'2 Thessalonicenzen','1 Timothy'=>'1 Timotheüs','2 Timothy'=>'2 Timotheüs',
    'Titus'=>'Titus','Philemon'=>'Filemon','Hebrews'=>'Hebreeën','James'=>'Jakobus','1 Peter'=>'1 Petrus',
    '2 Peter'=>'2 Petrus','1 John'=>'1 Johannes','2 John'=>'2 Johannes','3 John'=>'3 Johannes','Jude'=>'Judas',
    'Revelation'=>'Openbaring'
];
if (!$payload || !empty($payload['errors'])) {
    $ref_nl = $ref;
    foreach ($map as $en => $nl) {
        // Whole word replace, keep numerals (1/2/3) if present
        $ref_nl = preg_replace('/\b'.$en.'\b/u', $nl, $ref_nl);
    }
    if ($ref_nl !== $ref) {
        $payload = fetch_json($ref_nl);
        if ($payload && empty($payload['errors'])) {
            $ref = $ref_nl; // keep Dutch ref on success
        }
    }
}

// 3) Extract text from any of the common shapes
$text = '';
if ($payload) {
    // Shape A: results.text
    if (!$text && isset($payload['results']['text']) && is_string($payload['results']['text'])) {
        $text = trim($payload['results']['text']);
    }

    // Shape B: results.passages[0].text
    if (!$text && isset($payload['results']['passages'][0]['text'])) {
        $text = trim($payload['results']['passages'][0]['text']);
    }

    // Shape C: results.passages[0].verses[].text
    if (
        !$text &&
        isset($payload['results']['passages'][0]['verses']) &&
        is_array($payload['results']['passages'][0]['verses'])
    ) {
        $buff = [];
        foreach ($payload['results']['passages'][0]['verses'] as $v) {
            if (!empty($v['text'])) $buff[] = trim($v['text']);
        }
        $text = trim(implode(' ', $buff));
    }

    // Shape D (your sample): results[0].verses.stve[chapter][verse].text
    if (
        !$text &&
        isset($payload['results'][0]['verses']['stve']) &&
        is_array($payload['results'][0]['verses']['stve'])
    ) {
        $buff = [];
        foreach ($payload['results'][0]['verses']['stve'] as $chapterArr) {
            if (!is_array($chapterArr)) continue;
            foreach ($chapterArr as $verseArr) {
                if (!is_array($verseArr)) continue;
                if (!empty($verseArr['text'])) $buff[] = trim($verseArr['text']);
            }
        }
        $text = trim(implode(' ', $buff));
    }
}

if ($debug) {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ref'=>$ref,'payload'=>$payload,'text'=>$text], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($text === '') {
    http_response_code(502);
    exit(reference_retrieval_message('nl'));
}

// Build a localized reference (prefer Dutch book name from payload)
$refOut = $ref; // default: whatever came in

if (isset($payload['results'][0]['book_name']) && isset($payload['results'][0]['chapter_verse'])) {
    // e.g., "Filippenzen" and "2:5"
    $book = trim($payload['results'][0]['book_name']);
    $cv   = trim($payload['results'][0]['chapter_verse']);
    if ($book !== '' && $cv !== '') {
        $refOut = $book . ' ' . $cv;
    }
} elseif (isset($payload['results']['passages'][0]['reference'])) {
    // some responses include a localized full reference directly
    $refOut = trim($payload['results']['passages'][0]['reference']);
}

// Final output in your preferred format
echo $refOut . ' — ' . $text . ' — ' . $refOut;
