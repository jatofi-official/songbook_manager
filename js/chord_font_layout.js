// Renders song content in a proportional (non-monospace) font while keeping
// chords positioned above the right point in the lyric text below them.
//
// The original format (see song_render.js) positions a chord token by literal
// column: the chord line and the lyric line are both plain text, and the
// chord's horizontal position is however many space characters precede it -
// which only lines up visually because every character is the same width in
// a monospace font. Swap in a proportional font and that assumption breaks:
// "the chord starts at column 8" no longer means anything in pixels.
//
// The fix: for each chord token, measure the RENDERED WIDTH (in the target
// font) of the lyric line's substring up to that same column, and place the
// chord at that pixel offset instead. The lyric line renders normally below
// it; the chord line becomes a separate absolutely-positioned overlay row.
// This needs a `measure(text, isChord) -> pixels` function (isChord picks
// normal vs. bold weight, since chords render bold) - in the browser that's
// a hidden real DOM element (see createDomMeasurer - deliberately not
// canvas measureText, which doesn't always exactly match actual CSS text
// layout), but every function here takes it as a parameter so the
// positioning logic itself is plain, synchronous, and unit-testable without
// a real DOM (see
// tests/chord_font_layout.test.js).

// Node: pull in isChordLine/escapeHtml from song_render.js. Browser: `var`
// with no initializer never clobbers an existing binding, so this is a no-op
// there and the two names just resolve to song_render.js's own globals
// (loaded first) - it'd be a SyntaxError to redeclare them with let/const
// instead, since classic <script> tags share one global scope.
var isChordLine, escapeHtml;
if (typeof module !== "undefined" && module.exports) {
    ({ isChordLine, escapeHtml } = require("./song_render.js"));
}

// Finds every run of non-space characters in a chord line and the column
// (character index into the raw line) each one starts at. Doesn't re-verify
// chord-ness - isChordLine() already decided the whole line qualifies.
function parseChordTokens(chordLine) {
    const tokens = [];
    const regex = /\S+/g;
    let match;
    while ((match = regex.exec(String(chordLine ?? ""))) !== null) {
        tokens.push({ text: match[0], column: match.index });
    }
    return tokens;
}

// Width (in the target font) of `referenceLine` up to `upToColumn`
// characters in.
//
// `anchored` (whether THIS chord sits directly on a real, non-space lyric
// character - see computeChordPositions) changes how a purely blank prefix
// gets measured, because the two cases mean different things:
//
// - Anchored: the real lyric row renders this exact prefix as real text
//   (white-space:pre - see renderLyricRow), so the chord has to land exactly
//   above wherever the browser actually puts that real character, which
//   means measuring the prefix - blanks included - proportionally, in the
//   SAME font, so it matches.
// - Unanchored: nothing at this column ties it to any specific rendered
//   character, so there's no real position it has to match. A run of blank
//   columns here is usually acting as pure alignment PADDING (e.g. several
//   chords positioned far to the right, above a lyric line that's mostly
//   leading spaces before the words start - "                    ... nanana
//   ..." - or a chord sitting past the end of a short/absent lyric line
//   entirely, or an instrumental-only chord line with no lyric line at
//   all), placed assuming a FIXED monospace column width. Measuring that
//   padding's actual rendered width in a proportional font (whose space
//   glyph is usually much narrower) collapses it closer together than the
//   original column spacing intended, bunching every chord positioned over
//   it noticeably closer together than it should be - so this falls back to
//   `monoCharWidth` (one column = one original monospace character width)
//   for any blank run trailing right up to `upToColumn`. A real word
//   appearing earlier in the line still measures proportionally either way,
//   since that's genuine content.
function measurePrefixWidth(referenceLine, upToColumn, monoCharWidth, measureNormal, anchored) {
    const line = String(referenceLine ?? "");
    const prefix = upToColumn <= line.length ? line.slice(0, upToColumn) : line;

    if (anchored) {
        return measureNormal(prefix);
    }

    let lastRealIndex = -1;
    for (let i = prefix.length - 1; i >= 0; i--) {
        if (prefix[i] !== " ") {
            lastRealIndex = i;
            break;
        }
    }

    const realPart = lastRealIndex === -1 ? "" : prefix.slice(0, lastRealIndex + 1);
    const blankColumns = upToColumn - realPart.length;
    return measureNormal(realPart) + blankColumns * monoCharWidth;
}

// Returns [{ text, x }] - each chord token from `chordLine` with the pixel
// offset (from the start of the line box) it should render at, so it sits
// above the same point in `lyricLine` it was aligned to in the original
// monospace layout.
//
// `lyricLine` may be null/undefined - a genuinely chord-only line, e.g. an
// instrumental intro/interlude with no lyric row under it at all. That's
// handled differently from an ordinary chord sitting over blank space
// within a real lyric line (see measurePrefixWidth's `anchored` doc): with
// no lyric text anywhere to align to, `monoCharWidth` (the ORIGINAL
// monospace column width) isn't the right measure of intended spacing
// either - it stretches the row out to the monospace version's absolute
// pixel width, which is usually noticeably wider than this font actually
// needs, since chord labels themselves are typically narrower proportionally
// too. The only signal left for how far apart chords should sit is how many
// literal space characters the chord line ITSELF has between them, so a
// chord-only line measures proportionally straight off its own text - same
// mechanism `measurePrefixWidth` already uses for a real anchored chord,
// just measuring the chord line as the "reference" instead of a lyric line.
//
// A second pass then enforces a minimum gap between each chord's own
// (bold-measured) rendered width and the next chord's position: a
// proportional font can measure the lyric text between two chords as
// narrower than either chord LABEL actually needs to render without
// overlapping the next one, even though their monospace columns were fine.
//
// Which side gives way matters: a chord sitting directly above a real
// lyric character ("anchored") is meaningfully tied to that syllable and
// should keep its position RELATIVE TO THE LYRIC TEXT; a chord sitting over
// blank space (leading space before the lyric starts, or a chord-only line)
// isn't tied to anything specific and is the one that should move instead.
// E.g.
//   Em G     C         Asus2
//      Run a-way, I've got to;
// "G" sits directly above "Run" and must stay put; "Em" sits over blank
// leading space, so if its own label doesn't fit before "G", "Em" is the one
// that shifts left - not "G" getting shoved right off of "Run".
//
// If there still isn't room even at the very start of the line (e.g. "Em"'s
// own label is simply wider than the blank space "G" left for it), the fix
// isn't to clamp "Em" into overlapping "G" anyway - it's to shift the WHOLE
// LINE right by however much extra room is needed (`rowOffset`), same as
// physically inserting spaces before the lyric text. The caller is
// responsible for applying that same offset to the paired lyric row (see
// renderLyricRow) so the shifted chords and the shifted lyric text stay in
// sync with each other - only their shared position relative to the page
// moves, not their position relative to each other.
function computeChordPositions(chordLine, lyricLine, monoCharWidth, measure) {
    // A lyric line that's present but entirely blank (e.g. a chord line
    // immediately followed by an empty line before the next real lyric -
    // isChordLine('') is false, so the pairing logic in
    // renderSongProportional treats that blank line as "the lyric line"
    // rather than leaving this chord-only) has exactly as little to anchor
    // to as no lyric line at all - every column in it is blank, so every
    // chord would be unanchored anyway. Treat it the same as the true
    // chord-only case (proportional spacing off the chord line's own text)
    // rather than the ordinary "unanchored chord within a real lyric line"
    // case (monoCharWidth-extrapolated blank padding) - the whole point of
    // that monoCharWidth fallback is to stay consistent with OTHER, real
    // text elsewhere on the same line, which doesn't exist here.
    const hasLyricLine = lyricLine != null && /\S/.test(lyricLine);
    const measureNormal = (text) => measure(text, false);

    const positions = parseChordTokens(chordLine).map(({ text, column }) => {
        if (!hasLyricLine) {
            // No lyric line at all - measure straight off the chord line's
            // own text, always proportionally (never monoCharWidth - see
            // this function's own doc comment above). Reuses
            // measurePrefixWidth's `anchored: true` branch for that, since
            // it's the same "just measure the real prefix, blanks included,
            // in this font" behavior - just with the chord line standing in
            // as the reference text instead of a lyric line. Recorded as
            // `anchored: false` below regardless, since there's no real
            // lyric syllable to keep this chord pinned to for the overlap
            // pass further down - an ordinary rightward clamp is enough.
            return { text, x: measurePrefixWidth(chordLine, column, monoCharWidth, measureNormal, true), anchored: false };
        }
        const anchored = column < lyricLine.length && lyricLine[column] !== " ";
        return {
            text,
            x: measurePrefixWidth(lyricLine, column, monoCharWidth, measureNormal, anchored),
            anchored
        };
    });

    const minGap = measure(" ", false);
    for (let i = 1; i < positions.length; i++) {
        const prevWidth = measure(positions[i - 1].text, true);
        const minX = positions[i - 1].x + prevWidth + minGap;
        if (positions[i].x >= minX) continue;

        if (positions[i].anchored && !positions[i - 1].anchored) {
            // Keep the anchored chord exactly where it is; move the
            // unanchored run before it out of its way instead - the WHOLE
            // contiguous run of unanchored chords immediately preceding it
            // (not just the single one right next to it), shifted as one
            // rigid block by the same amount. Shifting only the immediate
            // neighbor used to just relocate the overlap one step further
            // back whenever 3+ unanchored chords needed to fit before a
            // single fixed anchor (e.g. several chords positioned over
            // blank lead-in space, ending at a short lyric melisma) -
            // each got pushed just far enough to clear its own immediate
            // neighbor, with no guarantee that reached far enough back to
            // also clear the one before THAT, so two of them could still
            // end up landing on top of each other. Shifting the whole run
            // by one uniform delta instead preserves whatever spacing they
            // already had relative to EACH OTHER (already resolved in
            // earlier iterations of this same loop) while only changing
            // their position relative to the fixed anchor. Deliberately
            // NOT clamped to >= 0 here - see rowOffset below.
            const delta = (positions[i].x - minGap - prevWidth) - positions[i - 1].x;
            let j = i - 1;
            while (j >= 0 && !positions[j].anchored) {
                positions[j].x += delta;
                j--;
            }
        } else {
            positions[i].x = minX;
        }
    }

    // Bring everything back into non-negative territory by shifting the
    // whole row right, rather than clamping the offending chord in place
    // (which would just reintroduce the overlap it was trying to avoid).
    const rowOffset = Math.max(0, -Math.min(0, ...positions.map((p) => p.x)));
    if (rowOffset > 0) {
        positions.forEach((p) => { p.x += rowOffset; });
    }

    return { positions: positions.map(({ text, x }) => ({ text, x })), rowOffset };
}

// The proportional font must never make any line WIDER than it was in the
// original monospace rendering (otherwise a songbook page layout computed
// against the monospace version could overflow). Given the fixed
// monospace character width `monoCharWidth` and a `measure` function bound
// to the proportional font at the size you'd like to use, this returns how
// much that size needs to be scaled down (<=1; never scaled up - a
// narrower-than-monospace font isn't a license to grow the text) so every
// line's proportional width stays within its monospace equivalent.
//
// `measure(text, isChordLine)` is called once per line so the caller can
// measure chord lines at bold weight (they render bold - see
// renderChordRow/.chord-token below) and lyric lines at normal weight,
// rather than under-estimating chord lines with a normal-weight-only
// measurement.
function computeFontScale(content, monoCharWidth, measure) {
    const lines = String(content ?? "").split(/\r?\n/);
    let minScale = 1;

    for (const line of lines) {
        if (!line.length) continue;
        const monoWidth = monoCharWidth * line.length;
        const properWidth = measure(line, isChordLine(line));
        if (properWidth > 0) {
            minScale = Math.min(minScale, monoWidth / properWidth);
        }
    }

    return minScale;
}

// A chord/lyric token IS (not just contains) the ® chorus-marker symbol if,
// once any trailing ":" is stripped, all that's left is ® - matches how
// song_ordering.js's isChorusMarkerLine/splitSongIfNeeded already recognize
// it ("®" or "®:" starting a line). escapeHtml(line).indexOf("®") - used
// below for the inline-within-lyric-text case - deliberately doesn't require
// this exact shape, since there ® can be mixed into running text however the
// song's source has it.
function isMarkerToken(text) {
    return /^®:?$/.test(String(text ?? "").trim());
}

// Inline style boosting how big a ® marker glyph/token renders, given the
// caller's chosen markerScale (see SONG_FONT_STACKS/SONG_FONT_MARKER_SCALE,
// song_render.js - a per-font multiplier, since fonts don't agree on how
// big their OWN ® glyph already is: Calibri's is tiny and needs a big
// boost, Verdana's is already large and needs none - a single fixed
// multiplier either left Calibri's ® too small or made Verdana's comically
// oversized). Defaults to 1 (no boost) if the caller doesn't pass one, e.g.
// existing callers/tests that predate this parameter.
//
// A plain `font-size` change - NOT `transform: scale()` (an earlier version
// of this used transform instead, on the theory that changing font-size
// distorts a glyph's own baseline/layout metrics unpredictably per font).
// That reasoning held back when this glyph was positioned purely by inline
// vertical-align. It no longer is: renderChordRow/renderLyricRow now wrap
// this glyph in its own box that's manually placed via
// top:50%/transform:translateY(-50%), centered against that box's OWN real
// rendered height - so however tall the boosted glyph actually renders,
// centering adapts to it automatically, whatever the mechanism.
// `transform: scale()` turned out to be the wrong mechanism for the boost
// itself, though: it enlarges symmetrically around the box's geometric
// center, and a font's ® glyph doesn't necessarily sit ink-centered within
// that box in the first place (how far off varies by font) - scaling from
// the wrong center amplifies that pre-existing offset, worse the bigger the
// scale, which is exactly why Calibri (markerScale 1.5, the largest in use)
// still looked wrong while Verdana (1, no boost at all) and Arial (1.15,
// barely any) didn't show it. A `font-size` change has no such artificial
// center to be wrong about - the glyph renders the same way any
// differently-sized character natively does - and the box it renders into
// auto-sizes to fit it, so the translateY(-50%) centering above still
// captures its true height correctly either way.
function markerScaleStyle(markerScale) {
    const scale = typeof markerScale === "number" && markerScale > 0 ? markerScale : 1;
    // Always set explicitly (even at scale 1, "no boost") - nothing else
    // declares a font-size for this glyph, so leaving it unset would just
    // silently fall back to whatever font-size cascades in from elsewhere.
    return `font-size:${scale}em;`;
}

function renderChordRow(positions, markerScale) {
    if (!positions.length) return '<div class="chord-row"></div>';
    const markerStyle = markerScaleStyle(markerScale);
    const spans = positions
        // "chord-token", not "chord" - .chord is already a pre-existing,
        // differently-styled class in styles.css/songs_styles.css (used
        // elsewhere), so reusing that name picked up unrelated styling here.
        .map(({ text, x }) => {
            const marker = isMarkerToken(text);
            const tokenClass = marker ? "chord-token chord-token-marker" : "chord-token";
            // Regular chord tokens stay pinned to top:0 (see .chord-token,
            // styles/a4_page.css & songs_styles.css) - they need to sit at a
            // consistent baseline above the lyric text below them. A marker
            // token instead gets centered on the row's own height
            // (top:50%/translateY(-50%), relative to .chord-row's
            // position:relative box): different fonts' ® glyphs don't agree
            // on where they sit within their own line box (some render
            // noticeably high or low relative to their nominal font-size),
            // so pinning to top:0 like a real chord left it looking
            // consistently too high or too low depending on the font.
            // Centering by the marker span's own actual rendered height
            // sidesteps needing to know per-font metrics at all.
            const tokenStyle = marker
                ? `left:${Math.round(x)}px;top:50%;transform:translateY(-50%);`
                : `left:${Math.round(x)}px;`;
            // The marker boost (markerStyle) is meant for the ® glyph
            // itself, which some fonts render tiny - not for a trailing
            // ":" that's sometimes written after it ("®:"). Scaling the
            // whole token up (as this used to do) blew the colon up to the
            // same oversized font-size too, which - combined with this
            // span's line-height:1/vertical-align:middle (see the
            // chord-token-marker CSS rule) sized for the SMALL font-size,
            // not the boosted one - made it sit at a visibly wrong
            // baseline, floating away from the ® next to it. Only wrap the
            // ® character itself in the boosted size; any trailing text
            // renders at the row's normal chord size.
            const inner = marker
                ? escapeHtml(text).replace(/®/, `<span class="chorus-marker-symbol" style="${markerStyle}">®</span>`)
                : escapeHtml(text);
            return `<span class="${tokenClass}" style="${tokenStyle}">${inner}</span>`;
        })
        .join("");
    return `<div class="chord-row">${spans}</div>`;
}

// Same inline-{chord}/® handling as renderSong() in song_render.js, just
// wrapped as its own row instead of relying on <pre> for line breaks.
// `rowOffset` (see computeChordPositions) shifts the lyric text right by the
// same amount its paired chord-row's chords were shifted, so an unanchored
// chord that needed extra room still lines up with the (also-shifted)
// anchored chord's real syllable. `markerScale` - see markerScaleStyle.
// `measure` (same `measure(text, isChord) -> pixels` function passed
// everywhere else here) is used to place ® manually - see below.
function renderLyricRow(line, rowOffset, markerScale, measure) {
    const raw = String(line ?? "");
    const escaped = escapeHtml(raw)
        // The VISIBLE ® is rendered separately below as an absolutely-
        // positioned overlay, measured and placed exactly like a chord
        // token (see computeChordPositions/renderChordRow) instead of
        // relying on inline vertical-align - inline alignment of a
        // differently-sized glyph is relative to the surrounding text's
        // own font metrics (x-height, baseline, line-height), which don't
        // just vary by font but can end up inconsistent line to line
        // depending on exactly what else is on the line, which is what
        // made it look like it was "moving all over the place" rather than
        // being off by a single fixed, fixable amount. Measuring and
        // placing it explicitly, the same way a chord symbol is placed
        // above its lyric line, sidesteps that entirely - it doesn't rely
        // on the text layout engine's own alignment rules at all.
        //
        // This hidden copy is left in the normal flow purely so the
        // surrounding text still reserves its natural (unscaled) glyph
        // width and doesn't reflow when the real, visible overlay is added
        // on top of it.
        .replace(/®/g, `<span class="chorus-marker-symbol" style="visibility:hidden;">®</span>`)
        .replace(/\{([^}]+)\}/g, (match, inner) => ` <strong>${inner}</strong> `);

    let overlays = "";
    if (typeof measure === "function") {
        const markerStyle = markerScaleStyle(markerScale);
        let idx = raw.indexOf("®");
        while (idx !== -1) {
            // Prefix measured against the RAW line (® included as a normal-
            // width character, same as the hidden placeholder above
            // reserves) so this lines up with how the surrounding text
            // actually flows, including any earlier ® on the same line.
            const x = measure(raw.slice(0, idx), false);
            // Outer span: position only (left/top/translateY), sized
            // normally (untransformed) so translateY(-50%) centers a
            // predictable, normal-glyph-sized box on the row - same
            // two-layer split as renderChordRow's marker token (position
            // on the outer, transform: scale() on the inner) so the two
            // transforms don't have to be composed on one element.
            overlays += `<span style="position:absolute;left:${Math.round(x)}px;top:50%;transform:translateY(-50%);"><span class="chorus-marker-symbol chord-token-marker" style="${markerStyle}">®</span></span>`;
            idx = raw.indexOf("®", idx + 1);
        }
    }

    const style = `position:relative;` + (rowOffset > 0 ? `margin-left:${Math.round(rowOffset)}px;` : "");
    return `<div class="lyric-row" style="${style}">${escaped || "&nbsp;"}${overlays}</div>`;
}

// Renders full song content as proportional-font-safe HTML: each
// chord-line/lyric-line pair becomes a positioned chord-row overlaying a
// normal lyric-row, using `measure` (pixels for a given string in the target
// font/weight) and `monoCharWidth` (the original monospace column width) to
// place every chord. A lone chord line (no lyric line immediately below it)
// still gets a chord-row, just positioned by monospace-equivalent column
// spacing instead of lyric text (see computeChordPositions). `markerScale` -
// see markerScaleStyle; optional, defaults to no boost.
function renderSongProportional(content, monoCharWidth, measure, markerScale) {
    const lines = String(content ?? "").split(/\r?\n/);
    const out = [];
    let i = 0;

    while (i < lines.length) {
        const line = lines[i];

        if (isChordLine(line)) {
            const next = lines[i + 1];
            const nextIsLyric = next !== undefined && !isChordLine(next);
            const lyricLine = nextIsLyric ? next : null;

            const { positions, rowOffset } = computeChordPositions(line, lyricLine, monoCharWidth, measure);
            out.push(renderChordRow(positions, markerScale));

            if (nextIsLyric) {
                out.push(renderLyricRow(next, rowOffset, markerScale, measure));
                i += 2;
                continue;
            }

            i += 1;
            continue;
        }

        out.push(renderLyricRow(line, 0, markerScale, measure));
        i += 1;
    }

    return out.join("");
}

// Browser-only: a measure(text, isChord) -> pixels function backed by a
// hidden, real DOM <span> (getBoundingClientRect, not canvas measureText).
// Canvas's text shaping doesn't always exactly match the browser's own CSS
// text layout - kerning/hinting can diverge slightly per font, more so for
// kerning-heavy serif fonts (Georgia, Times) than sans-serif ones - and since
// every chord's position is measured from the start of the line, even a
// fraction of a pixel of per-character error compounds into a visible,
// growing drift further into a long line. A real span, styled identically to
// how the text actually renders, measures via the exact same layout engine
// the lyric text itself uses, so it can't diverge from it.
// white-space:pre is required, not optional - default collapses runs of
// spaces to one, which would badly under-measure chord-line prefixes with
// multiple leading/internal spaces (common - that's how the source columns
// were spaced in the first place).
//
// Measures at a large, fixed REFERENCE_SIZE and scales the result down to
// fontSizePx mathematically, rather than measuring directly at fontSizePx.
// getBoundingClientRect() quantizes to a fairly coarse pixel grid, and that
// quantization is a much larger RELATIVE error against a small measured
// width than a large one - fonts that need heavy shrinking to fit the
// monospace width budget (e.g. Georgia/Times New Roman, which are
// noticeably wider than Calibri/Arial at the same nominal size, so
// computeFontScale shrinks them more) end up measured at a small enough
// size for this quantization to become visible as a growing chord drift.
// Font metrics scale linearly with size, so measuring "big and scaling
// down" preserves the true ratio while avoiding that small-size precision
// loss entirely.
const MEASURE_REFERENCE_SIZE = 256;

function createDomMeasurer(fontFamily, fontSizePx, boldFontWeight) {
    if (!createDomMeasurer._el) {
        const el = document.createElement("span");
        el.style.position = "absolute";
        el.style.visibility = "hidden";
        el.style.left = "-9999px";
        el.style.top = "0";
        el.style.whiteSpace = "pre";
        document.body.appendChild(el);
        createDomMeasurer._el = el;
    }
    const el = createDomMeasurer._el;
    const scaleDown = fontSizePx / MEASURE_REFERENCE_SIZE;
    el.style.fontFamily = fontFamily;
    el.style.fontSize = MEASURE_REFERENCE_SIZE + "px";
    const boldWeight = boldFontWeight || "bold";

    return (text, isChord) => {
        el.style.fontWeight = isChord ? boldWeight : "normal";
        el.textContent = text;
        return el.getBoundingClientRect().width * scaleDown;
    };
}

if (typeof module !== "undefined" && module.exports) {
    module.exports = {
        parseChordTokens,
        measurePrefixWidth,
        computeChordPositions,
        computeFontScale,
        renderSongProportional
    };
}
