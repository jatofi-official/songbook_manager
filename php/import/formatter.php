<?php
/**
 * formatter.php — finálne formátovanie na "domáci" štýl spevníka.
 * Aplikuje sa na výstup KAŽDÉHO zdroja (link/internet/upload rovnako).
 *
 * Pravidlá (podľa zadania):
 *  - Sekčný marker na VLASTNOM riadku NAD slohou (nie inline pred textom).
 *  - Verše prečíslované 1., 2., ... ; refrén "®:" ; bridge "B:" ; medzihra "Medzihra".
 *  - Akordy len v PRVOM výskyte slohy/refrénu (opakované slohy = len text).
 *  - Melizma: dlhé opakované písmená skrátené na 3 (dááááál -> dáál).
 *  - Intro / samotné akordy: bez čiariek, len medzery.
 * Konzervatívne: nečísluje bloky bez markera v zdroji (nemylne neoznačí intro ako slohu).
 */
require_once __DIR__ . '/detector.php';

/* ------------------------------------------------------------------ */
/* Junk strip + beat-grid (UG paste smeti)                            */
/* ------------------------------------------------------------------ */

/** Je riadok uploader-smeť (URL, ATTENTION, prosby o hviezdičky...)? */
function imp_is_junk_line(string $line): bool {
    $t = trim($line);
    if ($t === '') return false;
    if (preg_match('#^https?://#i', $t)) return true;
    if (preg_match('/^capo\s+\d+$/i', $t)) return true;
    if (preg_match('/\(youtube starts/i', $t)) return true;
    if (preg_match('/ATTENTION:|Worked out on request|If you like my|don\'t hesitate|Here is the list|Do you mind your avatar|STARS it deserves|add favorite|per-?page=\d+/iu', $t)) {
        return true;
    }
    // count-in: "One, two, one two three four!"
    if (preg_match('/^(one|two|three|four|jeden|dva|tri|štyri)[\s,!]+((one|two|three|four|jeden|dva|tri|štyri)[\s,!]+){2,}/iu', $t)) {
        return true;
    }
    return false;
}

/**
 * Beat-grid riadok: "| 1 2 3 4 | ..." -> null (zmazať);
 * "| CM7 | GM7 | ..." -> "CM7  GM7" (akordy, collapse duplikáty za sebou);
 * inak vráti pôvodný riadok.
 */
function imp_grid_to_chords(string $line) {
    $t = trim($line);
    if ($t === '' || strpos($t, '|') === false) return $line;
    if (substr_count($t, '|') < 2) return $line;
    $inner = trim($t, '| ');
    // pure beat-count grid (len čísla/medzery/|)
    if (preg_match('/^[\d\s|]+$/', $inner)) return null;
    // chord grid: každá bunka prázdna alebo akord (s prípadnými * značkami)
    $cells = array_map('trim', explode('|', $inner));
    $chords = [];
    foreach ($cells as $cell) {
        if ($cell === '') continue;
        $tok = rtrim($cell, '*');
        if (!imp_is_chord_token($tok)) return $line; // nie je chord grid -> nechaj tak
        if (empty($chords) || end($chords) !== $tok) $chords[] = $tok; // collapse duplikáty
    }
    if (empty($chords)) return null;
    return implode('  ', $chords);
}

/** Pre-clean: junk riadky preč, beat-grid na akordy. */
function imp_strip_junk(array $lines): array {
    $out = [];
    foreach ($lines as $line) {
        if (imp_is_junk_line($line)) continue;
        $g = imp_grid_to_chords($line);
        if ($g === null) continue;
        $out[] = $g;
    }
    return $out;
}

/** Spoločný regex pre marker prefix (bez kotiev). */
function imp_marker_re(): string {
    // všetky markery končia delimiterom (: . ) ]) -> bezpečné povoliť aj bez medzery za nimi
    return '(®:?|\(r\)|R:|Ref\.?:|\d+\.|B:|\[[^\]]+\]'
         . '|(?:intro|chorus|verse|sloka|refr[eé]n|bridge|solo|outro|medzihra|mezihra|predohra|dohra)\s*:)';
}

/** Začína riadok sekčným markerom? (na pre-split sekcií čo nie sú oddelené prázdnym riadkom) */
function imp_line_starts_section(string $line): bool {
    if (imp_is_chord_line($line)) return false;
    if (imp_is_pure_marker(trim($line))) return true; // celý riadok = sekčné slovo (Chorus, Bridge...)
    return (bool)preg_match('/^\s*' . imp_marker_re() . '/iu', $line);
}

/**
 * Vlož oddeľovače sekcií tam kde marker začína uprostred bloku (žiadny prázdny riadok).
 * Sekcia začína pri chord riadku nad označeným lyric riadkom (ak je), inak pri markeri.
 */
function imp_presplit(array $lines): array {
    $res = [];
    foreach ($lines as $line) {
        if (trim($line) !== '' && imp_line_starts_section($line)) {
            // inline marker na lyric riadku (chord nad ním) -> zlom pred chord riadkom;
            // samostatný marker riadok -> zlom len pred markerom (nevťahuj intro chord)
            $isInline = !imp_is_pure_marker(trim($line));
            if ($isInline && !empty($res) && imp_is_chord_line(end($res))) {
                $chord = array_pop($res);
                if (!empty($res) && trim(end($res)) !== '') $res[] = '';
                $res[] = $chord;
            } elseif (!empty($res) && trim(end($res)) !== '') {
                $res[] = '';
            }
        }
        $res[] = $line;
    }
    return $res;
}

/**
 * Rozpoznaj typ sekcie z markera.
 *  verse        -> číslovať "N."
 *  chorus       -> "®:" / "®"
 *  bridge       -> "B:"   (POZOR: Break ≠ Bridge!)
 *  interlude    -> "Medzihra" LEN pri explicitnej Medzihre
 *  instrumental -> žiadny label (Intro/Interlude/Solo/Outro/Instrumental...)
 *  strip        -> žiadny label, obsah ostane (Pre-Chorus, End-Chorus, Break...)
 */
function imp_section_type(string $marker): string {
    $m = trim(trim($marker), "[]: \t");
    if ($m === '') return 'none';
    if (preg_match('/^\d+\.?$/', $m)) return 'verse';
    if (preg_match('/^(verse|sloka)\s*\d*$/iu', $m)) return 'verse';
    // pre-/end- chorus NIE je refrén -> strip (skôr než chorus match!)
    if (preg_match('/^(pre|end)[- ]?(chorus|refr)/iu', $m)) return 'strip';
    if (preg_match('/(®|\(r\)|^r$|^ref\.?$|refr|chorus)/iu', $m)) return 'chorus';
    if (preg_match('/^break\b/iu', $m)) return 'strip'; // Break ≠ B:
    if (preg_match('/^(b|bridge|most)$/iu', $m)) return 'bridge';
    if (preg_match('/(medzihra|mezihra)/iu', $m)) return 'interlude'; // len explicitná
    if (preg_match('/(interlude|solo|predohra|dohra|outro|coda|instrumental|intro|úvod|link)/iu', $m)) return 'instrumental';
    return 'other';
}

/**
 * Vyber marker z bloku (prvý pure-marker riadok, alebo inline prefix prvého lyric riadku).
 * Vráti [markerRaw, lines] — lines už bez markera (inline prefix nahradený medzerami
 * ak je nad ním chord riadok, inak orezaný).
 */
function imp_extract_block_marker(array $block): array {
    if (empty($block)) return ['', $block];

    // 1) samostatný marker riadok
    $first = trim($block[0]);
    if (imp_is_pure_marker($first)) {
        return [$first, array_slice($block, 1)];
    }

    // 2) inline prefix na prvom lyric riadku (nie chord). Marker končí delimiterom,
    // preto (\s*) — chytí aj "R:Refren" bez medzery.
    $markerRe = '/^(\s*)' . imp_marker_re() . '(\s*)/iu';
    $hasChordLine = false;
    foreach ($block as $l) { if (imp_is_chord_line($l)) { $hasChordLine = true; break; } }

    foreach ($block as $idx => $line) {
        if (imp_is_chord_line($line)) continue; // preskoč chord riadky
        if (preg_match($markerRe, $line, $mm)) {
            $marker = trim($mm[2]);
            $prefixLen = imp_len($mm[0]);
            $rest = mb_substr($line, $prefixLen, null, 'UTF-8');
            if ($idx > 0 && imp_is_chord_line($block[$idx - 1])) {
                // odstráň marker z lyric A posuň chord riadok nad ním o rovnako doľava
                $block[$idx] = $rest;
                $block[$idx - 1] = preg_replace('/^ {0,' . $prefixLen . '}/', '', $block[$idx - 1]);
            } else {
                $block[$idx] = ltrim($rest);
            }
            return [$marker, $block];
        }
        break; // prvý lyric riadok nemal marker -> žiadny
    }
    return ['', $block];
}

/** Je riadok čistý marker (nič iné)? */
function imp_is_pure_marker(string $line): bool {
    $t = trim($line);
    if ($t === '') return false;
    if (preg_match('/^(Medzihra|Mezihra|Intro|Predohra|Solo|Coda|Outro|Bridge|Break|Chorus|Pre[- ]?Chorus|End[- ]?Chorus|Instrumental|Interlude|Refr[eé]n|Ref|Verse(\s*\d+)?|Sloka(\s*\d+)?)$/iu', $t)) return true;
    return (bool)preg_match('/^' . imp_marker_re() . '$/iu', $t);
}

/** Odstráň čiarky medzi akordmi (intro/samotné akordy). NEKOLABUJ medzery (zarovnanie!). */
function imp_decomma_chords(string $line): string {
    return rtrim(str_replace(',', ' ', $line));
}

/**
 * Chord-only riadok (intro/medzihra) — domáci štýl:
 *  - preč čiarky a samostatné pomlčky (padding),
 *  - zlúč rovnaké akordy IDÚCE ZA SEBOU (C C G G -> C G) — "akord len pri zmene",
 *  - oddeľ dvoma medzerami.
 * POZOR: aplikovať LEN na samotné chord riadky, nie na pozičné akordy nad textom
 * (tam by sa rozbilo zarovnanie). Zámerné opakovanie na zvýraznenie si user píše ručne
 * v editore (engine ho sám nepridáva).
 */
function imp_declutter_chord_line(string $line): string {
    $line = str_replace(',', ' ', $line);
    $toks = preg_split('/\s+/u', trim($line), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $out = [];
    foreach ($toks as $tok) {
        if ($tok === '-' || $tok === '–' || $tok === '—') continue; // dash padding preč
        if (!empty($out) && end($out) === $tok) continue;            // zlúč rovnaké za sebou
        $out[] = $tok;
    }
    return implode('  ', $out);
}

/**
 * Signatúra slohy = holý text (bez akordov, malé písmená, len písmená/čísla).
 * Slúži na detekciu OPAKOVANÝCH strof (Rule A: číslujeme len tie čo sa reálne opakujú).
 */
function imp_verse_signature(array $lines): string {
    $parts = [];
    foreach ($lines as $l) {
        if (trim($l) === '' || imp_is_chord_line($l)) continue;
        $parts[] = $l;
    }
    $s = mb_strtolower(implode(' ', $parts), 'UTF-8');
    return preg_replace('/[^\p{L}\p{N}]+/u', '', $s);
}

/** Skráť melizmu: beh rovnakého znaku dĺžky >3 -> 3, v páre uprav aj chord riadok. */
function imp_collapse_melisma(string $lyric, ?string $chord = null): array {
    // nájdi behy (od konca kvôli stabilným indexom)
    if (!preg_match_all('/(.)\1{3,}/u', $lyric, $mm, PREG_OFFSET_CAPTURE)) {
        return [$lyric, $chord];
    }
    // pracuj na poli code-pointov
    $lyChars = preg_split('//u', $lyric, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $chChars = $chord !== null ? (preg_split('//u', $chord, -1, PREG_SPLIT_NO_EMPTY) ?: []) : null;
    // nájdi behy v poli znakov
    $runs = [];
    $i = 0; $n = count($lyChars);
    while ($i < $n) {
        $j = $i;
        while ($j + 1 < $n && $lyChars[$j + 1] === $lyChars[$i]) $j++;
        $len = $j - $i + 1;
        if ($len > 3 && trim($lyChars[$i]) !== '') $runs[] = [$i, $len];
        $i = $j + 1;
    }
    // od konca: odstráň (len-3) znakov; z chord riadku len ak sú tam medzery
    foreach (array_reverse($runs) as [$start, $len]) {
        $remove = $len - 3;
        $delFrom = $start + 3;
        array_splice($lyChars, $delFrom, $remove);
        if ($chChars !== null) {
            $slice = array_slice($chChars, $delFrom, $remove);
            $allSpace = true;
            foreach ($slice as $c) { if (trim($c) !== '') { $allSpace = false; break; } }
            if ($allSpace) array_splice($chChars, $delFrom, $remove);
            // ak tam je akord, nechaj chord riadok tak (akord ostane približne)
        }
    }
    $ly = implode('', $lyChars);
    $ch = $chChars !== null ? rtrim(implode('', $chChars)) : $chord;
    return [$ly, $ch];
}

/** Počet vedúcich medzier. */
function imp_lead(string $s): int { return strlen($s) - strlen(ltrim($s, ' ')); }

/** Spracuj riadky bloku: strip chords (opakovaná sekcia), decomma, melizma, per-pár dedent. */
function imp_process_block_lines(array $lines, bool $stripChords): array {
    // strip chords z opakovanej sekcie
    if ($stripChords) {
        $lines = array_values(array_filter($lines, fn($l) => !imp_is_chord_line($l)));
    }
    $out = [];
    $n = count($lines);
    for ($i = 0; $i < $n; $i++) {
        $line = $lines[$i];
        if (imp_is_chord_line($line)) {
            $next = $lines[$i + 1] ?? null;
            if ($next !== null && trim($next) !== '' && !imp_is_chord_line($next)) {
                // pár chord+lyric -> melizma, potom per-pár dedent (lyric na stĺpec 0, chord posunutý s ním)
                [$ly, $ch] = imp_collapse_melisma($next, $line);
                $d = min(imp_lead($ch), imp_lead($ly));
                $ch2 = rtrim(substr($ch, $d));
                $ly2 = rtrim(substr($ly, $d));
                // UG štýl (akord na začiatku riadku, text odsadený): ak sa celý chord riadok
                // zmestí pred začiatok textu, odsadenie textu preč (akord ostáva na stĺpci 0)
                $ll = imp_lead($ly2);
                if ($ll > 0 && strlen($ch2) <= $ll) $ly2 = substr($ly2, $ll);
                $out[] = $ch2;
                $out[] = $ly2;
                $i++;
            } else {
                // samotný akordový riadok (intro/medzihra) -> declutter: bez čiariek/pomlčiek,
                // rovnaké akordy za sebou zlúčené, ľavý okraj preč
                $out[] = imp_declutter_chord_line($line);
            }
        } else {
            [$ly, ] = imp_collapse_melisma($line, null);
            $out[] = ltrim(rtrim($ly), ' '); // lyric bez akordu -> na stĺpec 0
        }
    }
    return $out;
}

/**
 * Hlavný formátovač. Konzervatívny: verše čísluje len ak zdroj mal marker.
 */
function imp_format_final(string $content): string {
    $content = str_replace(["\r\n", "\r"], "\n", $content);
    $rawLines = imp_strip_junk(explode("\n", $content)); // uploader smeti + beat-grid preč
    $rawLines = imp_presplit($rawLines); // oddeľ sekcie bez prázdnych riadkov

    // rozdeľ na bloky podľa prázdnych riadkov
    $blocks = []; $cur = [];
    foreach ($rawLines as $l) {
        if (trim($l) === '') { if ($cur) { $blocks[] = $cur; $cur = []; } }
        else $cur[] = $l;
    }
    if ($cur) $blocks[] = $cur;

    // --- Fáza 1: rozparsuj bloky (marker + typ + signatúra slohy) -------------
    $parsed = [];
    foreach ($blocks as $block) {
        [$markerRaw, $lines] = imp_extract_block_marker($block);
        $type = imp_section_type($markerRaw);

        // chord-only blok bez markera = inštrumentálny -> len akordy, žiadny label
        $allChords = !empty($lines);
        foreach ($lines as $l) { if (trim($l) !== '' && !imp_is_chord_line($l)) { $allChords = false; break; } }
        if ($type === 'none' && $allChords) $type = 'instrumental';

        $parsed[] = [
            'markerRaw' => $markerRaw,
            'lines'     => $lines,
            'type'      => $type,
            'sig'       => ($type === 'verse') ? imp_verse_signature($lines) : '',
        ];
    }

    // Rule A: číslujeme LEN strofy ktoré sa v piesni reálne opakujú (count >= 2).
    // Každej rozdielnej slohe prideľ ordinál v poradí prvého výskytu (1,2,3...),
    // ale zobraz ho len ak sa daná sloha opakuje — takže "verš 3 sa opakuje" ukáže "3.".
    $sigCount = []; $sigOrd = []; $ord = 0;
    foreach ($parsed as $p) {
        if ($p['type'] !== 'verse' || $p['sig'] === '') continue;
        $sigCount[$p['sig']] = ($sigCount[$p['sig']] ?? 0) + 1;
        if (!isset($sigOrd[$p['sig']])) $sigOrd[$p['sig']] = ++$ord;
    }
    $sigNum = [];
    foreach ($sigOrd as $sig => $n) {
        if (($sigCount[$sig] ?? 0) >= 2) $sigNum[$sig] = $n;
    }

    // --- Fáza 2: vypíš bloky --------------------------------------------------
    $out = [];
    $chorusSeen = false; $verseSeenSig = [];
    foreach ($parsed as $p) {
        $type = $p['type']; $lines = $p['lines']; $markerRaw = $p['markerRaw'];

        $markerOut = null;
        switch ($type) {
            // verse: číslo LEN ak je sloha opakovaná (inak žiadny marker)
            case 'verse':        $markerOut = isset($sigNum[$p['sig']]) ? $sigNum[$p['sig']] . '.' : null; break;
            // prvý refrén = "®:" (s akordmi), opakovanie = "®" (bez dvojbodky)
            case 'chorus':       $markerOut = $chorusSeen ? '®' : '®:'; break;
            case 'bridge':       $markerOut = 'B:'; break;
            case 'interlude':    $markerOut = 'Medzihra'; break; // len explicitná Medzihra
            case 'instrumental': $markerOut = null; break;       // len akordy, bez labelu
            case 'strip':        $markerOut = null; break;       // Pre-Chorus/Break/... label preč
            case 'other':        $markerOut = ($markerRaw !== '') ? $markerRaw : null; break;
            default:             $markerOut = null;
        }

        // Strip akordy len pri REÁLNOM opakovaní: rovnaká sloha videná znova / opakovaný refrén.
        // (Rôzne strofy si akordy ponechajú — každá má vlastnú progresiu.)
        $strip = false;
        if ($type === 'verse' && $p['sig'] !== '') {
            if (isset($verseSeenSig[$p['sig']])) $strip = true;
            $verseSeenSig[$p['sig']] = true;
        }
        if ($type === 'chorus') { if ($chorusSeen) $strip = true; $chorusSeen = true; }

        $lines = imp_process_block_lines($lines, $strip);

        if ($markerOut !== null) $out[] = $markerOut;
        foreach ($lines as $l) $out[] = $l;
        $out[] = '';
    }

    return imp_normalize_output(implode("\n", $out));
}
