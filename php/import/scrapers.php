<?php
/**
 * scrapers.php — per-zdroj scrapery (deterministické, podľa plan/import-scraper-research.md).
 * Každý scraper: HTML -> ['title','artist','ir'=>[lines],'confidence','source'].
 * IR sa renderuje cez imp_render_ir() (detector.php).
 */
require_once __DIR__ . '/detector.php';
require_once __DIR__ . '/formatter.php';

/* ================================================================== */
/* HTTP fetch                                                         */
/* ================================================================== */

/** Stiahni URL. Rieši supermusic _sm_verified cookie challenge. */
function imp_fetch_url(string $url, int $timeout = 25): array {
    $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
        . '(KHTML, like Gecko) Chrome/120 Safari/537.36';

    $do = function (string $url, string $cookie = '') use ($ua, $timeout) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT      => $ua,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_HTTPHEADER     => ['Accept-Language: sk,cs;q=0.9,en;q=0.8'],
        ]);
        if ($cookie !== '') curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        $final = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);
        return ['body' => (string)$body, 'code' => $code, 'err' => $err, 'url' => $final];
    };

    $r = $do($url);
    // supermusic JS cookie challenge — retry s cookie (nezávisle od veľkosti stránky)
    if ($r['body'] !== '' && strpos($r['body'], '_sm_verified=') !== false
        && preg_match('/_sm_verified=[a-f0-9]+/', $r['body'], $m)) {
        $r2 = $do($url, $m[0]);
        if ($r2['body'] !== '') $r = $r2;
    }
    return $r;
}

/* ================================================================== */
/* DOM utilities                                                      */
/* ================================================================== */

function imp_load_dom(string $html): DOMDocument {
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    // vynúť UTF-8
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    return $dom;
}

/** Prvý node podľa presného class tokenu. */
function imp_xp_class(DOMXPath $xp, string $class, ?DOMNode $ctx = null): ?DOMNode {
    $q = ".//*[contains(concat(' ',normalize-space(@class),' '),' $class ')]";
    $nodes = $ctx ? $xp->query($q, $ctx) : $xp->query('/' . $q);
    return ($nodes && $nodes->length) ? $nodes->item(0) : null;
}

function imp_node_text(?DOMNode $n): string {
    return $n ? trim(preg_replace('/\s+/u', ' ', $n->textContent)) : '';
}

/** innerHTML nodu. */
function imp_inner_html(DOMNode $node): string {
    $html = '';
    foreach ($node->childNodes as $child) {
        $html .= $node->ownerDocument->saveHTML($child);
    }
    return $html;
}

/* ================================================================== */
/* Marked-HTML -> IR  (pre inline-chord zdroje)                       */
/* ================================================================== */

/**
 * $marked: text kde akordy = \x01CHORD\x01, riadky oddelené \n.
 * Vráti IR pole riadkov.
 */
function imp_marked_to_ir(string $marked): array {
    $marked = html_entity_decode($marked, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $marked = str_replace("\xC2\xA0", ' ', $marked); // nbsp -> space
    $marked = str_replace("\t", '', $marked);        // tab = junk indent zo source spanov
    $lines = explode("\n", $marked);
    $ir = [];
    foreach ($lines as $line) {
        // rozdeľ na kúsky podľa sentinelu
        $parts = explode("\x01", $line);
        // parts: [text, chord, text, chord, text, ...]
        $segments = [];
        $chords = [];
        $leading = array_shift($parts);
        if ($leading !== null && $leading !== '') {
            $segments[] = ['chord' => '', 'text' => $leading];
        }
        // teraz páry (chord, text)
        for ($i = 0; $i < count($parts); $i += 2) {
            $chord = $parts[$i] ?? '';
            $text  = $parts[$i + 1] ?? '';
            $segments[] = ['chord' => $chord, 'text' => $text];
            if (trim($chord) !== '') $chords[] = $chord;
        }
        // vyhodnoť typ riadku
        $lyricText = '';
        foreach ($segments as $s) $lyricText .= $s['text'];
        if (trim($lyricText) === '' && empty($chords)) {
            $ir[] = ['type' => 'blank'];
        } elseif (preg_match('/^[\s,._\-|]*$/u', $lyricText) && !empty($chords)) {
            // riadok len akordy (intro/medzihra)
            $ir[] = ['type' => 'chordonly', 'chords' => $chords];
        } else {
            $ir[] = ['type' => 'lyric', 'segments' => $segments];
        }
    }
    return $ir;
}

/** Vytiahni title/artist z <title>. */
function imp_title_from_html(string $html): string {
    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
        return trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
    return '';
}

/* ================================================================== */
/* Zdroj: supermusic.cz                                               */
/* ================================================================== */

function imp_scrape_supermusic(string $html): array {
    $dom = imp_load_dom($html);
    $xp  = new DOMXPath($dom);
    $node = $xp->query("//*[@id='chordText']")->item(0);
    $ir = [];
    $hasChordMarkup = false;
    if ($node) {
        $inner = imp_inner_html($node);
        $inner = preg_replace('/<br\s*\/?>/i', "\n", $inner);
        // <a class="chord" data-original="X">..</a> -> \x01X\x01
        $inner = preg_replace(
            '/<a[^>]*\bdata-original="([^"]*)"[^>]*>.*?<\/a>/is',
            "\x01\$1\x01",
            $inner, -1, $cnt
        );
        $hasChordMarkup = ($cnt > 0);
        $inner = preg_replace('/<[^>]+>/', '', $inner); // zvyšné tagy (<sup> atď.)
        $ir = imp_marked_to_ir($inner);
    }
    // bez chord markupu = holý inline text (zlepené akordy) -> nespoľahlivé, nízka confidence
    $conf = !$node ? 0.2 : ($hasChordMarkup ? 0.9 : 0.35);
    // title/artist z <title>: "Song – Artist | Akordy ..."
    $t = imp_title_from_html($html);
    $t = preg_replace('/\s*\|.*$/su', '', $t); // odstráň všetko za "|"
    [$title, $artist] = imp_split_title_artist($t);
    return ['title' => $title, 'artist' => $artist, 'ir' => $ir,
            'confidence' => $conf, 'source' => 'supermusic.cz'];
}

/* ================================================================== */
/* Zdroj: brnkni.cz                                                   */
/* ================================================================== */

function imp_scrape_brnkni(string $html): array {
    $dom = imp_load_dom($html);
    $xp  = new DOMXPath($dom);
    $node = imp_xp_class($xp, 'chordpad');
    $ir = [];
    if ($node) {
        $inner = imp_inner_html($node);
        $inner = preg_replace('/<br\s*\/?>/i', "\n", $inner);
        $inner = preg_replace('/<span[^>]*>([^<]*)<\/span>/i', "\x01\$1\x01", $inner);
        $inner = preg_replace('/<[^>]+>/', '', $inner);
        $ir = imp_marked_to_ir($inner);
    }
    $t = imp_title_from_html($html);
    // "Artist - Song akordy a text písně - Brnkni.cz"
    $t = preg_replace('/\s+akordy\b.*$/iu', '', $t);
    $t = preg_replace('/\s*-\s*Brnkni.*$/iu', '', $t);
    [$title, $artist] = imp_split_title_artist($t, true); // brnkni má artist prvý
    return ['title' => $title, 'artist' => $artist, 'ir' => $ir,
            'confidence' => $node ? 0.85 : 0.2, 'source' => 'brnkni.cz'];
}

/* ================================================================== */
/* Zdroj: mladeznickyspevnik.sk                                       */
/* ================================================================== */

function imp_scrape_mladeznicky(string $html): array {
    $dom = imp_load_dom($html);
    $xp  = new DOMXPath($dom);
    $node = imp_xp_class($xp, 'accord');
    $ir = [];
    if ($node) {
        $inner = imp_inner_html($node);
        $inner = preg_replace('/<br\s*\/?>/i', "\n", $inner);
        // <strong><span>[</span>C<sup></sup><span>]</span></strong> -> \x01C\x01
        $inner = preg_replace(
            '/<strong>\s*<span>\[<\/span>([^<]*)<sup>.*?<\/strong>/is',
            "\x01\$1\x01",
            $inner
        );
        $inner = preg_replace('/<[^>]+>/', '', $inner);
        $ir = imp_marked_to_ir($inner);
    }
    // title = h1#category, artist z "Autor:"
    $title = imp_node_text($xp->query("//h1[@id='category']")->item(0));
    if ($title === '') $title = imp_node_text($xp->query('//h1')->item(0));
    $artist = '';
    foreach ($xp->query("//*[contains(@class,'post')]") as $p) {
        if (preg_match('/Autor:\s*(.+)$/u', $p->textContent, $m)) {
            // ber len meno pred " - " / "(" (odrež popis)
            $artist = trim(preg_split('/\s+-\s+|\(/u', trim($m[1]))[0]);
            break;
        }
    }
    return ['title' => $title, 'artist' => $artist, 'ir' => $ir,
            'confidence' => $node ? 0.85 : 0.2, 'source' => 'mladeznickyspevnik.sk'];
}

/* ================================================================== */
/* Zdroj: 4334.sk (ChordWP — DOM)                                     */
/* ================================================================== */

function imp_scrape_4334(string $html): array {
    $dom = imp_load_dom($html);
    $xp  = new DOMXPath($dom);
    $cont = imp_xp_class($xp, 'chordwp-container');
    $ir = [];
    if ($cont) {
        // rows aj comments v poradí
        $q = ".//div[contains(concat(' ',normalize-space(@class),' '),' chwp-lyrics-row ')]"
           . " | .//span[contains(concat(' ',normalize-space(@class),' '),' chwp-comment ')]";
        foreach ($xp->query($q, $cont) as $row) {
            $cls = $row->getAttribute('class');
            if (strpos($cls, 'chwp-comment') !== false) {
                $txt = trim($row->textContent);
                if ($txt === '') { continue; }
                // " 1:" -> "1.", "R:" ostáva
                $txt = preg_replace('/^(\d+):$/', '$1.', $txt);
                $ir[] = ['type' => 'marker', 'text' => $txt];
                continue;
            }
            $noLyrics = strpos($cls, 'chwp-no-lyrics-in-line') !== false;
            $segments = [];
            $chords = [];
            $wq = ".//span[contains(concat(' ',normalize-space(@class),' '),' chwp-chord-lyrics-wrapper ')]";
            foreach ($xp->query($wq, $row) as $wrap) {
                $cq = ".//span[contains(concat(' ',normalize-space(@class),' '),' chwp-chord ')]";
                $chordParts = [];
                foreach ($xp->query($cq, $wrap) as $c) {
                    if (strpos($c->getAttribute('class'), 'chwp-empty-chord') !== false) continue;
                    $ct = trim(str_replace("\xC2\xA0", '', $c->textContent));
                    if ($ct !== '') $chordParts[] = $ct;
                }
                $lyr = imp_xp_class($xp, 'chwp-words', $wrap);
                $text = $lyr ? str_replace("\xC2\xA0", ' ', $lyr->textContent) : '';
                $chord = implode(' ', $chordParts);
                $segments[] = ['chord' => $chord, 'text' => $text];
                foreach ($chordParts as $cp) $chords[] = $cp;
            }
            if ($noLyrics) {
                if (!empty($chords)) $ir[] = ['type' => 'chordonly', 'chords' => $chords];
            } else {
                $lyricText = '';
                foreach ($segments as $s) $lyricText .= $s['text'];
                if (trim($lyricText) === '' && empty($chords)) $ir[] = ['type' => 'blank'];
                else $ir[] = ['type' => 'lyric', 'segments' => $segments];
            }
        }
    }
    // title/artist
    $title = imp_node_text($xp->query('//h1')->item(0));
    $artist = '';
    $al = imp_xp_class($xp, 'song-album-title');
    if ($al) $artist = imp_node_text($al);
    return ['title' => $title, 'artist' => $artist, 'ir' => $ir,
            'confidence' => $cont ? 0.9 : 0.2, 'source' => '4334.sk'];
}

/* ================================================================== */
/* Zdroj: zpevnik.proscholy.cz (SSR Vue — DOM)                        */
/* ================================================================== */

function imp_scrape_proscholy(string $html): array {
    $dom = imp_load_dom($html);
    $xp  = new DOMXPath($dom);
    $cont = imp_xp_class($xp, 'song-lyrics');
    $ir = [];
    if ($cont) {
        $partQ = ".//*[contains(concat(' ',normalize-space(@class),' '),' song-part ')]";
        foreach ($xp->query($partQ, $cont) as $part) {
            $isRefrain = strpos($part->getAttribute('class'), 'song-part-refrain') !== false;
            $emittedRefrain = false;
            $lineQ = ".//*[contains(concat(' ',normalize-space(@class),' '),' song-line ')]";
            foreach ($xp->query($lineQ, $part) as $line) {
                $segments = [];
                // part-tag (napr. "1." / "R:")
                $tag = imp_xp_class($xp, 'song-part-tag', $line);
                $tagText = $tag ? trim($tag->textContent) : '';
                if ($isRefrain && !$emittedRefrain) {
                    $ir[] = ['type' => 'marker', 'text' => '®:'];
                    $emittedRefrain = true;
                } elseif ($tagText !== '') {
                    $segments[] = ['chord' => '', 'text' => $tagText . ' '];
                }
                // chord segmenty
                $chQ = ".//*[contains(concat(' ',normalize-space(@class),' '),' chord ')]";
                foreach ($xp->query($chQ, $line) as $ch) {
                    // preskoč vnorené (chord-base atď.) — berieme len span.chord (priamy)
                    $ccls = ' ' . $ch->getAttribute('class') . ' ';
                    if (strpos($ccls, ' chord ') === false) continue;
                    $base = imp_xp_class($xp, 'chord-base', $ch);
                    $var  = imp_xp_class($xp, 'chord-variant', $ch);
                    $ext  = imp_xp_class($xp, 'chord-extension', $ch);
                    $bass = imp_xp_class($xp, 'chord-bass', $ch);
                    $ctext= imp_xp_class($xp, 'chord-text', $ch);
                    $chord = trim(($base?$base->textContent:'') . ($var?$var->textContent:'')
                                 . ($ext?$ext->textContent:''));
                    if ($bass && trim($bass->textContent) !== '') {
                        $chord .= '/' . trim(str_replace('/', '', $bass->textContent));
                    }
                    $chord = trim(str_replace("\xE2\x80\x8B", '', $chord)); // zero-width space
                    $text = $ctext ? $ctext->textContent : '';
                    $segments[] = ['chord' => $chord, 'text' => $text];
                }
                if (empty($segments)) { $ir[] = ['type' => 'blank']; continue; }
                $ir[] = ['type' => 'lyric', 'segments' => $segments];
            }
            $ir[] = ['type' => 'blank'];
        }
    }
    // title z <title> (spoľahlivejšie než Vue span), artist voliteľný
    $title = imp_title_from_html($html);
    $title = preg_replace('/\s*[–\-]\s*(ProScholy|Zpěvník).*$/iu', '', $title);
    $title = trim($title);
    if ($title === '') $title = imp_node_text($xp->query('//h1')->item(0));
    $artist = imp_node_text($xp->query("//*[contains(@class,'song-authors')]")->item(0));
    return ['title' => $title, 'artist' => $artist, 'ir' => $ir,
            'confidence' => $cont ? 0.9 : 0.2, 'source' => 'zpevnik.proscholy.cz'];
}

/* ================================================================== */
/* Zdroj: pisnicky-akordy.cz                                          */
/* ================================================================== */

function imp_scrape_pisnicky(string $html): array {
    $dom = imp_load_dom($html);
    $xp  = new DOMXPath($dom);
    $ir = [];
    // obsah v <pre>: <el class='aline'><span class='akord'>X</span> ...</el>lyric
    if (preg_match('/<pre[^>]*>(.*?)<\/pre>/is', $html, $m)) {
        $inner = $m[1];
        $inner = preg_replace('/<br\s*\/?>/i', "\n", $inner);
        // akord span -> holý akord (medzery okolo zostanú -> zachová stĺpce)
        $inner = preg_replace('/<span[^>]*class=["\']?akord["\']?[^>]*>(.*?)<\/span>/is', '$1', $inner);
        $inner = preg_replace('/<[^>]+>/', '', $inner); // <el>, zvyšné tagy
        $inner = html_entity_decode($inner, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $ir = imp_plaintext_to_ir($inner);
    }
    // title/artist z <title> "Artist - Song - akordy a text písně" (artist prvý)
    $t = imp_title_from_html($html);
    $t = preg_replace('/\s*-\s*akordy a text.*$/iu', '', $t);
    [$title, $artist] = imp_split_title_artist($t, true); // vráti [title, artist]
    // fallback: song h1 (ten s odkazom na pieseň), nie site-name h1
    if ($title === '') {
        foreach ($xp->query('//h1') as $h) {
            $txt = imp_node_text($h);
            if ($txt !== '' && stripos($txt, 'akordy') === false) { $title = $txt; break; }
        }
    }
    return ['title' => $title, 'artist' => $artist, 'ir' => $ir,
            'confidence' => !empty($ir) ? 0.9 : 0.2, 'source' => 'pisnicky-akordy.cz'];
}

/* ================================================================== */
/* Generic fallback                                                   */
/* ================================================================== */

function imp_scrape_generic(string $html): array {
    // skús <pre> bloky
    $ir = [];
    $title = imp_title_from_html($html);
    if (preg_match('/<pre[^>]*>(.*?)<\/pre>/is', $html, $m)) {
        $txt = html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $ir = imp_plaintext_to_ir($txt);
    } else {
        // odstráň script/style, vyber najdlhší text blok
        $clean = preg_replace('#<(script|style)[^>]*>.*?</\1>#is', '', $html);
        $clean = preg_replace('/<br\s*\/?>/i', "\n", $clean);
        $clean = preg_replace('#</(p|div|li|h[1-6])>#i', "\n", $clean);
        $txt = html_entity_decode(strip_tags($clean), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $ir = imp_plaintext_to_ir($txt);
    }
    [$t, $a] = imp_split_title_artist(preg_replace('/\s*[|\-].*$/', '', $title));
    return ['title' => $t, 'artist' => $a, 'ir' => $ir,
            'confidence' => 0.4, 'source' => 'generic'];
}

/* ================================================================== */
/* Plain text (upload/paste) -> IR                                    */
/* ================================================================== */

/**
 * Rozparsuj plain text ktorý JE už v 2-line formáte (chord line nad lyric line)
 * ALEBO obsahuje inline [chord]. Vráti IR (bez straty informácií).
 * Pre 2-line: chord riadok si necháme ako 'chordonly' ak nasleduje lyric? Nie —
 * radšej necháme text tak ako je (už je v cieľovom formáte) cez 'raw' riadky.
 */
function imp_plaintext_to_ir(string $text): array {
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = str_replace("\t", '    ', $text); // tab -> 4 medzery (zachovaj stĺpce)

    // inline [chord]?
    if (preg_match('/\[[A-Ha-h][#b]?[^\]]{0,6}\]/u', $text)) {
        $lines = explode("\n", $text);
        $ir = [];
        foreach ($lines as $line) {
            if (trim($line) === '') { $ir[] = ['type' => 'blank']; continue; }
            $marked = preg_replace('/\[([^\]]{1,10})\]/u', "\x01\$1\x01", $line);
            $ir = array_merge($ir, imp_marked_to_ir($marked));
        }
        return $ir;
    }

    // 2-line formát (chord riadok nad text) — páruj a normalizuj
    $lines = explode("\n", $text);
    $ir = [];
    $n = count($lines);
    for ($i = 0; $i < $n; $i++) {
        $line = $lines[$i];
        if (trim($line) === '') { $ir[] = ['type' => 'blank']; continue; }

        if (imp_is_chord_line($line)) {
            $next = $lines[$i + 1] ?? '';
            // POZOR: iba ČISTÝ marker riadok ("R:", "1.") smie rozbiť pár chord+lyric.
            // Inline marker ("R: Horí ti maštaľ") je lyric riadok — akord nad ním k nemu patrí,
            // inak sa z neho stane chord-only a render stratí zarovnanie akordov.
            if (trim($next) !== '' && !imp_is_chord_line($next) && !imp_is_pure_marker($next)) {
                // chord line + lyric line -> segmenty (normalizuje akordy)
                $ir[] = ['type' => 'lyric', 'segments' => imp_two_line_to_segments($line, $next)];
                $i++; // skonzumuj lyric riadok
            } else {
                // chord-only riadok (intro/medzihra)
                $ir[] = ['type' => 'chordonly', 'chords' => preg_split('/\s+/u', trim($line))];
            }
        } elseif (imp_is_pure_marker($line)) {
            $ir[] = ['type' => 'marker', 'text' => rtrim($line)];
        } else {
            // lyric bez akordov
            $ir[] = ['type' => 'lyric', 'segments' => [['chord' => '', 'text' => rtrim($line)]]];
        }
    }
    return $ir;
}

/** Je riadok sekčný marker (1., R:, ®, [Verse], atď.)? */
function imp_is_section_marker(string $line): bool {
    return (bool)preg_match('/^\s*(\[|\d+\.|®|R:|Ref|Verse|Chorus|Bridge|Intro|Outro|Solo|Coda)/iu', trim($line));
}

/* ================================================================== */
/* Helpery + dispatcher                                               */
/* ================================================================== */

/**
 * Rozdeľ "Song – Artist" na [title, artist].
 * $artistFirst=true => vstup je "Artist – Song".
 */
function imp_split_title_artist(string $s, bool $artistFirst = false): array {
    $s = trim($s);
    foreach (['–', '—', ' - ', ' : ', ':'] as $sep) {
        $pos = mb_strpos($s, $sep);
        if ($pos !== false) {
            $a = trim(mb_substr($s, 0, $pos));
            $b = trim(mb_substr($s, $pos + mb_strlen($sep))); // mb_strlen, nie strlen!
            return $artistFirst ? [$b, $a] : [$a, $b];
        }
    }
    return $artistFirst ? [$s, ''] : [$s, ''];
}

/** Rozpoznaj zdroj z URL. */
function imp_detect_source(string $url): string {
    $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
    $map = [
        'supermusic'        => 'supermusic',
        '4334.sk'           => '4334',
        'proscholy'         => 'proscholy',
        'mladeznickyspevnik'=> 'mladeznicky',
        'brnkni'            => 'brnkni',
        'pisnicky-akordy'   => 'pisnicky',
        'worshipleaderapp'  => 'worship',
        'ultimate-guitar'   => 'ultimate',
    ];
    foreach ($map as $needle => $key) {
        if (strpos($host, $needle) !== false) return $key;
    }
    return 'generic';
}

/** Je zdroj (URL host) anglický tab web? (tam B = B-natural -> H). */
function imp_source_is_english(string $url): bool {
    $host = mb_strtolower(parse_url($url, PHP_URL_HOST) ?? $url, 'UTF-8');
    foreach (['ultimate-guitar', 'e-chords', 'azchords', 'chordie', 'guitaretab',
              'songsterr', 'tabs4acoustic', 'chordu', 'cowboylyrics'] as $en) {
        if (strpos($host, $en) !== false) return true;
    }
    return false;
}

/** Vytiahni holý text (lyrics) z IR — na fallback jazykovej detekcie. */
function imp_ir_lyrics(array $ir): string {
    $t = '';
    foreach ($ir as $ln) {
        if (($ln['type'] ?? '') === 'lyric') {
            foreach ($ln['segments'] ?? [] as $seg) $t .= ($seg['text'] ?? '') . ' ';
        }
    }
    return $t;
}

/** Scrapni URL -> normalizovaný výsledok. */
function imp_scrape_url(string $url): array {
    $src = imp_detect_source($url);
    $res = imp_fetch_url($url);
    if ($res['code'] >= 400 || $res['body'] === '') {
        return ['ok' => false, 'error' => "HTTP {$res['code']} " . $res['err'],
                'source' => $src];
    }
    $html = $res['body'];
    switch ($src) {
        case 'supermusic': $out = imp_scrape_supermusic($html); break;
        case '4334':       $out = imp_scrape_4334($html); break;
        case 'proscholy':  $out = imp_scrape_proscholy($html); break;
        case 'mladeznicky':$out = imp_scrape_mladeznicky($html); break;
        case 'brnkni':     $out = imp_scrape_brnkni($html); break;
        case 'pisnicky':   $out = imp_scrape_pisnicky($html); break;
        default:           $out = imp_scrape_generic($html); break;
    }
    // artist fallback z URL slugu (brnkni /<x>/<artist>/<song>/ atď.)
    if (trim($out['artist'] ?? '') === '') {
        $out['artist'] = imp_artist_from_url($url, $src);
    }
    $out['ok'] = !empty($out['ir']);
    $out['source_url'] = $res['url'];
    // B/H konvencia: EN doména vždy; pri neznámom (generic) zdroji fallback na jazyk.
    // Známe SK/CZ scrapery NIKDY nie sú EN (ich B = B-flat).
    $knownSk = in_array($src, ['supermusic', '4334', 'proscholy', 'mladeznicky', 'brnkni', 'pisnicky'], true);
    $enChords = imp_source_is_english($url)
        || (!$knownSk && imp_detect_language(imp_ir_lyrics($out['ir'])) === 'en');
    imp_chord_english($enChords);
    $out['content'] = imp_format_final(imp_render_ir($out['ir'])); // domáci štýl
    imp_chord_english(false); // reset pre ďalšiu pieseň
    $out['raw_html_len'] = strlen($html);
    if (!$out['ok']) $out['error'] = 'Nepodarilo sa extrahovať obsah piesne.';
    return $out;
}

/** Odhad artist z URL slugu. */
function imp_artist_from_url(string $url, string $src): string {
    $path = trim(parse_url($url, PHP_URL_PATH) ?? '', '/');
    $segs = array_values(array_filter(explode('/', $path)));
    $slug = '';
    if ($src === 'brnkni' && count($segs) >= 3) $slug = $segs[1]; // /a/<artist>/<song>/
    if ($slug === '') return '';
    // slug -> "Meno Priezvisko"
    $slug = str_replace(['-', '_'], ' ', $slug);
    return mb_convert_case(trim($slug), MB_CASE_TITLE, 'UTF-8');
}
