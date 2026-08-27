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
// a canvas measureText call (see createCanvasMeasurer), but every function
// here takes it as a parameter so the positioning logic itself is plain,
// synchronous, and unit-testable without a DOM (see
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
function computeChordPositions(chordLine, lyricLine, monoCharWidth, measure) {
    const reference = lyricLine != null ? lyricLine : "";
    const measureNormal = (text) => measure(text, false);

    const positions = parseChordTokens(chordLine).map(({ text, column }) => ({
        text,
        x: measurePrefixWidth(reference, column, monoCharWidth, measureNormal)
    }));

    const minGap = measure(" ", false);
    for (let i = 1; i < positions.length; i++) {
        const prevWidth = measure(positions[i - 1].text, true);
        const minX = positions[i - 1].x + prevWidth + minGap;
        if (positions[i].x < minX) positions[i].x = minX;
    }

    return positions;
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
function renderLyricRow(line) {
    const escaped = escapeHtml(line)
        .replace(/®/g, "<strong>®</strong>")
        .replace(/\{([^}]+)\}/g, (match, inner) => ` <strong>${inner}</strong> `);
    return `<div class="lyric-row">${escaped || "&nbsp;"}</div>`;
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

            out.push(renderChordRow(computeChordPositions(line, lyricLine, monoCharWidth, measure)));

            if (nextIsLyric) {
                out.push(renderLyricRow(next));
                i += 2;
                continue;
            }

            i += 1;
            continue;
        }

        out.push(renderLyricRow(line));
        i += 1;
    }

    return out.join("");
}

// Browser-only: a fast measure(text, isChord) -> pixels function backed by a
// shared <canvas> 2D context (no DOM reflow per call, unlike a hidden
// <span>+getBoundingClientRect - important here since a full song can mean
// hundreds of measure() calls per render). `boldFontWeight` defaults to
// "bold", matching .chord-token's CSS font-weight.
function createCanvasMeasurer(fontFamily, fontSizePx, boldFontWeight) {
    if (!createCanvasMeasurer._canvas) {
        createCanvasMeasurer._canvas = document.createElement("canvas");
    }
    const ctx = createCanvasMeasurer._canvas.getContext("2d");
    const normalFont = `normal ${fontSizePx}px ${fontFamily}`;
    const boldFont = `${boldFontWeight || "bold"} ${fontSizePx}px ${fontFamily}`;

    return (text, isChord) => {
        ctx.font = isChord ? boldFont : normalFont;
        return ctx.measureText(text).width;
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
