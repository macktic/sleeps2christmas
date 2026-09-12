# Bible API TODO

## Split into a separate project

Move the Bible functionality out of `sleeps2christmas` into a dedicated private
repository and deploy it on its own hostname, such as `bible.sebs.space`.

Move the passage backends, reference handling, SQLite database, importer,
YouVersion utilities, VOTD and POTD functionality together.

Preserve the existing `sleeps2christmas` endpoints temporarily as compatibility
wrappers or redirects so existing Apple Shortcuts continue working.

## Unified endpoint

Create a single `bible.php` endpoint that replaces separate POTD, VOTD and
direct-reference interfaces.

Examples:

    bible.php?version=esv&ref=potd&format=audio
    bible.php?version=htb&ref=votd
    bible.php?version=esv&ref=john3:16
    bible.php?version=lxxsbl&ref=psalms51:1
    bible.php?version=lxx&ref=1maccabees1:1

Accept `key` as a backwards-compatible alias for `version`.

Keep passage selection separate from rendering:

1. Resolve `potd`, `votd`, or a direct reference.
2. Normalize the reference to USFM.
3. Validate and classify it.
4. Select the translation backend.
5. Return text, HTML or audio where supported.

## Compact multilingual references

Use compact, case-insensitive references without requiring spaces or URL-encoded
spaces.

Support commonly used English, Dutch, Scottish Gaelic and Greek book names and
abbreviations, plus USFM codes.

Examples that should resolve to `JHN.3.16`:

    john3:16
    joh3:16
    jhn3:16
    johannes3:16
    eòin3:16
    eoin3:16
    soisgeuleòin3:16
    soisgeuleoin3:16
    ιωάννης3:16
    κατάιωάννην3:16

Additional examples:

    1corinthians13:4-7
    1korinthe13:4-7
    1co13:4-7
    proverbs12
    spreuken12
    1maccabees1:1
    1makkabeeën1:1

Parse references from right to left: verse or range, chapter, then book. Resolve
the book through an explicit multilingual alias table rather than fuzzy
matching.

Encoded spaces may be tolerated for generated URLs, but compact references are
the documented public format.

## Scottish Gaelic

Add a Scottish Gaelic translation, preferably through an official API when one
is available under suitable terms.

Investigate the Scottish Gaelic editions publicly listed by YouVersion,
including the complete `ABG1992` Bible and the separate `MacGAP` Apocrypha.
Platform API availability and licensing must be tested independently from
consumer-app availability.

Include common Scottish Gaelic book names and spelling variants in the compact
reference alias table. Support both accented and commonly typed unaccented
forms.

Examples:

    eòin3:16
    eoin3:16
    soisgeuleòin3:16
    mata5:3
    salm23:1
    gnàthfhacal3:5
    gnathfhacal3:5

## Landing page and errors

Render the project README when visiting the project root, calling `bible.php`
without options, or supplying a missing or invalid version key.

Once a valid version has been selected, return concise API errors rather than
the README.

Examples:

    /                                           -> README
    /bible.php                                  -> README
    /bible.php?version=invalid                  -> README with version error
    /bible.php?version=esv&ref=invalid          -> Invalid reference

Document:

- Translation keys
- Supported formats
- Compact reference syntax
- POTD and VOTD
- Testament and Apocrypha coverage
- Example URLs
- Localized error behaviour

## English Septuagint and Apocrypha

Investigate adding Brenton's public-domain English Septuagint as a local
translation backed by SQLite.

Also consider other English Apocrypha translations, subject to their licensing
conditions. NETS is useful for study but is not suitable for unrestricted local
publication without permission.

Potential sources and options:

- Brenton's English Septuagint
- KJV Apocrypha
- Revised Version with Apocrypha
- NRSVue with Apocrypha
- NETS for comparison and study

Prefer an official API when the required translation and books are available
under suitable terms. If an API is unavailable, incomplete or a poor fit,
consider importing a suitable local text instead, including public-domain
sources such as Brenton.

Preserve source attribution, licensing information and original documentation
for either approach and alongside any generated database.
