<?php
/**
 * detector.php — nízkoúrovňové utility pre import.
 *
 * Spoločný medzikód (IR): scraper vyprodukuje pole "riadkov", každý:
 *   ['type'=>'lyric',     'segments'=>[ ['chord'=>'Am','text'=>'srdci je '], ... ] ]
 *   ['type'=>'chordonly', 'chords'=>['Dmi','Gmi', ...] ]   // intro/medzihra
 *   ['type'=>'marker',    'text'=>'1.' | 'R:' | '®:' ... ]
 *   ['type'=>'blank']
 * imp_render_ir() z toho spraví finálny 2-line positional text (chord line nad lyric line).
 *
 * Prefix imp_* aby nekolidoval s functions.php.
 */

/* ------------------------------------------------------------------ */
/* Chord normalizácia                                                 */
/* ------------------------------------------------------------------ */

/**
 * B/H konvencia pre normalizáciu akordov (per-pieseň, module-static).
 *  - $set = null  -> vráť aktuálny stav
 *  - $set = bool  -> nastav (true = anglický zdroj: B = B-natural)
 * Nastavuje sa pred renderom piesne (scrapers.php / import_process.php) podľa zdroja.
 */
function imp_chord_english(?bool $set = null): bool {
    static $en = false;
    if ($set !== null) $en = $set;
    return $en;
}

/**
 * Remap koreňa akordu na slovenský B/H systém.
 *  - A# / Bb  -> B   (enh. B-flat = slovenské "B")
 *  - holé B   -> H   LEN v anglickej konvencii (B = B-natural); v SK/CZ ostáva B
 *  - H a ostatné (C,D,E,F,G,A + ich #/b) -> nezmenené
 * $root = veľké písmeno + prípadné #/b.
 */
function imp_remap_bh_root(string $root): string {
    if ($root === 'A#' || $root === 'Bb') return 'B';
    if ($root === 'B') return imp_chord_english() ? 'H' : 'B';
    return $root;
}

/**
 * Normalizuj jeden akord na cieľový formát.
 *  - lowercase root  => minor  (ChordWP/mladeznicky konvencia): c# -> C#m, a -> Am
 *  - "mi" suffix     => "m"    (supermusic/brnkni konvencia):   Dmi -> Dm, Emi7 -> Em7
 *  - B/H systém: A#/Bb -> B; anglické B(-natural) -> H (viď imp_chord_english()).
 */
function imp_normalize_chord(string $chord): string {
    $chord = trim($chord);
    if ($chord === '' || $chord === 'N.C.' || strtoupper($chord) === 'NC') {
        return $chord;
    }
    // root: A-H písmeno (+ prípadné #/b)
    if (!preg_match('/^([A-Ha-h])([#b]?)(.*)$/u', $chord, $m)) {
        return $chord; // nie je to rozpoznateľný akord — nechaj tak
    }
    $letter    = $m[1];
    $accidental = $m[2];
    $rest      = $m[3];

    $isMinorByCase = ctype_lower($letter);
    $root = imp_remap_bh_root(strtoupper($letter) . $accidental);

    // "mi" na začiatku zvyšku -> "m"  (Dmi -> Dm, Hmi -> Hm)
    $rest = preg_replace('/^mi(?![a-z])/', 'm', $rest);

    if ($isMinorByCase) {
        // lowercase = minor; ak už rest nezačína na m, pridaj m
        if (strlen($rest) === 0 || $rest[0] !== 'm') {
            $rest = 'm' . $rest;
        }
    }

    // bass po '/' (napr. F#/A# -> F#/B, B/D# -> H/D# v EN) — remap aj basový tón
    $rest = preg_replace_callback('~/([A-Ha-h])([#b]?)~u', function ($mm) {
        return '/' . imp_remap_bh_root(strtoupper($mm[1]) . $mm[2]);
    }, $rest);

    return $root . $rest;
}

/* ------------------------------------------------------------------ */
/* Chord-line detekcia (pre plain text / generic / upload)            */
/* ------------------------------------------------------------------ */

/** Vyzerá token ako akord? */
function imp_is_chord_token(string $tok): bool {
    $tok = trim($tok);
    if ($tok === '') return false;
    if ($tok === 'N.C.' || $tok === '%') return true;
    // /: :/ repeat, x2, 4x
    if (preg_match('#^/?:?$#', $tok)) return true;
    if (preg_match('/^\d+x$/i', $tok) || preg_match('/^x\d+$/i', $tok)) return true;
    // dash padding (drží akord cez slabiky) — filler v chord riadku, declutter ho odstráni
    if (preg_match('/^[-–—]+$/u', $tok)) return true;
    // vlastný akord: root + kvalita + rozšírenia + bass (delimiter ~ kvôli # a /)
    return (bool)preg_match(
        '~^[A-Ha-h][#b]?(m|mi|maj|min|dim|aug|sus|add|M)?\d*(sus\d)?(add\d+)?(/[A-Ha-h][#b]?)?[)(\d]*$~u',
        $tok
    );
}

/**
 * Je celý riadok akordový (nad textom)?  True ak všetky "slová" sú akordy
 * a riadok neobsahuje dlhé lyric slová.
 */
function imp_is_chord_line(string $line): bool {
    $t = trim($line);
    if ($t === '') return false;
    // odstráň bežné repeat/tabové symboly
    $tokens = preg_split('/\s+/u', $t);
    $chordCount = 0; $total = 0;
    foreach ($tokens as $tok) {
        if ($tok === '') continue;
        $total++;
        if (imp_is_chord_token($tok)) $chordCount++;
    }
    if ($total === 0) return false;
    return $chordCount === $total; // striktné: všetky tokeny musia byť akordy
}

/* ------------------------------------------------------------------ */
/* IR renderer — segmenty (chord+text) -> 2 riadky (chord nad text)   */
/* ------------------------------------------------------------------ */

/** mb-safe dĺžka */
function imp_len(string $s): int { return mb_strlen($s, 'UTF-8'); }

/**
 * Vyrenderuj jeden 'lyric' riadok (pole segmentov) na [chordLine, lyricLine].
 * Chord sa umiestni nad začiatok textu svojho segmentu; pri kolízii sa
 * lyric odsadí medzerami (štandardný chordpro->text render), min. 1 medzera medzi akordmi.
 */
function imp_render_segments(array $segments): array {
    $chordLine = '';
    $lyricLine = '';
    foreach ($segments as $seg) {
        $chord = imp_normalize_chord((string)($seg['chord'] ?? ''));
        $text  = (string)($seg['text'] ?? '');

        if ($chord !== '') {
            $col = imp_len($lyricLine);
            // dopĺň chordLine medzerami po pozíciu col
            if (imp_len($chordLine) < $col) {
                $chordLine .= str_repeat(' ', $col - imp_len($chordLine));
            } elseif ($chordLine !== '') {
                $chordLine .= ' '; // dva akordy tesne vedľa seba -> oddeliť medzerou
            }
            // predošlý akord presahuje? odsadíme lyric pod novú pozíciu akordu
            if (imp_len($chordLine) > $col) {
                $lyricLine .= str_repeat(' ', imp_len($chordLine) - $col);
            }
            $chordLine .= $chord;
        }
        $lyricLine .= $text;
    }
    return [rtrim($chordLine), rtrim($lyricLine)];
}

/**
 * Prevod 2-riadkového formátu (chord line nad lyric line) na segmenty.
 * Chord na stĺpci C sa naviaže na text od stĺpca C po ďalší akord.
 * Segmenty potom prejdú imp_render_segments -> normalizované akordy + zarovnanie.
 */
function imp_two_line_to_segments(string $chordLine, string $lyricLine): array {
    $chars = preg_split('//u', $chordLine, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $tokens = []; $cur = ''; $start = -1;
    foreach ($chars as $i => $ch) {
        if ($ch === ' ' || $ch === "\t" || $ch === "\xC2\xA0") {
            if ($cur !== '') { $tokens[] = [$start, $cur]; $cur = ''; $start = -1; }
        } else {
            if ($cur === '') $start = $i;
            $cur .= $ch;
        }
    }
    if ($cur !== '') $tokens[] = [$start, $cur];
    if (empty($tokens)) return [['chord' => '', 'text' => $lyricLine]];

    $llen = imp_len($lyricLine);
    $segs = [];
    if ($tokens[0][0] > 0) {
        $segs[] = ['chord' => '', 'text' => mb_substr($lyricLine, 0, $tokens[0][0], 'UTF-8')];
    }
    $n = count($tokens);
    for ($i = 0; $i < $n; $i++) {
        $col  = $tokens[$i][0];
        $next = ($i + 1 < $n) ? $tokens[$i + 1][0] : max($llen, $col);
        $text = ($col <= $llen) ? mb_substr($lyricLine, $col, $next - $col, 'UTF-8') : '';
        $segs[] = ['chord' => $tokens[$i][1], 'text' => $text];
    }
    return $segs;
}

/** Vyrenderuj celé IR do cieľového textu. */
function imp_render_ir(array $lines): string {
    $out = [];
    foreach ($lines as $ln) {
        switch ($ln['type'] ?? '') {
            case 'blank':
                $out[] = '';
                break;
            case 'marker':
                $out[] = rtrim((string)$ln['text']);
                break;
            case 'chordonly':
                $chords = array_map('imp_normalize_chord', $ln['chords'] ?? []);
                $out[] = rtrim(implode('  ', array_filter($chords, fn($c) => $c !== '')));
                break;
            case 'lyric':
                [$c, $l] = imp_render_segments($ln['segments'] ?? []);
                // ak segment nemal žiadne akordy, vypíš len lyric riadok
                if (trim($c) === '') { $out[] = $l; }
                else { $out[] = $c; $out[] = $l; }
                break;
        }
    }
    return imp_normalize_output(implode("\n", $out));
}

/* ------------------------------------------------------------------ */
/* Výstupná normalizácia                                              */
/* ------------------------------------------------------------------ */

/** Zbav trailing whitespace, max 1 prázdny riadok medzi sekciami. */
function imp_normalize_output(string $text): string {
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $lines = explode("\n", $text);
    $lines = array_map('rtrim', $lines);
    $out = [];
    $blank = 0;
    foreach ($lines as $l) {
        if ($l === '') { $blank++; if ($blank > 1) continue; }
        else { $blank = 0; }
        $out[] = $l;
    }
    // orež vedúce/koncové prázdne riadky
    while (!empty($out) && $out[0] === '') array_shift($out);
    while (!empty($out) && end($out) === '') array_pop($out);
    return implode("\n", $out);
}

/* ------------------------------------------------------------------ */
/* Jazyk + kategória heuristika                                       */
/* ------------------------------------------------------------------ */

/** 'sk' | 'cz' | 'en' | 'other' z lyrics. */
function imp_detect_language(string $text): string {
    $t = mb_strtolower($text, 'UTF-8');
    // SK-špecifické znaky
    if (preg_match('/[ľĺŕôä#]/u', $t) && preg_match('/[ľĺŕô]/u', $t)) return 'sk';
    // CZ-špecifické
    if (preg_match('/[řěů]/u', $t)) return 'cz';
    // ostatné diakritické SK/CZ zdieľané
    if (preg_match('/[áčďéíňóšťúýž]/u', $t)) {
        // rozlíš: ak má ř/ě/ů -> cz (už vyššie), inak skús sk markery
        if (preg_match('~[ôä]~u', $t)) return 'sk';
        return 'cz';
    }
    // Bezdiakritický SK/CZ text by inak spadol do 'en' ("Mezi horami" -> Anglické).
    // ponytail: stopword check, 2 riadky; plný jazykový detektor až keď toto nestačí.
    if (preg_match('/\b(jsem|jsi|jsme|mezi|jako|ktery|proto|vsak|jeste|divka)\b/u', $t)) return 'cz';
    if (preg_match('/\b(som|sme|ako|ktory|preto|vsetko|ved|este|aby|ked|dievca)\b/u', $t)) return 'sk';
    // prevažne ASCII -> angličtina
    $ascii = preg_match_all('/[a-z]/', $t);
    $all   = max(1, preg_match_all('/\p{L}/u', $t));
    if ($ascii / $all > 0.95) return 'en';
    return 'other';
}

/**
 * Odhad category id (songs.category). $source = doména/typ zdroja.
 * Kategórie: 2 Greenhorns-Nedvědi, 3 Nohavica-Kryl, 4 Iné České,
 *            5 Slovenské, 7 Anglické-Iné, 8 Duchovné, 11 Ostatné.
 */
/**
 * Je text nábožensky/duchovný? (spomína Ježiša, Boha, kráľovstvo...)
 * Silný marker => hneď true; inak treba >=2 rôzne slabšie markery.
 */
function imp_is_spiritual(string $text): bool {
    $t = mb_strtolower($text, 'UTF-8');
    // Silné markery. \b na OBOCH stranách tam kde hrozí substring: "christ" inak matchol
    // "Christmas" -> z Merry Christmas sa stalo Duchovné.
    if (preg_match('/\bje[zž]iš|\bkristus\b|\bchrist(us)?\b|\bjesus\b|\baleluja\b|\bhallelujah\b'
                 . '|\bhosanna\b|sláva bohu|\bspasite[ľl]|\bsaviou?r\b|duch svätý|son of man/u', $t)) {
        return true;
    }
    // Slabé markery — treba >=2 rôzne. Substring pasce:
    //  "boh" matchol "Bohemian" (Bohemian Rhapsody -> Duchovné),
    //  "nebo" je v češtine bežná spojka "alebo" -> nahradené "nebes".
    $weak = ['\bboh(a|u|om|ovi)?\b', '\bgod\b', '\blord\b', '\bkráľovstv', '\bkingdom\b',
             '\bnebes', '\bheaven\b', '\bsvät', '\bholy\b', '\bkríž', '\bcross\b',
             '\bmilos', '\bgrace\b', '\bžalm', '\bmári', '\bamen\b', '\bworship\b', '\bchvál'];
    $hits = 0;
    foreach ($weak as $w) {
        if (preg_match('/' . $w . '/u', $t)) { $hits++; if ($hits >= 2) return true; }
    }
    return false;
}

function imp_detect_category(string $title, string $artist, string $lyrics, string $source = ''): int {
    $src = mb_strtolower($source, 'UTF-8');
    // 1) zdroj = duchovné
    foreach (['proscholy', '4334', 'mladeznicky', 'worship'] as $rel) {
        if (strpos($src, $rel) !== false) return 8;
    }
    // 2) "ľudovka" / "ľudová" napísaná v hlavičke alebo autorovi -> Ľudovky
    if (preg_match('/ľudov|ĺudov|\bludov/iu', $artist . ' ' . $title)) return 9;
    // 3) artist mapping
    $a = mb_strtolower($artist, 'UTF-8');
    if (preg_match('/nedv[eě]d|brontosau|greenhorn/u', $a)) return 2;
    if (preg_match('/nohavica|kryl/u', $a)) return 3;
    if (preg_match('/koll[aá]rovci/u', $a)) return 9; // Kollárovci = ľudovky
    // 3) duchovný obsah (Ježiš/Boh/kráľovstvo...) -> Duchovné, má prednosť pred jazykom
    if (imp_is_spiritual($title . ' ' . $lyrics)) return 8;
    // 4) jazyk
    switch (imp_detect_language($lyrics)) {
        case 'sk': return 5;
        case 'cz': return 4;
        case 'en': return 7;
    }
    return 11; // Ostatné
}

/* ------------------------------------------------------------------ */
/* Difficulty z akordov (easy | normal | hard) — ukladá sa ako tag    */
/* ------------------------------------------------------------------ */

/**
 * Klasifikuj akord podľa hrateľnosti (shape):
 *   'easy'       = otvorené akordy: C D E G A, Am Em Dm (+ ich 7/sus varianty)
 *   'barreMinor' = molové s barré (Bm, F#m, Cm, Gm...) — jeden stačí na 'normal'
 *   'barreMajor' = durové s barré (F, B, H, F#, Bb...) — viac = 'hard'
 */
function imp_classify_chord(string $chord): string {
    if (!preg_match('/^([A-Ha-h])([#b]?)/', $chord, $m)) return 'easy';
    $root = strtoupper($m[1]) . $m[2];
    $rest = substr($chord, strlen($m[0]));
    $isMinor = (bool)preg_match('/^m(?!aj)/', $rest); // "m" ale nie "maj"

    $openMajors = ['C', 'D', 'E', 'G', 'A'];
    $openMinors = ['A', 'E', 'D']; // Am Em Dm

    if ($isMinor) {
        return in_array($root, $openMinors, true) ? 'easy' : 'barreMinor';
    }
    // durový / dominantný
    return in_array($root, $openMajors, true) ? 'easy' : 'barreMajor';
}

/**
 * Odhad obtiažnosti z akordov (chord lines):
 *   hard   = veľa barré durových (F/B/H...) alebo veľa barré spolu
 *   normal = aspoň jeden barré (napr. F#m) alebo veľa rôznych akordov (rýchle zmeny)
 *   easy   = len otvorené akordy, málo z nich
 */
function imp_detect_difficulty(string $content): string {
    $chords = [];
    foreach (explode("\n", $content) as $line) {
        if (!imp_is_chord_line($line)) continue;
        foreach (preg_split('/\s+/u', trim($line)) as $tok) {
            $tok = trim($tok);
            if ($tok !== '' && preg_match('/^[A-Ha-h][#b]?/', $tok)) $chords[$tok] = true;
        }
    }
    $chords = array_keys($chords);
    $distinct = count($chords);
    if ($distinct === 0) return 'normal';

    $barreMajor = 0; $barreMinor = 0;
    foreach ($chords as $c) {
        $cls = imp_classify_chord($c);
        if ($cls === 'barreMajor') $barreMajor++;
        elseif ($cls === 'barreMinor') $barreMinor++;
    }
    $barreTotal = $barreMajor + $barreMinor;

    if ($barreMajor >= 2 || $barreTotal >= 4) return 'hard';
    if ($barreTotal >= 1 || $distinct >= 7)   return 'normal'; // "medium"
    return 'easy';
}
