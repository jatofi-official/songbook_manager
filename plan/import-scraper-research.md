# Import — Scraper Research (Fáza 0, "predom")

Zistené z reálnych stiahnutých HTML (2026-07-01). Každý zdroj má DETERMINISTICKÝ
extraction pattern zahardcodovaný v `php/import/scrapers.php` — systém runtime NEzisťuje
štruktúru, len aplikuje tieto pravidlá.

## Zhrnutie zdrojov

| Zdroj | Scrapeable server-side | Container | Chord markup | Minor | H/B |
|-------|------------------------|-----------|--------------|-------|-----|
| supermusic.cz | ÁNO (cookie challenge) | `div#chordText` | inline `<sup><a class=chord data-original=X>` pred slabikou | `mi` (Dmi) | H=B nat, B=Bb |
| 4334.sk | ÁNO | `.chordwp-container` | per-slabika spany (ChordWP) | lowercase (c#) | H=B nat |
| zpevnik.proscholy.cz | ÁNO (SSR Vue) | `.song-lyrics` | `span.chord` = `chord-base`+`chord-variant`+`chord-extension` + `chord-text` | variant `m` (Am) | štandard |
| mladeznickyspevnik.sk | ÁNO | `.chordline .accord` | inline `<strong>[X]</strong>` pred slabikou | lowercase (e,a) | B=Bb |
| brnkni.cz | ÁNO | `.chordpad.text` | inline `<span>X</span>` pred slabikou, newlines literal | `mi` (Emi) | H=B nat, B=Bb |
| pisnicky-akordy.cz | ÁNO | `<pre>` | `<el class=aline><span class=akord>X</span></el>` chord line nad lyric (2-line) | `mi` (Ami) | H/B |
| worshipleaderapp.com | NIE (client-render SPA) | — | obsah cez JS/API | — | — |
| ultimate-guitar.com | NIE (Cloudflare Turnstile) | — | `js-store` JSON `[ch]X[/ch]` | — | — |

worship + UG → generic fallback / phase 2. 5 deterministických zdrojov pokrýva CZ/SK
folk (brnkni, supermusic) + duchovné (proscholy, 4334, mladeznicky) = hlavné kategórie.

## Per-zdroj detail

### supermusic.cz
- URL piesne s akordami: `piesen.php?idpiesne=ID&sh=1`
- **Cookie challenge:** prvý GET vráti ~720B JS stub so `document.cookie="_sm_verified=<hex>..."`.
  Extrahovať `_sm_verified=<hex>` regexom, re-GET s `Cookie: _sm_verified=<hex>`.
- Obsah v `<div class="chord-text" id="chordText">`.
- Akord: `<sup><a class="chord" data-original="Dmi" ...>Dmi</a></sup>` INLINE pred slabikou.
  Použiť `data-original`. Prvý `<sup>` na začiatku riadku = akordy nad prvým znakom.
- Riadky oddelené `\n`. Prvý riadok (samé `<sup>` bez textu) = intro akordy.
- Normalizácia: `Dmi`→`Dm`. `B`=Bb (ponechať), `H`=B natural (ponechať).
- Konverzia inline→2-line positional (chord line nad lyric line).

### 4334.sk (WordPress ChordWP plugin)
- URL: `4334.sk/piesne/<slug>/`
- Obsah v `.chordwp-container`. Štruktúra:
  - `.chwp-lyrics-row-wrapper` > `.chwp-lyrics-row` (modifikátory `chwp-chords-in-line`,
    `chwp-lyrics-in-line` / `chwp-no-lyrics-in-line`).
  - Segment = `.chwp-chord-lyrics-wrapper` > `.chwp-chord-wrapper`(N× `.chwp-chord`) + `.chwp-lyrics`>`.chwp-words`.
  - `.chwp-comment` (display:none) s textom ` 1:` / ` 2:` = verse markery.
- **Minor = lowercase** root: `c#`→`C#m`, `a`→`Am`, `e`→`Em`. `H`=B nat. `E/G#` bass.
- `chwp-no-lyrics-in-line` = riadok len akordy (intro/medzihra).
- Rekonštrukcia: per segment chord nad začiatkom slabiky.

### zpevnik.proscholy.cz (SSR Vue)
- URL: `pisen/<id>/<slug>`
- Obsah v `.song-lyrics`. `.song-part` (+ `.song-part-refrain` = refrén),
  `.song-line`, `.song-part-tag` = "1. "/"R:".
- Segment = `span.chord` > `span.chord-sign`(`chord-base`+`chord-variant`+`chord-extension`(+bass))
  + `span.chord-text` (lyric pod akordom). Prázdny chord-base = žiadny akord.
- **Už target formát** (`Am`, `F`). Len rekonštruovať pozíciu + sekcie.
- Vue markery `<!--[-->` / `<!--]-->` stripovať.

### mladeznickyspevnik.sk
- URL: `song/show?songId=ID`
- Obsah v `.chordline .wrapper_accord .accord`. Riadky = `<span>...</span><br/>`.
- Akord inline: `<strong><span>[</span>C<sup></sup><span>]</span></strong>` pred slabikou = `[C]`.
- **Minor = lowercase** (`e`→Em, `a`→Am). `B`=Bb.
- Sekcie inline v texte: `R: /:...:/` refrén+repeat, `1.` verse, `(orig in D)` = orig. tónina.

### brnkni.cz
- URL: `<pismeno>/<artist-slug>/<song-slug>/`
- Obsah v `.chordpad.text`. Akord inline `<span>G</span>` / `<span>Emi</span>` pred slabikou.
- `mi`=minor (`Emi`→Em, `Ami`→Am). `B`=Bb, `H`=B nat. Newlines literal, prázdny riadok = medzi slokami.

### Generic fallback
- cURL + strip `<script>/<style>`, DOMDocument text extrakcia z `<pre>` alebo najväčšieho
  text bloku. Heuristická chord-line detekcia (detector.php). Nízka confidence.

## Detekčné heuristiky (metadata) — navrhnuté (user "neviem podľa čoho")

**category** (songs.category, povinný int):
1. Zdroj: proscholy/4334/mladeznicky/worship → Duchovné (8).
2. Artist mapping: Nedvěd*/Brontosauři/Greenhorns → 2; Nohavica/Kryl → 3.
3. Jazyk lyrics: SK znaky (ľ ĺ ŕ ô ä) → Slovenské (5); CZ (ř ě ů) → Iné České (4);
   prevažne ASCII/EN → Anglické-Iné (7).
4. Fallback → Ostatné (11).

**instruments**: default `Guitar` (primary v `instruments` tabuľke).
**is_nsfw**: 0. **capo**: 0.
**difficulty**: `songs.tags` kóduje difficulty ako tag (`easy`/`normal`/`hard`,
  viď `filter_difficulty_tags()`). Auto-detekcia z akordov: počet distinct + zložitosť
  (barré F/B/H, #/b root, extenzie maj/dim/aug/sus/add/7…) → `imp_detect_difficulty()`.
**tags**: = difficulty (1 tag). Genre/mood tagy (rock/western/romantické…) sa NEDAJÚ
  spoľahlivo auto-generovať → doplní človek. 1 tag < FIXED_TAGS_COUNT(4) ⇒ is_fixed=0.

## Draft storage (rozhodnutie: "bez kontroly" — auto-save, viditeľné)
- `is_public=1, is_fixed=0, owner=$_SESSION["user"]`, tags=difficulty.
- is_public=1 ⇒ import je viditeľný ako bežná NOVÁ (unfinished) pieseň, nie skrytý private.
  Review/doplnenie genre tagov v bežnom song editore (`song.php?id=X`).
- `source_url` uložený do nového stĺpca. Záznam do `import_log`.
