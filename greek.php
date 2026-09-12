<?php
declare(strict_types=1);

// Local ancient-Greek Bible backend. Expects $passageId or ?passage=JHN.3.16.
header('Content-Type: text/plain; charset=UTF-8');

$key = strtolower($_GET['key'] ?? 'lxxsbl');
if (!in_array($key, ['lxx', 'sblgnt', 'lxxsbl'], true)) {
    http_response_code(400);
    exit('Unsupported Greek text');
}

if (!isset($passageId) || trim((string)$passageId) === '') {
    $passageId = trim($_GET['passage'] ?? '');
}
if (!preg_match('/^([1-4]?[A-Z]{2,3})\.(\d+)(?:\.(\d+)(?:-(\d+))?)?$/', $passageId, $parts)) {
    http_response_code(400);
    exit('Invalid passage');
}

$inputBook = $parts[1];
$chapter = (int)$parts[2];
$firstVerse = isset($parts[3]) ? (int)$parts[3] : null;
$lastVerse = isset($parts[4]) ? (int)$parts[4] : $firstVerse;
if ($chapter < 1 || ($firstVerse !== null && ($firstVerse < 1 || $lastVerse < $firstVerse))) {
    http_response_code(400);
    exit('Invalid passage');
}

$bookNames = [
    'GEN'=>'Genesis','EXO'=>'Exodus','LEV'=>'Leviticus','NUM'=>'Numbers','DEU'=>'Deuteronomy',
    'JOS'=>'Joshua','JDG'=>'Judges','RUT'=>'Ruth','1SA'=>'1 Samuel','2SA'=>'2 Samuel',
    '1KI'=>'1 Kings','2KI'=>'2 Kings','1CH'=>'1 Chronicles','2CH'=>'2 Chronicles',
    'EZR'=>'Ezra','NEH'=>'Nehemiah','EST'=>'Esther','JOB'=>'Job','PSA'=>'Psalms',
    'PRO'=>'Proverbs','ECC'=>'Ecclesiastes','SNG'=>'Song of Solomon','ISA'=>'Isaiah',
    'JER'=>'Jeremiah','LAM'=>'Lamentations','EZK'=>'Ezekiel','DAN'=>'Daniel','HOS'=>'Hosea',
    'JOL'=>'Joel','AMO'=>'Amos','OBA'=>'Obadiah','JON'=>'Jonah','MIC'=>'Micah','NAM'=>'Nahum',
    'HAB'=>'Habakkuk','ZEP'=>'Zephaniah','HAG'=>'Haggai','ZEC'=>'Zechariah','MAL'=>'Malachi',
    'MAT'=>'Matthew','MRK'=>'Mark','LUK'=>'Luke','JHN'=>'John','ACT'=>'Acts','ROM'=>'Romans',
    '1CO'=>'1 Corinthians','2CO'=>'2 Corinthians','GAL'=>'Galatians','EPH'=>'Ephesians',
    'PHP'=>'Philippians','COL'=>'Colossians','1TH'=>'1 Thessalonians','2TH'=>'2 Thessalonians',
    '1TI'=>'1 Timothy','2TI'=>'2 Timothy','TIT'=>'Titus','PHM'=>'Philemon','HEB'=>'Hebrews',
    'JAS'=>'James','1PE'=>'1 Peter','2PE'=>'2 Peter','1JN'=>'1 John','2JN'=>'2 John',
    '3JN'=>'3 John','JUD'=>'Jude','REV'=>'Revelation',
];
$lxxCodeAliases = ['EST'=>'ESG','SNG'=>'SOL','EZK'=>'EZE','JOL'=>'JOE','NAM'=>'NAH'];
$lxxNativeNames = [
    '1ES'=>'1 Esdras','ESG'=>'Esther (Greek)','DNG'=>'Daniel (Greek)','1MA'=>'1 Maccabees',
    '2MA'=>'2 Maccabees','3MA'=>'3 Maccabees','4ES'=>'4 Esdras','BAR'=>'Baruch',
    'BEL'=>'Bel and the Dragon','EPJ'=>'Letter of Jeremiah','JDT'=>'Judith',
    'PRA'=>'Prayer of Azariah','SIR'=>'Sirach','SOL'=>'Song of Solomon','SUS'=>'Susanna',
    'TOB'=>'Tobit','WIS'=>'Wisdom',
];
$newTestament = array_flip([
    'MAT','MRK','LUK','JHN','ACT','ROM','1CO','2CO','GAL','EPH','PHP','COL','1TH','2TH',
    '1TI','2TI','TIT','PHM','HEB','JAS','1PE','2PE','1JN','2JN','3JN','JUD','REV',
]);

$database = __DIR__ . '/greek_bible.sqlite';
if (!is_file($database)) {
    http_response_code(500);
    exit('Greek Bible database missing');
}
try {
    $db = new PDO('sqlite:' . $database, null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
} catch (Throwable $error) {
    http_response_code(500);
    exit('Could not open Greek Bible database');
}
$findVerse = $db->prepare(
    'SELECT text FROM verses WHERE corpus=? AND book=? AND chapter=? AND verse=?'
);
$findChapter = $db->prepare(
    'SELECT text FROM verses WHERE corpus=? AND book=? AND chapter=? ORDER BY sequence'
);
$findMapping = $db->prepare(
    'SELECT target_chapter, target_verse FROM english_to_lxx
     WHERE book=? AND source_chapter=? AND source_verse=?'
);

function mapped_lxx_reference(
    PDOStatement $findMapping,
    string $bookName,
    int $chapter,
    int $verse
): ?array {
    $findMapping->execute([$bookName, $chapter, $verse]);
    $mapped = $findMapping->fetch(PDO::FETCH_ASSOC);
    if ($mapped === false) {
        return [$chapter, (string)$verse];
    }
    if ($mapped['target_chapter'] === null || $mapped['target_verse'] === null) {
        return null;
    }
    return [(int)$mapped['target_chapter'], (string)$mapped['target_verse']];
}

$corpus = $key;
$book = $inputBook;
$label = strtoupper($key);
$texts = [];

if ($key === 'lxxsbl') {
    if (isset($newTestament[$inputBook])) {
        $corpus = 'sblgnt';
        $label = 'SBLGNT';
    } else {
        if (!isset($bookNames[$inputBook])) {
            http_response_code(404);
            exit('Book unavailable in LXXSBL');
        }
        $corpus = 'lxx';
        $book = $lxxCodeAliases[$inputBook] ?? $inputBook;
        $label = 'LXX';

        // Ezra is not present as canonical Ezra in this particular LXX corpus.
        if ($inputBook === 'EZR') {
            http_response_code(404);
            exit('Ezra unavailable in this LXX corpus');
        }
    }
} elseif ($key === 'sblgnt' && !isset($newTestament[$inputBook])) {
    http_response_code(404);
    exit('Book unavailable in SBLGNT');
} elseif ($key === 'lxx') {
    $book = $lxxCodeAliases[$inputBook] ?? $inputBook;
}

if ($key === 'lxxsbl' && $corpus === 'lxx') {
    $bookName = $bookNames[$inputBook];
    $start = $firstVerse ?? 1;
    $finish = $lastVerse ?? 200;
    $misses = 0;
    $seenTargets = [];
    for ($verse = $start; $verse <= $finish; $verse++) {
        $target = mapped_lxx_reference($findMapping, $bookName, $chapter, $verse);
        if ($target === null) {
            continue;
        }
        [$targetChapter, $targetVerse] = $target;
        $targetKey = $targetChapter . ':' . $targetVerse;
        if (isset($seenTargets[$targetKey])) {
            continue;
        }
        $findVerse->execute(['lxx', $book, $targetChapter, $targetVerse]);
        $text = $findVerse->fetchColumn();
        if ($text === false) {
            $misses++;
            if ($firstVerse === null && !empty($texts) && $misses >= 12) {
                break;
            }
            continue;
        }
        $misses = 0;
        $seenTargets[$targetKey] = true;
        $texts[] = $text;
    }
} elseif ($firstVerse === null) {
    $findChapter->execute([$corpus, $book, $chapter]);
    $texts = $findChapter->fetchAll(PDO::FETCH_COLUMN);
} else {
    for ($verse = $firstVerse; $verse <= $lastVerse; $verse++) {
        $findVerse->execute([$corpus, $book, $chapter, (string)$verse]);
        $text = $findVerse->fetchColumn();
        if ($text !== false) {
            $texts[] = $text;
        }
    }
}

if (empty($texts)) {
    http_response_code(404);
    exit('Passage not found');
}

if (isset($ref) && trim((string)$ref) !== '') {
    $reference = trim((string)$ref);
} else {
    $referenceName = $bookNames[$inputBook] ?? $lxxNativeNames[$book] ?? $book;
    $reference = $referenceName . ' ' . $chapter;
    if ($firstVerse !== null) {
        $reference .= ':' . $firstVerse;
        if ($lastVerse !== $firstVerse) {
            $reference .= '-' . $lastVerse;
        }
    }
}

echo $reference . ' — ' . implode(' ', $texts) . ' (' . $label . ') — ' . $reference;
