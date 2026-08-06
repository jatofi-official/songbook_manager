<?php
/**
 * import_process.php — streamovaný SSE handler importu (Internet / Link / Upload).
 * Volaný cez fetch() POST; odpovedá text/event-stream (data: {json}\n\n).
 * Import nikdy automaticky nevytvorí skontrolovanú verejnú pieseň.
 */
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../import/parsers.php';

/* ---- SSE bootstrap ---- */
set_time_limit(0); // veľké PDF spevníky (100+ piesní) bežia dlho
header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');
while (ob_get_level() > 0) { ob_end_flush(); }
ob_implicit_flush(true);

function imp_sse(array $data): void {
    echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
    @flush();
}

/* ---- Auth / feature gate ---- */
if (!isset($_SESSION['id'], $_SESSION['user'])) {
    imp_sse(['status' => 'error', 'error' => 'Nie ste prihlásený.']); exit;
}
load_user_settings_into_session($mysqli);
if (empty($_SESSION['import_enabled'])) {
    imp_sse(['status' => 'error', 'error' => 'Import feature nie je zapnutý (User settings → Appearance).']); exit;
}

$mode      = $_POST['mode'] ?? '';
$createdBy = (int)$_SESSION['id'];
session_write_close(); // dlhý import nesmie blokovať otvorenie priebežných draftov v novom tabe

// Viditeľnosť importu: LEN private alebo bez_kontroly. Import NIKDY nezverejní (public iba ručne).
$visibility = $_POST['visibility'] ?? 'bez_kontroly';
if ($visibility !== 'private') $visibility = 'bez_kontroly';
$impIsPublic  = ($visibility === 'private') ? 0 : 1;  // bez_kontroly = viditeľné (is_public=1)
$impUnchecked = ($visibility === 'bez_kontroly') ? 1 : 0;

// Kategóriu smie user vynútiť pre celý import (riadi aj slovník tagov). '' = auto-detekcia.
$forceCat = trim($_POST['category'] ?? '');
// Dohľadávanie chýbajúcich autorov z internetu (checkbox; robí web request na pieseň).
$enrich = !empty($_POST['enrich_authors']);

// Poistka: znormalizuj textové vstupy na UTF-8 (keby klient poslal CP1250/Latin-2)
foreach (['title', 'artist', 'url', 'text'] as $k) {
    if (isset($_POST[$k]) && !mb_check_encoding($_POST[$k], 'UTF-8')) {
        $_POST[$k] = mb_convert_encoding($_POST[$k], 'UTF-8', 'Windows-1250, ISO-8859-2, UTF-8');
    }
}

/* ================================================================== */
/* Spracuj jeden scrape výsledok -> draft (kroky 3-5)                  */
/* ================================================================== */
function imp_finish_from_scrape(mysqli $mysqli, array $scrape, string $sourceType,
                                string $sourceUrl, int $createdBy, array $overrides = [],
                                int $isPublic = 1, int $isUnchecked = 1): void {
    // duplicitný import (rovnaký source_url) -> preskočiť
    if ($sourceUrl !== '') {
        $dup = imp_find_by_source_url($mysqli, $sourceUrl);
        if ($dup) {
            imp_sse(['status' => 'duplicate', 'song_id' => $dup['id'], 'title' => $dup['title']]);
            return;
        }
    }

    // kategória + AI genre/mood tagy (kategóriovo-vedomý vocab)
    $title   = $overrides['title']  ?? $scrape['title']  ?? '';
    $artist  = $overrides['artist'] ?? $scrape['artist'] ?? '';
    $content = $scrape['content'] ?? '';
    imp_sse(['status' => 'progress', 'step' => 3, 'total' => 5,
             'label' => '🏷️ (3/5) AI tagy — ' . ($title ?: 'pieseň')]);
    $category = (isset($overrides['category']) && $overrides['category'] !== '')
        ? (int)$overrides['category']
        : imp_detect_category($title, $artist, $content, $scrape['source'] ?? '');
    $vocab = imp_build_tag_vocab($mysqli, $category);
    $ai = imp_ai_tags($mysqli, $content, $title, $artist, $category, $vocab);
    $overrides['ai_tags']   = $ai['tags'];
    $overrides['category']  = $category;
    $overrides['is_public'] = $isPublic;
    $data = imp_build_song($scrape, $sourceUrl, $overrides);

    imp_sse(['status' => 'progress', 'step' => 4, 'total' => 5,
             'label' => '▶️ (4/5) YouTube link — ' . ($title ?: 'pieseň')]);
    if (trim($data['youtube_link']) === '') {
        $data['youtube_link'] = imp_youtube_search($data['title'], $data['artist']);
    }

    imp_sse(['status' => 'progress', 'step' => 5, 'total' => 5,
             'label' => '💾 (5/5) Ukladám draft — ' . ($title ?: 'pieseň')]);
    $save = imp_save_draft($mysqli, $data, $sourceType, $sourceUrl,
                           $scrape['content'] ?? '', (float)($scrape['confidence'] ?? 0),
                           $createdBy, $isUnchecked, $ai);
    if ($save['ok']) {
        imp_sse(['status' => 'saved', 'song_id' => $save['song_id'],
                 'title' => $data['title'], 'artist' => $data['artist'],
                 'category' => $data['category'], 'youtube' => $data['youtube_link'],
                 'tags' => $data['tags'], 'ai_ms' => $ai['ms'] ?? 0,
                 'ai_tokens' => ($ai['prompt_tokens'] ?? 0) + ($ai['completion_tokens'] ?? 0),
                 'confidence' => round((float)($scrape['confidence'] ?? 0), 2)]);
    } else {
        imp_sse(['status' => 'error', 'error' => 'Uloženie zlyhalo: ' . $save['error']]);
    }
}

/* ================================================================== */
/* Mód: LINK                                                          */
/* ================================================================== */
if ($mode === 'link') {
    $url = trim($_POST['url'] ?? '');
    if (!preg_match('#^https?://#i', $url)) {
        imp_sse(['status' => 'error', 'error' => 'Neplatná URL.']); exit;
    }
    imp_sse(['status' => 'progress', 'step' => 1, 'total' => 5, 'label' => '🔗 Otváram link...']);
    $scrape = imp_scrape_url($url);
    if (empty($scrape['ok'])) {
        imp_sse(['status' => 'error', 'error' => $scrape['error'] ?? 'Scrape zlyhal.']); exit;
    }
    imp_sse(['status' => 'progress', 'step' => 2, 'total' => 5,
             'label' => '🔄 Preformátúvam text a akordy... (' . $scrape['source'] . ')']);
    imp_finish_from_scrape($mysqli, $scrape, 'link', $scrape['source_url'] ?? $url, $createdBy,
                           ($forceCat !== '' ? ['category' => $forceCat] : []), $impIsPublic, $impUnchecked);
    imp_sse(['status' => 'complete']);
    exit;
}

/* ================================================================== */
/* Mód: INTERNET (name + author -> nájdi zdroj)                       */
/* ================================================================== */
if ($mode === 'internet') {
    $title  = trim($_POST['title'] ?? '');
    $artist = trim($_POST['artist'] ?? '');
    if ($title === '') { imp_sse(['status' => 'error', 'error' => 'Zadaj názov piesne.']); exit; }

    imp_sse(['status' => 'progress', 'step' => 1, 'total' => 5,
             'label' => '🔍 Vyhľadávam vhodný zdroj...']);
    $urls = imp_web_search_song($title, $artist);
    if (empty($urls)) {
        imp_sse(['status' => 'error', 'error' => 'Nenašiel sa žiadny podporovaný zdroj pre túto pieseň.']); exit;
    }
    // Auto + validácia názvu: v poradí rankingu, over že scraped názov SEDÍ s tvojím.
    $userTok = imp_tokens($title);
    $scrape = null; $usedUrl = ''; $best = null;
    foreach (array_slice($urls, 0, 4) as $u) {
        imp_sse(['status' => 'progress', 'step' => 2, 'total' => 5,
                 'label' => '📝 Skúšam zdroj: ' . parse_url($u, PHP_URL_HOST)]);
        $s = imp_scrape_url($u);
        if (empty($s['ok'])) continue;
        // title-match = koľko user-tokenov je v scraped názve
        $stTok = imp_tokens($s['title'] ?? '');
        $match = !empty($userTok) ? count(array_intersect($userTok, $stTok)) / count($userTok) : 0;
        $s['_match'] = $match; $s['_url'] = $s['source_url'] ?? $u;
        // silná zhoda + čistý formát -> vezmi hneď
        if ($match >= 0.6 && ($s['confidence'] ?? 0) >= 0.8) {
            $scrape = $s; $usedUrl = $s['_url']; break;
        }
        if ($best === null || $match > $best['_match']
            || ($match === $best['_match'] && ($s['confidence'] ?? 0) > ($best['confidence'] ?? 0))) {
            $best = $s;
        }
    }
    // fallback: najlepšia zhoda ak je aspoň slušná
    if (!$scrape) {
        if ($best !== null && $best['_match'] >= 0.5) { $scrape = $best; $usedUrl = $best['_url']; }
        else {
            imp_sse(['status' => 'error',
                     'error' => 'Presná zhoda pre "' . $title . '" sa nenašla. Skús Link tab s konkrétnou URL.']);
            exit;
        }
    }
    // scraped názov sedí -> ber scraped; user-input len keď scraped prázdny (nemaskuj cudzí obsah)
    $ov = [];
    if (trim($scrape['title'] ?? '')  === '') $ov['title']  = $title;
    if (trim($scrape['artist'] ?? '') === '') $ov['artist'] = $artist;
    if ($forceCat !== '') $ov['category'] = $forceCat;
    imp_finish_from_scrape($mysqli, $scrape, 'internet', $usedUrl, $createdBy, $ov, $impIsPublic, $impUnchecked);
    imp_sse(['status' => 'complete']);
    exit;
}

/* ================================================================== */
/* Mód: UPLOAD (TXT/MD, možno viac piesní)                            */
/* ================================================================== */
if ($mode === 'upload') {
    imp_sse(['status' => 'progress', 'step' => 1, 'total' => 5, 'label' => '📂 Načítavam súbor...']);

    // PDF spevník -> python extraktor (notový aj textový formát)
    if (!empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])
        && preg_match('/\.pdf$/i', $_FILES['file']['name'] ?? '')) {
        imp_process_pdf_upload($mysqli, $_FILES['file']['tmp_name'], $createdBy, $impIsPublic, $impUnchecked,
                               $forceCat, $enrich);
        exit;
    }

    $raw = '';
    if (!empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
        $raw = file_get_contents($_FILES['file']['tmp_name']);
    } elseif (!empty($_POST['text'])) {
        $raw = $_POST['text'];
    }
    $raw = (string)$raw;
    // vynúť UTF-8
    if (!mb_check_encoding($raw, 'UTF-8')) {
        $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1250, ISO-8859-2, UTF-8');
    }
    if (trim($raw) === '') { imp_sse(['status' => 'error', 'error' => 'Prázdny súbor.']); exit; }

    imp_sse(['status' => 'progress', 'step' => 2, 'total' => 5, 'label' => '🔍 Detegujem piesne...']);
    $chunks = imp_split_songs($raw);
    imp_sse(['status' => 'info', 'label' => 'Nájdené piesní: ' . count($chunks)]);

    $ok = 0;
    // tagy z DB — aby sa napísaný tag ("vianočné") neuznal za autora
    $knownTags = array_map(fn($t) => mb_strtolower($t, 'UTF-8'), get_all_tags($mysqli));
    $total = count($chunks);
    foreach ($chunks as $idx => $chunk) {
        $meta = imp_extract_upload_meta($chunk, $knownTags);
        $nr = ($idx + 1) . '/' . $total;
        $nm = $meta['title'] ?: 'pieseň';
        imp_sse(['status' => 'progress', 'step' => 3, 'total' => 6,
                 'label' => '🔄 (' . $nr . ') Formátujem — ' . $nm]);
        $ir = imp_plaintext_to_ir($meta['body']);
        // B/H konvencia: upload nemá doménu -> podľa jazyka (EN paste napr. z ultimate-guitar).
        imp_chord_english(imp_detect_language($meta['body']) === 'en');
        $content = imp_format_final(imp_render_ir($ir)); // domáci štýl (rovnako ako link)
        imp_chord_english(false); // reset pre ďalšiu pieseň
        // autor chýba -> skús dohľadať (nikdy neprepisuj existujúceho)
        if ($enrich && trim($meta['artist']) === '' && $meta['title'] !== '') {
            imp_sse(['status' => 'progress', 'step' => 4, 'total' => 6,
                     'label' => '🔎 (' . $nr . ') Hľadám autora — ' . $nm]);
            $meta['artist'] = imp_enrich_artist($meta['title']);
        }
        $scrape = ['title' => $meta['title'], 'artist' => $meta['artist'],
                   'content' => $content, 'source' => 'upload', 'confidence' => 0.5];

        imp_sse(['status' => 'progress', 'step' => 4, 'total' => 6,
                 'label' => '🏷️ (' . $nr . ') AI tagy — ' . $nm]);
        $category = $forceCat !== '' ? (int)$forceCat
                  : imp_detect_category($meta['title'], $meta['artist'], $content, 'upload');
        $vocab = imp_build_tag_vocab($mysqli, $category);
        $ai = imp_ai_tags($mysqli, $content, $meta['title'], $meta['artist'], $category, $vocab);
        $data = imp_build_song($scrape, '',
                    ['is_public' => $impIsPublic, 'category' => $category, 'ai_tags' => $ai['tags']]);

        imp_sse(['status' => 'progress', 'step' => 5, 'total' => 6,
                 'label' => '▶️ (' . $nr . ') YouTube — ' . $nm]);
        $data['youtube_link'] = imp_youtube_search($data['title'], $data['artist']);

        imp_sse(['status' => 'progress', 'step' => 6, 'total' => 6,
                 'label' => '💾 (' . $nr . ') Ukladám draft — ' . $nm]);
        $save = imp_save_draft($mysqli, $data, 'upload', '', $chunk, 0.5, $createdBy, $impUnchecked, $ai);
        if ($save['ok']) {
            $ok++;
            imp_sse(['status' => 'saved', 'song_id' => $save['song_id'],
                     'title' => $data['title'], 'artist' => $data['artist'],
                     'category' => $data['category'], 'youtube' => $data['youtube_link'],
                     'confidence' => 0.5]);
        } else {
            imp_sse(['status' => 'error', 'error' => 'Uloženie zlyhalo: ' . $save['error']]);
        }
    }
    imp_sse(['status' => 'complete', 'saved_count' => $ok]);
    exit;
}

imp_sse(['status' => 'error', 'error' => 'Neznámy mód.']);
// imp_split_songs() + imp_extract_upload_meta() sú v php/import/parsers.php

/* ================================================================== */
/* PDF spevník upload — extrakcia + uloženie všetkých piesní          */
/* ================================================================== */
function imp_process_pdf_upload(mysqli $mysqli, string $tmpPath, int $createdBy,
                                int $isPublic, int $isUnchecked,
                                string $forceCat = '', bool $enrich = false): void {
    imp_sse(['status' => 'progress', 'step' => 2, 'total' => 6,
             'label' => '📖 Extrahujem piesne z PDF spevníka...']);
    $res = imp_pdf_extract($tmpPath);
    if (empty($res['ok'])) {
        imp_sse(['status' => 'error', 'error' => 'PDF: ' . ($res['error'] ?? 'extrakcia zlyhala')]);
        return;
    }
    $songs = $res['songs'] ?? [];
    imp_sse(['status' => 'info', 'label' => 'Nájdené piesní: ' . count($songs)
             . ' (režim: ' . ($res['mode'] ?? '?') . ')']);
    if (empty($songs)) { imp_sse(['status' => 'error', 'error' => 'V PDF sa nenašli žiadne piesne.']); return; }

    $ok = 0; $total = count($songs);
    foreach ($songs as $idx => $sng) {
        $title  = trim($sng['title'] ?? '');
        $author = trim($sng['author'] ?? '');
        $body   = (string)($sng['body'] ?? '');
        if (trim($body) === '') continue;
        $nr = ($idx + 1) . "/$total";
        $nm = $title ?: 'pieseň';
        imp_sse(['status' => 'progress', 'step' => 3, 'total' => 6,
                 'label' => '🔄 (' . $nr . ') Formátujem — ' . $nm]);

        // body má inline \x01chord\x01 markery -> priamo IR -> render -> domáci formát
        $content = imp_format_final(imp_render_ir(imp_marked_to_ir($body)));
        if (trim($content) === '') continue;

        // autor chýba -> skús dohľadať (nikdy neprepisuj existujúceho)
        if ($enrich && $author === '' && $title !== '') {
            imp_sse(['status' => 'progress', 'step' => 4, 'total' => 6,
                     'label' => '🔎 (' . $nr . ') Hľadám autora — ' . $nm]);
            $author = imp_enrich_artist($title);
        }

        // kategória: voľba usera > hint z hlavičky spevníka (Adventné/Vianočné...) > detekcia
        [$hintCat, $hintTag] = imp_pdf_category_hint($sng['category_hint'] ?? '');
        $category = $forceCat !== '' ? (int)$forceCat
                  : ($hintCat ?: imp_detect_category($title, $author, $content, 'pdf'));

        imp_sse(['status' => 'progress', 'step' => 4, 'total' => 6,
                 'label' => '🏷️ (' . $nr . ') AI tagy — ' . $nm]);
        $vocab = imp_build_tag_vocab($mysqli, $category);
        $ai = imp_ai_tags($mysqli, $content, $title, $author, $category, $vocab);
        $aiTags = $ai['tags'];
        if ($hintTag !== '' && !in_array($hintTag, $aiTags, true)) array_unshift($aiTags, $hintTag);
        $aiTags = imp_filter_tags_for_category($aiTags, $category);

        $scrape = ['title' => $title, 'artist' => $author, 'content' => $content,
                   'source' => 'pdf', 'confidence' => 0.7];
        $data = imp_build_song($scrape, '', ['is_public' => $isPublic, 'category' => $category,
                                             'ai_tags' => $aiTags]);
        imp_sse(['status' => 'progress', 'step' => 5, 'total' => 6,
                 'label' => '▶️ (' . $nr . ') YouTube — ' . $nm]);
        $data['youtube_link'] = imp_youtube_search($data['title'], $data['artist']);

        imp_sse(['status' => 'progress', 'step' => 6, 'total' => 6,
                 'label' => '💾 (' . $nr . ') Ukladám draft — ' . $nm]);
        $save = imp_save_draft($mysqli, $data, 'pdf', '', $body, 0.7, $createdBy, $isUnchecked, $ai);
        if ($save['ok']) {
            $ok++;
            imp_sse(['status' => 'saved', 'song_id' => $save['song_id'],
                     'title' => $data['title'], 'artist' => $data['artist'],
                     'category' => $data['category'], 'youtube' => $data['youtube_link'],
                     'tags' => $data['tags'], 'ai_ms' => $ai['ms'] ?? 0,
                     'ai_tokens' => ($ai['prompt_tokens'] ?? 0) + ($ai['completion_tokens'] ?? 0),
                     'confidence' => 0.7]);
        } else {
            imp_sse(['status' => 'error', 'error' => "'{$title}': " . $save['error']]);
        }
    }
    imp_sse(['status' => 'complete', 'saved_count' => $ok]);
}
