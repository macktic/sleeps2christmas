<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = $argv[2] ?? __DIR__;
$output = $argv[1] ?? ($root . '/greek_bible.sqlite');
$lxxFile = $root . '/LXX/grclxx_vpl.txt';
$sblDirectory = $root . '/SBLGNT/data/sblgnt/text';
$mappingUrl = 'https://raw.githubusercontent.com/metaxiamultimedia/scriptures-js-source-stepbible-versification/2b20d86677a7ea318480ca7ddb425d2447b80b1b/data/stepbible-versification/mapping.json';

foreach ([$lxxFile, $sblDirectory] as $required) {
    if (!file_exists($required)) {
        fwrite(STDERR, "Missing source: $required\n");
        exit(1);
    }
}

$temporary = $output . '.tmp';
if (is_file($temporary) && !unlink($temporary)) {
    throw new RuntimeException('Could not replace temporary database');
}

$db = new PDO('sqlite:' . $temporary, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('PRAGMA journal_mode = DELETE');
$db->exec('PRAGMA synchronous = FULL');
$db->exec('CREATE TABLE verses (
    corpus TEXT NOT NULL, book TEXT NOT NULL, chapter INTEGER NOT NULL,
    verse TEXT NOT NULL, sequence INTEGER NOT NULL, text TEXT NOT NULL,
    PRIMARY KEY (corpus, book, chapter, verse)
)');
$db->exec('CREATE INDEX verse_lookup ON verses (corpus, book, chapter, sequence)');
$db->exec('CREATE TABLE english_to_lxx (
    book TEXT NOT NULL, source_chapter INTEGER NOT NULL, source_verse INTEGER NOT NULL,
    target_chapter INTEGER, target_verse TEXT, target_part TEXT,
    PRIMARY KEY (book, source_chapter, source_verse)
)');
$db->exec('CREATE TABLE metadata (name TEXT PRIMARY KEY, value TEXT NOT NULL)');

$insertVerse = $db->prepare('INSERT INTO verses VALUES (?, ?, ?, ?, ?, ?)');
$sequence = [];
$lxxCount = 0;
$db->beginTransaction();
$handle = fopen($lxxFile, 'rb');
if ($handle === false) {
    throw new RuntimeException('Could not open LXX source');
}
while (($line = fgets($handle)) !== false) {
    $line = trim($line);
    if (!preg_match('/^([1-4A-Z]{3}) (\d+):(\d+\p{L}*) (.+)$/u', $line, $match)) {
        throw new RuntimeException('Invalid LXX line: ' . $line);
    }
    $counterKey = $match[1] . ':' . $match[2];
    $sequence[$counterKey] = ($sequence[$counterKey] ?? 0) + 1;
    $insertVerse->execute(['lxx', $match[1], (int)$match[2], $match[3], $sequence[$counterKey], $match[4]]);
    $lxxCount++;
}
fclose($handle);

$sblBooks = [
    'Matt'=>'MAT', 'Mark'=>'MRK', 'Luke'=>'LUK', 'John'=>'JHN', 'Acts'=>'ACT',
    'Rom'=>'ROM', '1Cor'=>'1CO', '2Cor'=>'2CO', 'Gal'=>'GAL', 'Eph'=>'EPH',
    'Phil'=>'PHP', 'Col'=>'COL', '1Thess'=>'1TH', '2Thess'=>'2TH',
    '1Tim'=>'1TI', '2Tim'=>'2TI', 'Titus'=>'TIT', 'Phlm'=>'PHM', 'Heb'=>'HEB',
    'Jas'=>'JAS', '1Pet'=>'1PE', '2Pet'=>'2PE', '1John'=>'1JN', '2John'=>'2JN',
    '3John'=>'3JN', 'Jude'=>'JUD', 'Rev'=>'REV',
];
$sblCount = 0;
foreach ($sblBooks as $filename => $book) {
    $handle = fopen($sblDirectory . '/' . $filename . '.txt', 'rb');
    if ($handle === false) {
        throw new RuntimeException("Could not open SBLGNT book $filename");
    }
    while (($line = fgets($handle)) !== false) {
        $line = trim($line);
        if ($line === '' || !str_contains($line, "\t")) {
            continue;
        }
        [$reference, $text] = explode("\t", $line, 2);
        if (!preg_match('/^[^ ]+ (\d+):(\d+)$/', $reference, $match)) {
            throw new RuntimeException('Invalid SBLGNT line: ' . $line);
        }
        $insertVerse->execute(['sblgnt', $book, (int)$match[1], $match[2], (int)$match[2], trim($text)]);
        $sblCount++;
    }
    fclose($handle);
}

$mappingJson = file_get_contents($mappingUrl, false, stream_context_create(['http'=>['timeout'=>30]]));
if ($mappingJson === false) {
    throw new RuntimeException('Could not download STEP Bible versification mapping');
}
$decoded = json_decode($mappingJson, true, 512, JSON_THROW_ON_ERROR);
$mapping = $decoded['English->LXX'] ?? null;
if (!is_array($mapping)) {
    throw new RuntimeException('English-to-LXX mapping missing');
}
$insertMapping = $db->prepare('INSERT INTO english_to_lxx VALUES (?, ?, ?, ?, ?, ?)');
foreach ($mapping as $reference => $target) {
    if (!preg_match('/^(.+) (\d+):(\d+)$/', $reference, $match)) {
        throw new RuntimeException('Invalid mapping reference: ' . $reference);
    }
    $insertMapping->execute([
        $match[1], (int)$match[2], (int)$match[3],
        $target['chapter'] ?? null,
        isset($target['verse']) ? (string)$target['verse'] : null,
        $target['part'] ?? null,
    ]);
}

$insertMetadata = $db->prepare('INSERT INTO metadata VALUES (?, ?)');
foreach ([
    'lxx_verses'=>(string)$lxxCount,
    'sblgnt_verses'=>(string)$sblCount,
    'english_to_lxx_exceptions'=>(string)count($mapping),
    'versification_source'=>'STEP Bible TVTMS / Tyndale House, Cambridge',
    'versification_license'=>'CC BY 4.0 https://creativecommons.org/licenses/by/4.0/',
    'versification_changes'=>'English-to-LXX subset imported into SQLite; identity mappings omitted upstream.',
] as $name => $value) {
    $insertMetadata->execute([$name, $value]);
}
$db->commit();

if ($lxxCount !== 27827 || $sblCount !== 7939 || count($mapping) !== 4433) {
    throw new RuntimeException("Unexpected counts: LXX=$lxxCount SBLGNT=$sblCount mappings=" . count($mapping));
}
$check = $db->prepare('SELECT text FROM verses WHERE corpus=? AND book=? AND chapter=? AND verse=?');
foreach ([['lxx','GEN',1,'1'], ['lxx','PSA',50,'1'], ['sblgnt','JHN',3,'16']] as $sample) {
    $check->execute($sample);
    if ($check->fetchColumn() === false) {
        throw new RuntimeException('Validation sample missing: ' . implode(' ', $sample));
    }
}
$db = null;

if (is_file($output) && !unlink($output)) {
    throw new RuntimeException('Could not replace existing database');
}
if (!rename($temporary, $output)) {
    throw new RuntimeException('Could not promote temporary database');
}
echo "Created $output\nLXX: $lxxCount verses\nSBLGNT: $sblCount verses\nMappings: " . count($mapping) . "\n";
