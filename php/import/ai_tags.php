<?php
/**
 * ai_tags.php — genre/mood tagy cez OpenRouter (free modely).
 * Kľúč + model z app_settings (DB). Kategóriovo-vedomý vocab.
 * Bez kľúča / chyby → prázdne (fallback len difficulty). Import NIKDY nespadne.
 */
require_once __DIR__ . '/../functions.php';

/** Difficulty tagy (nie sú genre) — vynechať z vocab aj z AI výstupu. */
const IMP_DIFFICULTY_TAGS = ['easy', 'normal', 'hard'];

/** Tagy LEN pre Duchovné (kategória 8). */
const IMP_TAGS_DUCHOVNE = ['liturgické', 'svätý', 'sláva bohu', 'ranná', 'chválová',
    'odovzdávanie', 'mladý', 'adventné', 'svätá rodina', 'vianočná'];

/** Tagy LEN pre Ľudovky (kategória 9). */
const IMP_TAGS_LUDOVKY = ['valčík', 'veselá', 'milostná', 'alkohol', 'dedina', 'svadobná', 'vojna'];

/** Normalizácia tagov (opravy). */
function imp_normalize_tag(string $tag): string {
    $t = mb_strtolower(trim($tag), 'UTF-8');
    $map = [
        'frajer' => 'vzťahy', 'frajerka' => 'vzťahy',
        'smutný' => 'vážna', 'smutny' => 'vážna',
    ];
    return $map[$t] ?? $t;
}

/**
 * Vybuduj tag vocabulary podľa kategórie — STRICT:
 * Duchovné (8) -> LEN duchovná sada; Ľudovky (9) -> LEN ľudovková sada;
 * ostatné -> general (všetky DB tagy mínus difficulty mínus obidve špeciálne sady).
 */
function imp_build_tag_vocab($mysqli, int $category): array {
    if ($category === 8) return IMP_TAGS_DUCHOVNE;
    if ($category === 9) return IMP_TAGS_LUDOVKY;
    $all = array_map(fn($t) => mb_strtolower($t, 'UTF-8'), get_all_tags($mysqli));
    $special = array_merge(IMP_TAGS_DUCHOVNE, IMP_TAGS_LUDOVKY);
    $exclude = array_merge(IMP_DIFFICULTY_TAGS, $special);
    return array_values(array_filter($all, fn($t) => !in_array($t, $exclude, true)));
}

/** Tagy zakázané pre danú kategóriu (špeciálne sady iných kategórií). */
function imp_forbidden_tags(int $category): array {
    $f = [];
    if ($category !== 8) $f = array_merge($f, IMP_TAGS_DUCHOVNE);
    if ($category !== 9) $f = array_merge($f, IMP_TAGS_LUDOVKY);
    return $f;
}

/**
 * STRICT filter výstupných tagov podľa kategórie:
 * cat 8 -> len duchovná sada; cat 9 -> len ľudovková; inak žiadne špeciálne ani difficulty.
 */
function imp_filter_tags_for_category(array $tags, int $category): array {
    if ($category === 8) return array_values(array_intersect($tags, IMP_TAGS_DUCHOVNE));
    if ($category === 9) return array_values(array_intersect($tags, IMP_TAGS_LUDOVKY));
    $drop = array_merge(IMP_TAGS_DUCHOVNE, IMP_TAGS_LUDOVKY, IMP_DIFFICULTY_TAGS);
    return array_values(array_filter($tags, fn($t) => !in_array($t, $drop, true)));
}

/**
 * Keyword pre-pass: vysoko-presné pravidlové tagy z textu (istota tam, kde matchnú).
 * LLM potom dopĺňa zvyšok. Rešpektuje kategóriové obmedzenia cez imp_forbidden_tags.
 */
function imp_keyword_tags(string $lyrics, string $title, int $category): array {
    $t = mb_strtolower($title . "\n" . $lyrics, 'UTF-8');
    $rules = [
        'vianočná' => '/vianoc|betlehem|jasli|jaslič|koled[ay]|narodil sa|pastieri/u',
        'svadobná' => '/svadb|nevest|ženích/u',
        'vojna'    => '/vojn[aueý]|vojak|verbuj|regiment/u',
        'alkohol'  => '/vínko|vínečko|pálen|pohárik|pijem|pili sme/u',
    ];
    $forbidden = imp_forbidden_tags($category);
    $out = [];
    foreach ($rules as $tag => $re) {
        if (!preg_match($re, $t)) continue;
        $tag = imp_normalize_tag($tag);
        if (in_array($tag, $forbidden, true)) continue;
        $out[] = $tag;
    }
    return $out;
}

/**
 * Few-shot príklady z vlastnej DB: 4 už otagované piesne rovnakej kategórie.
 * Retrieval-augmented prompting — zužuje rozdiel voči fine-tunovaným modelom.
 */
function imp_fewshot_examples($mysqli, int $category, int $limit = 4): string {
    $stmt = $mysqli->prepare(
        "SELECT title, tags, LEFT(content, 250) AS snippet
         FROM songs
         WHERE category = ? AND is_unchecked = 0
           AND tags IS NOT NULL AND tags <> ''
           AND LENGTH(tags) - LENGTH(REPLACE(tags, ',', '')) >= 2
         ORDER BY RAND() LIMIT ?");
    if (!$stmt) return '';
    $stmt->bind_param('ii', $category, $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    $blocks = [];
    while ($r = $res->fetch_assoc()) {
        // vyhoď difficulty tagy z príkladu (LLM má navrhovať len genre/mood)
        $tags = array_filter(
            array_map('trim', explode(',', mb_strtolower($r['tags'], 'UTF-8'))),
            fn($x) => $x !== '' && !in_array($x, IMP_DIFFICULTY_TAGS, true)
        );
        if (empty($tags)) continue;
        $snippet = preg_replace('/\s+/u', ' ', $r['snippet']);
        $blocks[] = "Pieseň: \"{$r['title']}\"\nText: " . mb_substr($snippet, 0, 200, 'UTF-8')
                  . "\nTagy: " . implode(', ', $tags);
    }
    $stmt->close();
    if (empty($blocks)) return '';
    return "Príklady otagovaných piesní z nášho spevníka:\n\n" . implode("\n\n", $blocks) . "\n\n";
}

/**
 * AI klasifikácia -> genre/mood tagy + metriky.
 * Keyword pre-pass tagy sa vracajú VŽDY (aj keď AI zlyhá).
 * @return array{tags:string[], ms:int, model:string, prompt_tokens:int, completion_tokens:int}
 */
function imp_ai_tags($mysqli, string $lyrics, string $title, string $artist,
                     int $category, array $vocab): array {
    // keyword pre-pass — tieto tagy vraciame vždy, aj keď AI zlyhá (STRICT filter)
    $kwTags = imp_filter_tags_for_category(imp_keyword_tags($lyrics, $title, $category), $category);
    $empty = ['tags' => $kwTags, 'ms' => 0, 'model' => '', 'prompt_tokens' => 0, 'completion_tokens' => 0, 'error' => ''];

    $key = trim(get_app_setting($mysqli, 'openrouter_api_key', ''));
    $model = trim(get_app_setting($mysqli, 'openrouter_model', 'meta-llama/llama-3.3-70b-instruct:free'));
    if ($key === '') return array_merge($empty, ['error' => 'API kľúč nenastavený']);

    // názov kategórie pre kontext
    $cats = get_all_categories($mysqli); // [id=>name]
    $catName = $cats[$category] ?? (string)$category;

    $vocabStr = implode(', ', array_slice($vocab, 0, 120));
    $lyricsSnippet = mb_substr(preg_replace('/\s+/', ' ', $lyrics), 0, 1500, 'UTF-8');
    $examples = imp_fewshot_examples($mysqli, $category); // few-shot z vlastnej DB

    // pre Duchovné/Ľudovky STRICT: len tagy zo zoznamu, žiadne nové
    $strictCat = ($category === 8 || $category === 9);
    $ruleLine = $strictCat
        ? "Použi VÝHRADNE tagy z tohto zoznamu, NEVYMÝŠĽAJ žiadne nové.\n"
        : "Preferuj tagy z tohto zoznamu; ak žiadny nesedí, môžeš navrhnúť JEDEN nový výstižný tag (slovensky, malé písmená).\n";
    $prompt = "Si tagovač piesní v slovenskom spevníku. Pieseň: \"$title\""
        . ($artist !== '' ? " od \"$artist\"" : '') . ", kategória: \"$catName\".\n"
        . "Vyber 2 až 4 genre/mood tagy. " . $ruleLine
        . "Zoznam tagov: $vocabStr\n"
        . "Vráť IBA tagy oddelené čiarkou, nič iné.\n\n"
        . $examples
        . "Text piesne:\n$lyricsSnippet";

    $payload = json_encode([
        'model' => $model,
        'messages' => [['role' => 'user', 'content' => $prompt]],
        'temperature' => 0.3,
        'max_tokens' => 60,
    ], JSON_UNESCAPED_UNICODE);

    $t0 = microtime(true);
    $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $key,
            'Content-Type: application/json',
            'HTTP-Referer: http://localhost/songbook_manager',
            'X-Title: Songbook Manager Import',
        ],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $ms = (int)round((microtime(true) - $t0) * 1000);

    if ($resp === false || $code >= 400) {
        $msg = 'HTTP ' . $code;
        $j = json_decode((string)$resp, true);
        if (!empty($j['error']['message'])) $msg .= ': ' . $j['error']['message'];
        return array_merge($empty, ['ms' => $ms, 'model' => $model, 'error' => mb_substr($msg, 0, 250)]);
    }
    $data = json_decode($resp, true);
    $content = $data['choices'][0]['message']['content'] ?? '';
    $ptok = (int)($data['usage']['prompt_tokens'] ?? 0);
    $ctok = (int)($data['usage']['completion_tokens'] ?? 0);
    if (trim($content) === '') {
        return array_merge($empty, ['ms' => $ms, 'model' => $model,
            'prompt_tokens' => $ptok, 'completion_tokens' => $ctok,
            'error' => 'Model nevrátil text (možno to nie je chat model)']);
    }

    // parse CSV -> normalize -> filter; keyword tagy prvé (vysoká presnosť)
    $forbidden = imp_forbidden_tags($category);
    $tags = $kwTags;
    foreach (preg_split('/[,\n;]+/u', $content) as $raw) {
        $tag = imp_normalize_tag($raw);
        $tag = preg_replace('/[^\p{L}\p{N} #\-]/u', '', $tag); // očisti
        $tag = trim($tag);
        if ($tag === '') continue;
        if (in_array($tag, IMP_DIFFICULTY_TAGS, true)) continue; // difficulty rieši detector
        if (in_array($tag, $forbidden, true)) continue;          // cudzo-kategóriové zakázané
        if (mb_strlen($tag) > 30) continue;
        if (!in_array($tag, $tags, true)) $tags[] = $tag;
    }
    // STRICT: pre cat 8/9 prežijú LEN tagy z povolenej sady (aj keby model vymyslel nové)
    $tags = array_slice(imp_filter_tags_for_category($tags, $category), 0, 4);

    return ['tags' => $tags, 'ms' => $ms, 'model' => $model,
            'prompt_tokens' => $ptok, 'completion_tokens' => $ctok, 'error' => ''];
}
