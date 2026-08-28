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
// characters in. If the chord sits past the end of a short (or entirely
// absent) lyric line - e.g. a trailing chord after the last word, or an
// instrumental-only chord line with no lyric line at all - the remaining
// distance is extrapolated using `monoCharWidth` (one column = one original
// monospace character width), NOT the proportional font's own space-character
// width. A proportional font's space is usually much narrower than a
// monospace character, so measuring extrapolated distance with it collapses
// chords far closer together than their original column spacing intended -
// this is what made pure chord-only lines (and trailing chords past a short
// lyric) look garbled/bunched up.
function measurePrefixWidth(referenceLine, upToColumn, monoCharWidth, measureNormal) {
    const line = String(referenceLine ?? "");
    if (upToColumn <= line.length) {
        return measureNormal(line.slice(0, upToColumn));
    }
    const extraColumns = upToColumn - line.length;
    return measureNormal(line) + extraColumns * monoCharWidth;
}

// Returns [{ text, x }] - each chord token from `chordLine` with the pixel
// offset (from the start of the line box) it should render at, so it sits
// above the same point in `lyricLine` it was aligned to in the original
// monospace layout. `lyricLine` may be null/undefined (a chord-only line,
// e.g. an intro/interlude with no words under it) - handled the same way as
// a lyric line that's simply shorter than the chord line (see
// measurePrefixWidth).
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
    const reference = lyricLine != null ? lyricLine : "";
    const measureNormal = (text) => measure(text, false);

    const positions = parseChordTokens(chordLine).map(({ text, column }) => ({
        text,
        x: measurePrefixWidth(reference, column, monoCharWidth, measureNormal),
        anchored: lyricLine != null && column < lyricLine.length && lyricLine[column] !== " "
    }));

    const minGap = measure(" ", false);
    for (let i = 1; i < positions.length; i++) {
        const prevWidth = measure(positions[i - 1].text, true);
        const minX = positions[i - 1].x + prevWidth + minGap;
        if (positions[i].x >= minX) continue;

        if (positions[i].anchored && !positions[i - 1].anchored) {
            // Keep the anchored chord exactly where it is; move the
            // unanchored one out of its way instead. Deliberately NOT
            // clamped to >= 0 here - see rowOffset below.
            let newPrevX = positions[i].x - minGap - prevWidth;
            if (i - 2 >= 0) {
                // Don't let that push it into whatever comes before IT.
                const prevPrevWidth = measure(positions[i - 2].text, true);
                newPrevX = Math.max(newPrevX, positions[i - 2].x + prevPrevWidth + minGap);
            }
            positions[i - 1].x = newPrevX;
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

function renderChordRow(positions) {
    if (!positions.length) return '<div class="chord-row"></div>';
    const spans = positions
        // "chord-token", not "chord" - .chord is already a pre-existing,
        // differently-styled class in styles.css/songs_styles.css (used
        // elsewhere), so reusing that name picked up unrelated styling here.
        .map(({ text, x }) => `<span class="chord-token" style="left:${Math.round(x)}px;">${escapeHtml(text)}</span>`)
        .join("");
    return `<div class="chord-row">${spans}</div>`;
}

// Same inline-{chord}/® handling as renderSong() in song_render.js, just
// wrapped as its own row instead of relying on <pre> for line breaks.
// `rowOffset` (see computeChordPositions) shifts the lyric text right by the
// same amount its paired chord-row's chords were shifted, so an unanchored
// chord that needed extra room still lines up with the (also-shifted)
// anchored chord's real syllable.
function renderLyricRow(line, rowOffset) {
    const escaped = escapeHtml(line)
        .replace(/®/g, "<strong>®</strong>")
        .replace(/\{([^}]+)\}/g, (match, inner) => ` <strong>${inner}</strong> `);
    const style = rowOffset > 0 ? ` style="margin-left:${Math.round(rowOffset)}px;"` : "";
    return `<div class="lyric-row"${style}>${escaped || "&nbsp;"}</div>`;
}

// Renders full song content as proportional-font-safe HTML: each
// chord-line/lyric-line pair becomes a positioned chord-row overlaying a
// normal lyric-row, using `measure` (pixels for a given string in the target
// font/weight) and `monoCharWidth` (the original monospace column width) to
// place every chord. A lone chord line (no lyric line immediately below it)
// still gets a chord-row, just positioned by monospace-equivalent column
// spacing instead of lyric text (see computeChordPositions).
function renderSongProportional(content, monoCharWidth, measure) {
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
            out.push(renderChordRow(positions));

            if (nextIsLyric) {
                out.push(renderLyricRow(next, rowOffset));
                i += 2;
                continue;
            }

            i += 1;
            continue;
        }

        out.push(renderLyricRow(line, 0));
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
