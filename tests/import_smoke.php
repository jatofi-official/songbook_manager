<?php
require_once __DIR__ . '/../php/import/parsers.php';

function same($actual, $expected, string $label): void {
    if ($actual !== $expected) {
        fwrite(STDERR, "FAIL $label\nEXPECTED:\n" . var_export($expected, true)
            . "\nACTUAL:\n" . var_export($actual, true) . "\n");
        exit(1);
    }
    echo "OK $label\n";
}

same(imp_clean_title('Tri slová (NAOZAJ správne akordy)'), 'Tri slová', 'clean source-note title');
same(imp_clean_title('Nádherný (Ve Tvém srdci je soucit)'), 'Nádherný (Ve Tvém srdci je soucit)', 'keep real subtitle');
same(imp_normalize_chord('F#mi7'), 'F#m7', 'normalize Slovak minor chord');
same(imp_grid_to_chords('| C | C | G | Am |'), 'C  G  Am', 'collapse repeated chord grid cells');

$messy = "R:Refren prvy\nC   G\ntext refrénu\n\n1. Sloha\nAm  F\ntext slohy\n\nChorus\nC   G\ntext refrénu";
same(
    imp_format_final($messy),
    // Rule A: sloha "1." sa neopakuje -> žiadne číslo. Refrén: prvý ®: (s akordmi), opakovaný ® (bez akordov).
    "®:\nRefren prvy\nC   G\ntext refrénu\n\nSloha\nAm  F\ntext slohy\n\n®\ntext refrénu",
    'markers, no number for non-repeated verse, repeated chorus stripped'
);

/* ---- Rule A: číslovanie len opakovaných strof ---- */
same(
    imp_format_final("1.\nC G\nPrva sloha jedna\n\n2.\nAm F\nDruha sloha dva"),
    "C G\nPrva sloha jedna\n\nAm F\nDruha sloha dva",
    'Rule A: distinct verses get no numbers'
);
same(
    imp_format_final("1.\nC G\nAlfa text\n\n2.\nAm F\nBeta text\n\n1.\nC G\nAlfa text"),
    "1.\nC G\nAlfa text\n\nAm F\nBeta text\n\n1.\nAlfa text",
    'Rule A: repeated verse numbered (ordinal), repeat stripped'
);

/* ---- Rule B: chord-only declutter (pomlčky + rovnaké akordy za sebou) ---- */
same(
    imp_format_final("Medzihra\nC - - G Am Am"),
    "Medzihra\nC  G  Am",
    'Rule B: chord-only declutter'
);

/* ---- Rule C: B/H podľa konvencie ---- */
imp_chord_english(true);
same(imp_normalize_chord('B'),   'H',  'Rule C: EN bare B -> H');
same(imp_normalize_chord('Bb'),  'B',  'Rule C: Bb -> B');
same(imp_normalize_chord('A#m'), 'Bm', 'Rule C: A#m -> Bm');
same(imp_normalize_chord('B/D#'),'H/D#','Rule C: EN bass B -> H');
imp_chord_english(false);
same(imp_normalize_chord('B'),   'B',  'Rule C: SK bare B stays B');
same(imp_normalize_chord('H'),   'H',  'Rule C: H stays H');
same(imp_normalize_chord('A#'),  'B',  'Rule C: A# -> B (SK too)');

$chunks = imp_split_songs("# Prvá\nAutor A\nC G\ntext\n---\n# Druhá\nAutor B\nAm F\ntext");
same(count($chunks), 2, 'split explicit multi-song upload');
same(
    imp_extract_upload_meta($chunks[0]),
    ['title' => 'Prvá', 'artist' => 'Autor A', 'body' => "C G\ntext"],
    'extract upload metadata'
);

same(imp_pdf_category_hint('Adventné'), [8, 'adventné'], 'PDF spiritual category hint');

/* ---- P1: inline marker nesmie odpojiť akord od textu (zarovnanie) ---- */
$inline = "     D        F   G F\nR: Horí ti maštaľ,  horí\n     D        F   G F\n     Horí ti maštaľ,  horí";
$outIr = imp_format_final(imp_render_ir(imp_plaintext_to_ir($inline)));
same(strpos($outIr, 'D  F  G  F') === false, true, 'P1: chords keep positions under inline marker');
same(substr_count($outIr, 'Horí ti maštaľ'), 2, 'P1: both verses survive');

/* ---- P2: junk sa nesmie stať autorom ---- */
foreach (['intro: Hm A G F#m', 'A, Hm, E, A', 'ludovka', 'capo VIII', 'Medzihra'] as $bad) {
    same(imp_bad_artist_line($bad), true, "P2: rejected as artist: $bad");
}
same(imp_bad_artist_line('Modus'), false, 'P2: real artist accepted');
same(imp_extract_upload_meta("Včelín\nintro: Hm A G F#m\ntext piesne")['artist'], '',
     'P2: intro line not used as artist');
same(imp_extract_upload_meta("Mezi horami\nludovka\nMezi horami mezi doly")['artist'], '',
     'P2: category word not used as artist');
same(imp_extract_upload_meta("C G\nPrvý spievaný riadok\nďalší")['title'], 'Prvý spievaný riadok',
     'P2: title falls back to first lyric line');

/* ---- P3: kategória ---- */
same(imp_is_spiritual('Merry Christmas everybody'), false, 'P3: Christmas is not spiritual');
same(imp_is_spiritual('Bohemian Rhapsody is this the real life'), false, 'P3: Bohemian is not spiritual');
same(imp_is_spiritual('Ježiš je Pán'), true, 'P3: real spiritual still detected');
same(imp_detect_category('Mezi horami', 'ľudovka', 'Mezi horami mezi doly'), 9, 'P3: ľudovka -> Ľudovky');
same(imp_detect_language('Mezi horami mezi doly jako ptak'), 'cz', 'P3: diacritic-less CZ not English');

echo "ALL_OK\n";
