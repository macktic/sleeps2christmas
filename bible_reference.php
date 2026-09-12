<?php
declare(strict_types=1);

function is_apocrypha_reference(string $reference): bool
{
    $reference = trim(urldecode($reference));
    $code = strtoupper((string)preg_replace('/[.\s:].*$/u', '', $reference));

    $codes = [
        'TOB','JDT','ESG','WIS','SIR','BAR','LJE','EPJ','S3Y','PRA',
        'SUS','BEL','1MA','2MA','3MA','4MA','1ES','2ES','4ES',
        'MAN','PS2','ODA','PSS',
    ];

    if (in_array($code, $codes, true)) {
        return true;
    }

    return preg_match(
        '/^(?:'
        . '[1-4]\s*(?:Maccabees|Makkabeeën|Makkabeeen|Esdras)|'
        . 'Tobit|Tobias|Judith|Judit|'
        . 'Wisdom(?:\s+of\s+Solomon)?|Wijsheid(?:\s+van\s+Salomo)?|'
        . 'Ecclesiasticus|Sirach|Jezus\s+Sirach|Baruch|Baruk|'
        . 'Letter\s+of\s+Jeremiah|Epistle\s+of\s+Jeremiah|Brief\s+van\s+Jeremia|'
        . 'Prayer\s+of\s+Azariah|Gebed\s+van\s+Azarja|'
        . 'Prayer\s+of\s+Manasseh|Gebed\s+van\s+Manasse|'
        . 'Susanna|Bel(?:\s+and\s+the\s+Dragon|\s+en\s+de\s+draak)?|'
        . 'Psalm\s+151'
        . ')\b/iu',
        $reference
    ) === 1;
}

function apocrypha_unavailable_message(string $language): string
{
    return match ($language) {
        'nl' => 'Apocriefe boeken zijn niet beschikbaar in deze vertaling',
        'el' => 'Τα Απόκρυφα δεν είναι διαθέσιμα σε αυτή τη μετάφραση',
        default => 'Apocrypha not available in this translation',
    };
}

function reference_testament(string $reference): ?string
{
    $code = strtoupper((string)preg_replace(
        '/[.\s:].*$/u',
        '',
        trim(urldecode($reference))
    ));

    $oldTestament = [
        'GEN','EXO','LEV','NUM','DEU','JOS','JDG','RUT','1SA','2SA',
        '1KI','2KI','1CH','2CH','EZR','NEH','EST','JOB','PSA','PRO',
        'ECC','SNG','ISA','JER','LAM','EZK','DAN','HOS','JOL','AMO',
        'OBA','JON','MIC','NAM','HAB','ZEP','HAG','ZEC','MAL',
    ];

    $newTestament = [
        'MAT','MRK','LUK','JHN','ACT','ROM','1CO','2CO','GAL','EPH',
        'PHP','COL','1TH','2TH','1TI','2TI','TIT','PHM','HEB','JAS',
        '1PE','2PE','1JN','2JN','3JN','JUD','REV',
    ];

    if (in_array($code, $oldTestament, true)) {
        return 'ot';
    }

    if (in_array($code, $newTestament, true)) {
        return 'nt';
    }

    return null;
}

function bible_reference_category(string $reference): ?string
{
    if (is_apocrypha_reference($reference)) {
        return 'apocrypha';
    }

    $reference = trim(urldecode($reference));
    $testament = reference_testament($reference);
    if ($testament !== null) {
        return $testament;
    }

    $book = preg_replace('/\s+\d+(?::\d+(?:-\d+)?)?$/u', '', $reference);
    $oldTestamentNames = [
        'Genesis','Exodus','Leviticus','Numbers','Deuteronomy','Joshua','Judges','Ruth',
        '1 Samuel','2 Samuel','1 Kings','2 Kings','1 Chronicles','2 Chronicles','Ezra',
        'Nehemiah','Esther','Job','Psalms','Psalm','Proverbs','Ecclesiastes',
        'Song of Solomon','Song of Songs','Isaiah','Jeremiah','Lamentations','Ezekiel',
        'Daniel','Hosea','Joel','Amos','Obadiah','Jonah','Micah','Nahum','Habakkuk',
        'Zephaniah','Haggai','Zechariah','Malachi',
        'Numeri','Deuteronomium','Jozua','Richteren','1 Samuël','2 Samuël',
        '1 Koningen','2 Koningen','1 Kronieken','2 Kronieken','Nehemia','Ester',
        'Psalmen','Spreuken','Prediker','Hooglied','Jesaja','Jeremia','Klaagliederen',
        'Ezechiël','Daniël','Joël','Obadja','Jona','Micha','Habakuk','Zefanja',
        'Zacharia','Maleachi',
    ];
    $newTestamentNames = [
        'Matthew','Mark','Luke','John','Acts','Romans','1 Corinthians','2 Corinthians',
        'Galatians','Ephesians','Philippians','Colossians','1 Thessalonians',
        '2 Thessalonians','1 Timothy','2 Timothy','Titus','Philemon','Hebrews','James',
        '1 Peter','2 Peter','1 John','2 John','3 John','Jude','Revelation',
        'Mattheüs','Markus','Lukas','Johannes','Handelingen','Romeinen','1 Korinthe',
        '2 Korinthe','Galaten','Efeze','Filippenzen','Kolossenzen','1 Thessalonicenzen',
        '2 Thessalonicenzen','1 Timotheüs','2 Timotheüs','Filemon','Hebreeën','Jakobus',
        '1 Petrus','2 Petrus','1 Johannes','2 Johannes','3 Johannes','Judas','Openbaring',
    ];

    if (in_array($book, $oldTestamentNames, true)) {
        return 'ot';
    }
    if (in_array($book, $newTestamentNames, true)) {
        return 'nt';
    }

    return null;
}

function is_valid_bible_reference(string $reference): bool
{
    $reference = trim(urldecode($reference));
    if (bible_reference_category($reference) === null) {
        return false;
    }

    return preg_match(
        '/^(?:[1-4]?[A-Z]{2,3}\.\d+(?:\.\d+(?:-\d+)?)?|.+\s+\d+(?::\d+(?:-\d+)?)?)$/u',
        $reference
    ) === 1;
}

function testament_unavailable_message(string $testament, string $language): string
{
    $messages = [
        'en' => [
            'ot' => 'Old Testament not available in this translation',
            'nt' => 'New Testament not available in this translation',
        ],
        'nl' => [
            'ot' => 'Het Oude Testament is niet beschikbaar in deze vertaling',
            'nt' => 'Het Nieuwe Testament is niet beschikbaar in deze vertaling',
        ],
        'el' => [
            'ot' => 'Η Παλαιά Διαθήκη δεν είναι διαθέσιμη σε αυτή τη μετάφραση',
            'nt' => 'Η Καινή Διαθήκη δεν είναι διαθέσιμη σε αυτή τη μετάφραση',
        ],
    ];

    return $messages[$language][$testament] ?? $messages['en'][$testament];
}

function category_unavailable_message(string $category, string $language): string
{
    if ($category === 'apocrypha') {
        return apocrypha_unavailable_message($language);
    }
    return testament_unavailable_message($category, $language);
}

function invalid_reference_message(string $language): string
{
    return match ($language) {
        'nl' => 'Ongeldige Bijbelverwijzing',
        'el' => 'Μη έγκυρη βιβλική παραπομπή',
        default => 'Invalid reference',
    };
}

function reference_pending_message(string $language): string
{
    return match ($language) {
        'nl' => 'Dit Bijbelgedeelte is (nog) niet beschikbaar in deze vertaling',
        'el' => 'Η περικοπή δεν είναι (ακόμη) διαθέσιμη σε αυτή τη μετάφραση',
        default => 'This reference is not (yet) available in this translation',
    };
}

function reference_retrieval_message(string $language): string
{
    return match ($language) {
        'nl' => 'Dit Bijbelgedeelte kon niet uit deze vertaling worden opgehaald',
        'el' => 'Δεν ήταν δυνατή η ανάκτηση αυτής της περικοπής από αυτή τη μετάφραση',
        default => 'Unable to retrieve this reference from this translation',
    };
}

function response_http_status(array $headers): ?int
{
    foreach ($headers as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/', $header, $match)) {
            $status = (int)$match[1];
        }
    }
    return $status ?? null;
}
