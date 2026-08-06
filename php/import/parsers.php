<?php
/**
 * parsers.php — orchestrácia importu: zostavenie piesne, uloženie draftu,
 * YouTube hľadanie, web-search zdroja. Nadväzuje na scrapers.php + detector.php.
 */
require_once __DIR__ . '/scrapers.php';
require_once __DIR__ . '/ai_tags.php';

/**
 * Zostav $data pole pre add_song() z výsledku scrapera + heuristík.
 * $overrides umožní prepísať title/artist/category/content/instruments z UI.
 */
/**
 * Očisti názov od zdrojových poznámok v zátvorke (o akordoch/verzii/tab/capo...).
 * Ponechá legitímne podnázvy ("Nádherný (Ve Tvém srdci je soucit)").
 */
function imp_clean_title(string $title): string {
    $t = preg_replace(
        '/\s*\((?:[^)]*(?:akord|spr[aá]vn|verzi|oprav|capo|tab|naozaj|correct|chords?)[^)]*)\)\s*$/iu',
        '', $title);
    return trim($t) !== '' ? trim($t) : trim($title);
}

function imp_build_song(array $scrape, string $sourceUrl = '', array $overrides = []): array {
    $title   = imp_clean_title($overrides['title'] ?? $scrape['title'] ?? '');
    $artist  = $overrides['artist']  ?? $scrape['artist']  ?? '';
    $content = $overrides['content'] ?? $scrape['content'] ?? '';
    $source  = $scrape['source'] ?? imp_detect_source($sourceUrl);

    $category = isset($overrides['category']) && $overrides['category'] !== ''
        ? (int)$overrides['category']
        : imp_detect_category($title, $artist, $content, $source);

    // difficulty (easy|normal|hard) + AI genre/mood tagy (odovzdané z callera cez $overrides['ai_tags']).
    $difficulty = $overrides['difficulty'] ?? imp_detect_difficulty($content);
    $tagList = [$difficulty];
    foreach (($overrides['ai_tags'] ?? []) as $st) {
        $st = trim($st);
        if ($st !== '' && !in_array($st, $tagList, true)) $tagList[] = $st;
    }
    $autoTags = implode(',', array_filter($tagList));

    return [
        'title'        => trim($title) !== '' ? trim($title) : 'Bez názvu',
        'artist'       => trim($artist),
        'category'     => $category,
        'capo'         => 0,
        'capo_alt'     => '',
        'is_public'    => $overrides['is_public'] ?? 1,
        'instruments'  => $overrides['instruments'] ?? 'Guitar',
        'content'      => $content,
        'tags'         => $overrides['tags'] ?? $autoTags,
        'is_nsfw'      => 0,
        'youtube_link' => $overrides['youtube_link'] ?? '',
    ];
}

/**
 * Ulož pieseň ako draft + zapíš source_url + import_log.
 * Vráti ['ok'=>bool, 'song_id'=>int|null, 'error'=>string].
 */
function imp_save_draft(mysqli $mysqli, array $data, string $sourceType,
                        string $sourceUrl, string $rawContent, float $confidence,
                        int $createdBy, int $isUnchecked = 1, array $aiMeta = []): array {
    $data['source_url'] = $sourceUrl;
    $data['is_unchecked'] = $isUnchecked;
    $res = add_song($mysqli, $data); // int id | error string
    if (!is_int($res)) {
        return ['ok' => false, 'song_id' => null, 'error' => (string)$res];
    }
    $songId = $res;

    // import_log + AI metriky
    if ($stmt = $mysqli->prepare(
        "INSERT INTO import_log
            (song_id, source_type, source_url, raw_content, confidence, status, created_by, songs_count,
             ai_model, ai_ms, ai_prompt_tokens, ai_completion_tokens, ai_error)
         VALUES (?, ?, ?, ?, ?, 'draft', ?, 1, ?, ?, ?, ?, ?)")) {
        $conf  = round($confidence, 2);
        $model = (string)($aiMeta['model'] ?? '');
        $ms    = (int)($aiMeta['ms'] ?? 0);
        $ptok  = (int)($aiMeta['prompt_tokens'] ?? 0);
        $ctok  = (int)($aiMeta['completion_tokens'] ?? 0);
        $aerr  = (string)($aiMeta['error'] ?? '');
        $stmt->bind_param('isssdisiiis', $songId, $sourceType, $sourceUrl, $rawContent, $conf,
                          $createdBy, $model, $ms, $ptok, $ctok, $aerr);
        $stmt->execute();
        $stmt->close();
    }

    // recent activity (aby bolo vidno kto čo importoval)
    $u = $_SESSION['user'] ?? 'system';
    $desc = "Imported '" . ($data['title'] ?? '?') . "'"
          . (!empty($data['artist']) ? " by " . $data['artist'] : '');
    add_activity($mysqli, $u, 'import', $desc, (int)($data['is_public'] ?? 1));

    return ['ok' => true, 'song_id' => $songId, 'error' => ''];
}

/** Nájdi existujúci import podľa source_url (na preskočenie duplikátov). Vráti [id,title] alebo null. */
function imp_find_by_source_url(mysqli $mysqli, string $url): ?array {
    if ($url === '') return null;
    if ($stmt = $mysqli->prepare("SELECT id, title FROM songs WHERE source_url = ? LIMIT 1")) {
        $stmt->bind_param('s', $url);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($r) return ['id' => (int)$r['id'], 'title' => $r['title']];
    }
    return null;
}

/* ================================================================== */
/* YouTube link (DuckDuckGo HTML scrape)                              */
/* ================================================================== */

function imp_youtube_search(string $title, string $artist): string {
    $q = trim("$artist $title");
    if ($q === '') return '';
    // priamy scrape YouTube results (ytInitialData) — spoľahlivejšie než DDG
    $res = imp_fetch_url('https://www.youtube.com/results?search_query=' . rawurlencode($q), 15);
    if ($res['body'] !== '' && preg_match('/"videoId":"([\w-]{11})"/', $res['body'], $m)) {
        return 'https://www.youtube.com/watch?v=' . $m[1];
    }
    return '';
}

/* ================================================================== */
/* Web-search zdroja (Internet tab) — nájdi URL na podporovanej doméne */
/* ================================================================== */

/**
 * Nájdi URL piesne na podporovaných zdrojoch.
 * Primárne natívny supermusic search (veľký katalóg, spoľahlivý), DDG ako fallback.
 */
function imp_web_search_song(string $title, string $artist): array {
    // 1) supermusic natívny search (title váži viac než artist)
    $found = imp_supermusic_search($title, $artist);
    if (!empty($found)) return $found;
    // 2) DDG fallback (často blokovaný, ale skús)
    return imp_ddg_search_song($title, $artist);
}

/**
 * Dohľadaj chýbajúceho autora podľa názvu (reuse internet módu: search -> scrape -> artist).
 * Vráti '' ak sa nič spoľahlivé nenašlo ALEBO ide o ľudovku (ľudovka nemá autora).
 * Volaj LEN keď je autor prázdny — existujúceho autora nikdy neprepisuj.
 */
function imp_enrich_artist(string $title): string {
    $title = trim($title);
    if ($title === '') return '';
    $userTok = imp_tokens($title);
    if (empty($userTok)) return '';
    foreach (array_slice(imp_web_search_song($title, ''), 0, 3) as $u) {
        $s = imp_scrape_url($u);
        if (empty($s['ok'])) continue;
        // názov musí sedieť, inak by sme prilepili autora úplne inej piesne
        $stTok = imp_tokens($s['title'] ?? '');
        if (count(array_intersect($userTok, $stTok)) / count($userTok) < 0.6) continue;
        $a = trim($s['artist'] ?? '');
        if ($a === '') continue;
        if (preg_match('/ľudov|lidov|tradi|folk|anonym/iu', $a)) return ''; // ľudovka = bez autora
        return $a;
    }
    return '';
}

/**
 * Natívny supermusic search -> zoznam piesen URL, zoradený podľa zhody anchor textu.
 * Skóre = 2×zhoda_title + 1×zhoda_artist. Rieši cookie challenge cez imp_fetch_url.
 */
function imp_supermusic_search(string $title, string $artist = ''): array {
    $query = trim("$title $artist");
    if ($query === '') return [];
    $res = imp_fetch_url('https://www.supermusic.cz/najdi.php?hladane=' . rawurlencode($query), 15);
    if ($res['body'] === '') return [];

    $tTokens = imp_tokens($title);
    $aTokens = imp_tokens($artist);
    $cands = [];
    if (preg_match_all('/<a[^>]*href="[^"]*piesen\.php\?id=(\d+)[^"]*"[^>]*>(.*?)<\/a>/is',
                       $res['body'], $m, PREG_SET_ORDER)) {
        $seen = [];
        foreach ($m as $a) {
            $id = $a[1];
            if (isset($seen[$id])) continue;
            $seen[$id] = true;
            $anchor = imp_tokens(trim(preg_replace('/\s+/', ' ', strip_tags($a[2]))));
            $score = 2 * count(array_intersect($tTokens, $anchor))
                   + 1 * count(array_intersect($aTokens, $anchor));
            $cands[] = ['id' => $id, 'score' => $score];
        }
    }
    usort($cands, fn($x, $y) => $y['score'] <=> $x['score']); // stabilné (PHP 8+)
    $out = [];
    foreach (array_slice($cands, 0, 6) as $c) {
        $out[] = 'https://www.supermusic.cz/piesen.php?idpiesne=' . $c['id'] . '&sh=1';
    }
    return $out;
}

/** Tokeny bez diakritiky pre porovnanie. */
function imp_tokens(string $s): array {
    $s = mb_strtolower($s, 'UTF-8');
    // odstráň diakritiku (translit)
    $s = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
    $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
    $toks = array_filter(explode(' ', $s), fn($t) => mb_strlen($t) >= 2);
    return array_values(array_unique($toks));
}

/** DuckDuckGo fallback search naprieč podporovanými doménami. */
function imp_ddg_search_song(string $title, string $artist): array {
    $domains = ['supermusic.cz', 'supermusic.sk', 'brnkni.cz', '4334.sk',
                'zpevnik.proscholy.cz', 'mladeznickyspevnik.sk'];
    $q = trim("$title $artist") . ' akordy';
    $url = 'https://html.duckduckgo.com/html/?q=' . rawurlencode($q);
    $res = imp_fetch_url($url, 15);
    $found = [];
    if ($res['body'] !== '' && preg_match_all('#/l/\?uddg=([^"&]+)#', $res['body'], $m)) {
        foreach ($m[1] as $enc) {
            $link = urldecode($enc);
            foreach ($domains as $d) {
                if (stripos($link, $d) !== false && !in_array($link, $found, true)) {
                    // odfiltruj listing/kategórie – chceme detail piesne
                    if (imp_looks_like_song_url($link, $d)) $found[] = $link;
                }
            }
        }
    }
    // zoradenie podľa preferencie domén
    usort($found, function ($a, $b) use ($domains) {
        $ia = PHP_INT_MAX; $ib = PHP_INT_MAX;
        foreach ($domains as $i => $d) {
            if (stripos($a, $d) !== false) $ia = min($ia, $i);
            if (stripos($b, $d) !== false) $ib = min($ib, $i);
        }
        return $ia <=> $ib;
    });
    return $found;
}

/* ================================================================== */
/* PDF spevníky — extrakcia cez python/PyMuPDF (pdf_songs.py)         */
/* ================================================================== */

/**
 * Extrahuj piesne z PDF spevníka (notový aj textový formát).
 * Vráti ['ok'=>bool, 'mode'=>str, 'songs'=>[{title,author,category_hint,body}], 'error'=>str].
 * body obsahuje inline chord markery \x01AKORD\x01 (pre imp_marked_to_ir).
 * Vyžaduje python + pymupdf na serveri.
 */
function imp_pdf_extract(string $pdfPath): array {
    $script = __DIR__ . '/pdf_songs.py';
    if (!is_file($script)) return ['ok' => false, 'error' => 'pdf_songs.py chýba'];
    $cmd = 'python ' . escapeshellarg($script) . ' ' . escapeshellarg($pdfPath) . ' 2>&1';
    $out = shell_exec($cmd);
    if ($out === null || trim((string)$out) === '') {
        return ['ok' => false, 'error' => 'python sa nepodarilo spustiť (je nainštalovaný python + pymupdf?)'];
    }
    $data = json_decode($out, true);
    if (!is_array($data)) {
        return ['ok' => false, 'error' => 'PDF extraktor nevrátil JSON: ' . mb_substr(trim($out), 0, 150)];
    }
    if (empty($data['ok'])) {
        return ['ok' => false, 'error' => $data['error'] ?? 'PDF extrakcia zlyhala'];
    }
    return $data;
}

/** Kategória + tag hint z hlavičky PDF spevníka ("Adventné", "Vianočné"...). */
function imp_pdf_category_hint(string $hint): array {
    $h = mb_strtolower($hint, 'UTF-8');
    $tag = '';
    if (strpos($h, 'advent') !== false)  $tag = 'adventné';
    if (strpos($h, 'vianoc') !== false)  $tag = 'vianočná';
    // hlavičky ako Adventné/Vianočné/Pôstne/Introit... = duchovný spevník
    $cat = ($hint !== '') ? 8 : 0;
    return [$cat, $tag];
}

/* ================================================================== */
/* Upload — rozdelenie na piesne + hlavička                           */
/* ================================================================== */

/**
 * Rozdeľ raw na jednotlivé piesne. Hranice:
 *  - explicitný separátor --- / === (prednosť)
 *  - riadok "# Titul" (markdown nadpis) = začiatok novej piesne
 *  - run >=2 junk riadkov (URL/uploader smeti) = koniec piesne
 *  - 3+ prázdnych riadkov
 */
function imp_split_songs(string $raw): array {
    $raw = str_replace(["\r\n", "\r"], "\n", $raw);
    if (preg_match('/^\s*[-=*_]{3,}\s*$/m', $raw)) {
        $parts = preg_split('/^\s*[-=*_]{3,}\s*$/m', $raw);
    } else {
        // riadkový prechod: junk riadky sa preskakujú (separátor), "# Titul" začne novú pieseň
        $lines = explode("\n", $raw);
        $segs = []; $cur = []; $pendingTitle = ''; $junkRun = 0;
        $flush = function () use (&$segs, &$cur, &$pendingTitle) {
            $body = trim(implode("\n", $cur));
            $cur = [];
            if ($body === '') return; // prázdny segment: pendingTitle prežije na ďalšiu pieseň
            // segment bez hudobnej podstaty (žiadny chord riadok, <2 riadky) = zvyšok smetí -> zahoď
            $ls = array_filter(array_map('trim', explode("\n", $body)));
            $hasChord = false;
            foreach ($ls as $l) { if (imp_is_chord_line($l)) { $hasChord = true; break; } }
            if (!$hasChord && count($ls) < 2) return; // title prežije na ďalšiu pieseň
            $segs[] = ($pendingTitle !== '' ? "# $pendingTitle\n" : '') . $body;
            $pendingTitle = '';
        };
        foreach ($lines as $line) {
            if (imp_is_junk_line($line)) {
                $junkRun++;
                if ($junkRun === 2) $flush(); // junk blok = hranica piesne
                continue;                      // junk sa do obsahu neberie
            }
            $junkRun = 0;
            if (preg_match('/^\s*#\s+(\S.*)$/', $line, $m)) {
                $flush();
                $pendingTitle = trim($m[1]); // titul patrí NASLEDUJÚCEJ piesni
                continue;
            }
            $cur[] = $line;
        }
        $flush();
        // fallback: 3+ prázdnych riadkov vnútri segmentov
        $parts = [];
        foreach ($segs as $p) {
            foreach (preg_split('/\n[ \t]*\n[ \t]*\n[ \t]*\n+/', $p) as $sub) $parts[] = $sub;
        }
    }
    $parts = array_map('trim', $parts);
    // zahoď segmenty bez hudobného obsahu (žiadny chord riadok a menej než 2 neprázdne riadky)
    $parts = array_filter($parts, function ($p) {
        if ($p === '') return false;
        $ls = array_filter(array_map('trim', explode("\n", $p)));
        $hasChord = false;
        foreach ($ls as $l) { if (imp_is_chord_line($l)) { $hasChord = true; break; } }
        return $hasChord || count($ls) >= 2;
    });
    return array_values($parts);
}

/** Riadok je hudobná metadata (capo/tónina/tempo), nie názov ani autor. */
function imp_is_meta_line(string $line): bool {
    $t = trim($line);
    if ($t === '') return false;
    if (preg_match('/^(capo|kapo)\b/iu', $t) || preg_match('/\bcapo\s+([ivxlcdm]+|\d+)\b/iu', $t)) return true;
    return (bool)preg_match('/^(t[oó]nina|key|ladenie|tuning|tempo|rytmus|rhythm|bpm|orig(in[aá]l)?|kapod[aá]ster)\b/iu', $t);
}

/**
 * Nesmie sa uznať za autora: capo/tónina, sekčný riadok ("intro: Hm A G F#m"),
 * akordy s čiarkami ("A, Hm, E, A"), napísaný tag alebo názov kategórie ("ľudovka").
 */
function imp_bad_artist_line(string $line, array $knownTags = []): bool {
    $t = trim($line);
    if ($t === '' || imp_is_meta_line($t) || imp_line_starts_section($t)) return true;
    // akordový riadok aj keď je oddelený čiarkami/pomlčkami
    if (imp_is_chord_line(trim(preg_replace('/[,\-–—]+/u', ' ', $t)))) return true;
    if (in_array(mb_strtolower($t, 'UTF-8'), $knownTags, true)) return true;
    return (bool)preg_match('/^(ľudov|ludov|folk|tradi|duchovn|vianočn|adventn)/iu', $t);
}

/** Vytiahni title/artist z hlavičky chunku, vráť ['title','artist','body']. */
function imp_extract_upload_meta(string $chunk, array $knownTags = []): array {
    $lines = explode("\n", $chunk);
    $title = ''; $artist = ''; $bodyStart = 0;
    while ($bodyStart < count($lines) && trim($lines[$bodyStart]) === '') $bodyStart++;
    // capo/tónina pred názvom preskoč — nie je to názov ani autor
    while ($bodyStart < count($lines) && imp_is_meta_line($lines[$bodyStart])) $bodyStart++;
    while ($bodyStart < count($lines) && trim($lines[$bodyStart]) === '') $bodyStart++;
    // title = prvý riadok len ak NIE je akord, sekčný marker (vrátane Predohra/Interlude...) ani bracket
    $firstLine = $bodyStart < count($lines) ? trim($lines[$bodyStart]) : '';
    $isSection = preg_match('/^(\[|\d+\.|R:|®|Ref|Verse|Chorus|Bridge|Intro)/iu', $firstLine)
              || imp_is_pure_marker($firstLine);
    if ($firstLine !== '' && !imp_is_chord_line($lines[$bodyStart]) && !$isSection) {
        $first = preg_replace('/^#\s+/', '', $firstLine); // "# Titul" -> "Titul"
        if (mb_strpos($first, ' - ') !== false) {
            [$title, $artist] = imp_split_title_artist($first);
        } else {
            $title = $first;
        }
        $bodyStart++;
        if ($artist === '' && $bodyStart < count($lines)) {
            $second = trim($lines[$bodyStart]);
            if ($second !== '' && mb_strlen($second) < 40
                && !imp_bad_artist_line($second, $knownTags)
                && !preg_match('/^(\d+\.|R:|®|\[)/u', $second)
                && !imp_is_pure_marker($second)) {
                $artist = $second;
                $bodyStart++;
            }
        }
    }
    $body = trim(implode("\n", array_slice($lines, $bodyStart)));
    // Fallback názvu: pieseň bez hlavičky -> prvý spievaný riadok. Nikdy nezahoď pieseň.
    if ($title === '') {
        foreach (explode("\n", $body) as $l) {
            $t = trim($l);
            if ($t === '' || imp_is_chord_line($l) || imp_is_pure_marker($t) || imp_is_meta_line($t)) continue;
            $title = preg_replace('/^\s*(\d+\.|R:|®:?)\s*/u', '', $t); // marker prefix preč
            break;
        }
    }
    return ['title' => trim($title), 'artist' => trim($artist), 'body' => $body];
}

/** Vyzerá URL ako detail piesne (nie listing)? */
function imp_looks_like_song_url(string $url, string $domain): bool {
    $path = parse_url($url, PHP_URL_PATH) ?? '';
    if (stripos($domain, 'supermusic') !== false) return stripos($url, 'idpiesne=') !== false || stripos($path, 'piesen') !== false;
    if (stripos($domain, 'brnkni') !== false)     return preg_match('#^/[a-z]/[^/]+/[^/]+/#i', $path);
    if (stripos($domain, 'pisnicky') !== false)    return preg_match('#^/[^/]+/[^/]+/?$#', $path);
    if (stripos($domain, '4334') !== false)        return preg_match('#^/piesne/[^/]+/?$#', $path);
    if (stripos($domain, 'proscholy') !== false)   return stripos($path, '/pisen/') !== false;
    if (stripos($domain, 'mladeznicky') !== false) return stripos($url, 'songId=') !== false;
    return true;
}
