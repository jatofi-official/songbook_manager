const test = require('node:test');
const assert = require('node:assert/strict');

const {
    parseChordTokens,
    measurePrefixWidth,
    computeChordPositions,
    computeFontScale,
    renderSongProportional
} = require('../js/chord_font_layout.js');

// Deterministic mock measurer: every character is `charWidth`px wide,
// regardless of the isChord flag (bold == normal width here). Lets tests
// assert exact pixel numbers without a real font/canvas, while still
// exercising the real positioning math (as opposed to a monospace-only
// shortcut like `text.length * charWidth`, which would hide bugs where the
// code accidentally assumes uniform width elsewhere). Extra args (isChord)
// are simply ignored when the caller passes them - fine in JS.
function fixedWidthMeasurer(charWidth) {
    return (text) => text.length * charWidth;
}

// A "proportional-ish" mock: narrow characters cost less than wide ones, so
// tests can catch positioning code that accidentally treats all characters
// as equal width.
function variableWidthMeasurer() {
    const widths = { i: 4, l: 4, ' ': 5 };
    return (text) => [...text].reduce((sum, ch) => sum + (widths[ch] ?? 10), 0);
}

test('parseChordTokens finds each token and its column', () => {
    assert.deepEqual(parseChordTokens('C   G/H  Am7'), [
        { text: 'C', column: 0 },
        { text: 'G/H', column: 4 },
        { text: 'Am7', column: 9 }
    ]);
});

test('parseChordTokens returns nothing for a blank line', () => {
    assert.deepEqual(parseChordTokens(''), []);
    assert.deepEqual(parseChordTokens('   '), []);
});

test('measurePrefixWidth measures within the reference line normally', () => {
    const measure = fixedWidthMeasurer(10);
    assert.equal(measurePrefixWidth('Hello world', 5, 10, measure), 50);
    assert.equal(measurePrefixWidth('Hello world', 0, 10, measure), 0);
});

test('measurePrefixWidth extrapolates past the end of a short/absent reference using monoCharWidth', () => {
    const measure = fixedWidthMeasurer(10);
    // reference is only 5 chars; asking for column 8 needs 3 extra columns,
    // each monoCharWidth (12) wide - NOT measure(' ') - regardless of what
    // the proportional font's own space character measures as.
    assert.equal(measurePrefixWidth('Hello', 8, 12, measure), 50 + 3 * 12);
    // no reference at all (instrumental chord line) - entirely extrapolated from monoCharWidth
    assert.equal(measurePrefixWidth('', 4, 12, measure), 4 * 12);
    assert.equal(measurePrefixWidth(null, 4, 12, measure), 4 * 12);
});

test('measurePrefixWidth extrapolates an unanchored blank run WITHIN the reference line too, not just past its end', () => {
    // Mirrors a real reported case: several chords positioned far to the
    // right, above a lyric line that's mostly leading spaces before a short
    // melisma ("... nanana ..."). None of those chords are anchored to a
    // real character, so the leading blank padding under them has to
    // extrapolate via monoCharWidth the same way a chord past a short
    // line's end already did - not via the font's own (usually much
    // narrower) proportional space width, which used to bunch them all
    // closer together than their original columns intended.
    const measure = fixedWidthMeasurer(2); // narrow proportional space
    const monoCharWidth = 10;
    const reference = '                    ... nanana ...'; // 20 leading spaces
    assert.equal(measurePrefixWidth(reference, 20, monoCharWidth, measure, false), 20 * monoCharWidth);
});

test('measurePrefixWidth measures an anchored blank run proportionally, matching the real rendered lyric text', () => {
    // The opposite case: a chord IS anchored to a real character, and
    // everything before it on the line is blank padding. The real lyric
    // row renders that padding as real text (white-space:pre), so the
    // chord has to match wherever the browser actually puts that
    // character - which means the narrow proportional space width, NOT
    // monoCharWidth, even though the prefix itself is blank.
    const measure = fixedWidthMeasurer(2);
    const monoCharWidth = 10;
    assert.equal(measurePrefixWidth('     Run', 5, monoCharWidth, measure, true), 5 * 2);
});

test('measurePrefixWidth extrapolation ignores the proportional font\'s own (narrower) space width', () => {
    // This is the actual bug: a proportional font's space is usually much
    // narrower than a monospace character, so extrapolating with measure(' ')
    // instead of monoCharWidth collapsed chord-only/trailing-chord spacing
    // far closer together than the original monospace columns intended.
    const narrowSpaceMeasure = (text) => [...text].reduce((sum, ch) => sum + (ch === ' ' ? 2 : 10), 0);
    const monoCharWidth = 10; // the original monospace font's character width
    // 4 columns past the end of the (empty) reference
    assert.equal(measurePrefixWidth('', 4, monoCharWidth, narrowSpaceMeasure), 40); // 4 * monoCharWidth
    assert.notEqual(measurePrefixWidth('', 4, monoCharWidth, narrowSpaceMeasure), narrowSpaceMeasure('    ')); // would be 8 with the old, wrong approach
});

test('computeChordPositions aligns each chord to the measured width of the lyric text preceding it', () => {
    const measure = fixedWidthMeasurer(10);
    // "C" at column 0, "G" at column 6, over lyric "Amazing grace"
    const { positions, rowOffset } = computeChordPositions('C     G', 'Amazing grace', 10, measure);
    assert.deepEqual(positions, [
        { text: 'C', x: 0 },
        { text: 'G', x: 60 }
    ]);
    assert.equal(rowOffset, 0);
});

test('computeChordPositions uses real per-character widths, not column count directly', () => {
    // "l" and "i" are narrow (4px), other letters are 10px, spaces are 5px.
    const measure = variableWidthMeasurer();
    // chord line: "A" at col 0, "D" at col 6 (after "little " which is narrower than 6*10)
    const { positions } = computeChordPositions('A     D', 'little words', 10, measure);
    const littleWidth = measure('little'); // l(4)+i(4)+t(10)+t(10)+l(4)+e(10) = 42
    assert.equal(positions[0].x, 0);
    assert.equal(positions[1].x, littleWidth); // column 6 == exactly "little"
});

test('computeChordPositions handles a chord-only line (no lyric line) via monoCharWidth-based spacing', () => {
    const measure = fixedWidthMeasurer(10);
    const { positions, rowOffset } = computeChordPositions('C    G    Am', null, 10, measure);
    assert.deepEqual(positions, [
        { text: 'C', x: 0 },
        { text: 'G', x: 50 },
        { text: 'Am', x: 100 }
    ]);
    assert.equal(rowOffset, 0);
});

test('computeChordPositions keeps a chord-only line spaced by the ORIGINAL monospace width, not the proportional font\'s own space width', () => {
    // Proportional font's space (2px) is much narrower than the monospace
    // font it's replacing (10px) - previously this made instrumental/
    // chord-only lines look garbled/bunched together.
    const measure = (text) => [...text].reduce((sum, ch) => sum + (ch === ' ' ? 2 : 10), 0);
    const monoCharWidth = 10;
    const { positions } = computeChordPositions('C    G', null, monoCharWidth, measure);
    // "G" is at column 5 -> 5 * monoCharWidth = 50, not 5 * narrow-space (10)
    assert.equal(positions[1].x, 50);
});

test('computeChordPositions places a chord past the end of a short lyric line correctly', () => {
    const measure = fixedWidthMeasurer(10);
    // lyric is only "Hi" (2 chars); chord at column 5 needs 3 extrapolated columns
    const { positions } = computeChordPositions('     C', 'Hi', 10, measure);
    assert.equal(positions[0].x, measure('Hi') + 3 * 10);
});

test('computeChordPositions pushes a chord label away from the previous one if they would otherwise overlap', () => {
    // Bold chords render much wider (20px/char) than the narrow lyric text
    // (2px/char) that determines their natural lyric-aligned position, so
    // "B"'s natural position would sit underneath "A"'s own rendered label.
    // Both are anchored here (both column 0 and column 5 sit on real "i"
    // characters), so the default push-right resolution applies.
    const measure = (text, isChord) => text.length * (isChord ? 20 : 2);
    const monoCharWidth = 10;
    // "A" at column 0, "B" at column 5, over a lyric line of narrow characters
    const { positions, rowOffset } = computeChordPositions('A    B', 'iiiiii', monoCharWidth, measure);

    assert.equal(positions[0].x, 0);
    // natural x for B would be measure('iiiii', false) = 10 - clamped instead to
    // A.x + A's bold width (20) + one space's width (measure(' ', false) = 2) = 22
    assert.equal(positions[1].x, 22);
    assert.equal(rowOffset, 0); // resolved by pushing B right, not by shifting the row
});

test('computeChordPositions keeps an anchored chord exactly on its lyric syllable by shifting an unanchored chord before it left instead', () => {
    // Mirrors a real reported case:
    //   Em G     C         Asus2
    //      Run a-way, I've got to;
    // "G" sits directly above "Run" (a real lyric character - anchored) and
    // must keep its position RELATIVE TO THE LYRIC TEXT; "Em" sits over blank
    // leading space (unanchored - not tied to any specific syllable), so when
    // its own bold label doesn't fit before "G", "Em" gives way instead.
    const measure = (text, isChord) => text.length * (isChord ? 20 : 2);
    const monoCharWidth = 10;
    const lyricLine = '     Run'; // 5 leading spaces, "R" at column 5
    const { positions, rowOffset } = computeChordPositions('Em   G', lyricLine, monoCharWidth, measure);

    const gNaturalX = 5 * 2; // = 10, "G"'s position before any row-level shift
    // "Em"'s bold width (40) + gap (2) leaves no room before "G" (natural x 10),
    // so the whole row shifts right by 40 + 2 - 10 = 32 instead of clamping "Em"
    // into an overlap with "G".
    assert.equal(rowOffset, 32);
    assert.equal(positions[0].x, 0); // "Em" pinned to the row's new left edge
    assert.equal(positions[1].x, gNaturalX + rowOffset); // "G" keeps its natural offset relative to the lyric
    // no overlap: "Em"'s rendered span ends right where "G" starts (or before)
    assert.ok(positions[0].x + measure('Em', true) + measure(' ', false) <= positions[1].x);
});

test('computeChordPositions cascades the shift through a whole run of unanchored chords before a fixed anchor, not just the immediate neighbor', () => {
    // Mirrors a real reported case: several unanchored chords (positioned
    // far to the right, over blank lead-in space before a short lyric
    // melisma) rendered overlapping each other. Only pushing the SINGLE
    // chord immediately before the anchor used to leave earlier ones in
    // the same run still colliding with each other, since each only ever
    // got shifted far enough to clear its own immediate neighbor.
    //
    //   Am  Bm  Cm  Dm      <- "Am"/"Bm"/"Cm" unanchored, "Dm" anchored on "x"
    //             x
    const measure = (text, isChord) => text.length * (isChord ? 20 : 2);
    const monoCharWidth = 10;
    const lyricLine = '            x'; // 12 leading spaces, "x" at column 12
    const { positions, rowOffset } = computeChordPositions('Am  Bm  Cm  Dm', lyricLine, monoCharWidth, measure);

    const chordWidth = measure('Am', true); // 40 - same for all 4 (2-char labels)
    const gap = measure(' ', false); // 2
    // Every consecutive pair should end up exactly minGap apart - not just
    // the last pair before "Dm", all three.
    for (let i = 1; i < positions.length; i++) {
        assert.equal(positions[i].x - positions[i - 1].x, chordWidth + gap);
    }
    // "Dm" still keeps its natural offset relative to the lyric ("x" at
    // column 12) plus the row shift, same guarantee as the two-chord case.
    const dmNaturalX = 12 * 2;
    assert.equal(positions[3].x, dmNaturalX + rowOffset);
});

test('computeChordPositions does not alter spacing that already has enough room', () => {
    // Wide (10px/char) lyric text this time, unlike the narrow (2px/char)
    // text in the previous test - "B"'s natural position ends up well clear
    // of "A"'s own bold-rendered width, so nothing should get clamped or shifted.
    const measure = (text, isChord) => text.length * (isChord ? 20 : 10);
    const monoCharWidth = 10;
    const { positions, rowOffset } = computeChordPositions('A    B', 'wwwwwwwwww', monoCharWidth, measure);
    // natural x for B = measure('wwwww', false) = 50; min allowed = A.x(0) + A's bold width (20) + gap (10) = 30
    assert.equal(positions[1].x, 50);
    assert.equal(rowOffset, 0);
});

test('computeFontScale is 1 when the proportional font is never wider than monospace', () => {
    const monoCharWidth = 10;
    // proportional measurer that's always narrower than monospace
    const measure = (text) => text.length * 8;
    const scale = computeFontScale('Hello\nWorld line', monoCharWidth, measure);
    assert.equal(scale, 1);
});

test('computeFontScale shrinks to the worst-case (widest relative) line, never grows past 1', () => {
    const monoCharWidth = 10;
    // "WIDE LINE" renders much wider proportionally than mono at 1:1 char width;
    // the short line stays comfortably within its mono budget.
    const measure = (text) => (text === 'WIDE LINE' ? 200 : text.length * 5);
    const scale = computeFontScale('WIDE LINE\nok', monoCharWidth, measure);
    // mono width of "WIDE LINE" (9 chars) = 90; proportional = 200 -> scale 90/200 = 0.45
    assert.equal(scale, 90 / 200);
});

test('computeFontScale measures chord lines at bold weight via the isChordLine flag', () => {
    const monoCharWidth = 10;
    const seenCalls = [];
    const measure = (text, isChord) => {
        seenCalls.push({ text, isChord });
        return isChord ? text.length * 20 : text.length * 5; // bold chords rendered much wider
    };
    const scale = computeFontScale('C G\nlyrics here', monoCharWidth, measure);

    assert.deepEqual(seenCalls, [
        { text: 'C G', isChord: true },
        { text: 'lyrics here', isChord: false }
    ]);
    // worst case is the chord line: mono width 3*10=30 vs proportional 3*20=60 -> 0.5
    assert.equal(scale, 0.5);
});

test('computeFontScale ignores blank lines', () => {
    const scale = computeFontScale('\n\n', 10, () => 1000);
    assert.equal(scale, 1);
});

test('renderSongProportional pairs a chord line with the lyric line directly below it', () => {
    const measure = fixedWidthMeasurer(10);
    const html = renderSongProportional('C   G\nAmazing grace', 10, measure);
    assert.equal(
        html,
        '<div class="chord-row"><span class="chord-token" style="left:0px;">C</span>'
        + '<span class="chord-token" style="left:40px;">G</span></div>'
        + '<div class="lyric-row" style="position:relative;">Amazing grace</div>'
    );
});

test('renderSongProportional treats a chord line with no following lyric line as chord-only', () => {
    const measure = fixedWidthMeasurer(10);
    const html = renderSongProportional('C   G\nD   Em', 10, measure); // two chord lines back to back
    assert.equal(
        html,
        '<div class="chord-row"><span class="chord-token" style="left:0px;">C</span>'
        + '<span class="chord-token" style="left:40px;">G</span></div>'
        + '<div class="chord-row"><span class="chord-token" style="left:0px;">D</span>'
        + '<span class="chord-token" style="left:40px;">Em</span></div>'
    );
});

test('renderSongProportional escapes HTML and keeps inline {chord} / ® handling from renderSong', () => {
    const measure = fixedWidthMeasurer(10);
    const html = renderSongProportional('<b>Text</b> {Am F} ®', 10, measure);
    assert.equal(html.includes('<b>'), false);
    assert.equal(html.includes('&lt;b&gt;'), true);
    assert.equal(html.includes('<strong>Am F</strong>'), true);
    // The visible ® is now a manually-positioned overlay (see renderLyricRow) -
    // a hidden placeholder stays in the flowing text purely to reserve its width.
    assert.equal(html.includes('<span class="chorus-marker-symbol" style="visibility:hidden;">®</span>'), true);
    assert.equal(
        html.includes('<span style="position:absolute;left:190px;top:50%;transform:translateY(-50%);">'
            + '<span class="chorus-marker-symbol chord-token-marker" style="font-size:1em;">®</span></span>'),
        true
    );
});

test('renderSongProportional preserves blank lyric lines', () => {
    const measure = fixedWidthMeasurer(10);
    const html = renderSongProportional('Verse one\n\nVerse two', 10, measure);
    assert.equal(
        html,
        '<div class="lyric-row" style="position:relative;">Verse one</div>'
        + '<div class="lyric-row" style="position:relative;">&nbsp;</div>'
        + '<div class="lyric-row" style="position:relative;">Verse two</div>'
    );
});

test('renderSongProportional shifts the lyric row right by rowOffset when its chord row needed extra room', () => {
    const measure = (text, isChord) => text.length * (isChord ? 20 : 2);
    const html = renderSongProportional('Em   G\n     Run', 10, measure);
    assert.equal(
        html,
        '<div class="chord-row"><span class="chord-token" style="left:0px;">Em</span>'
        + '<span class="chord-token" style="left:42px;">G</span></div>'
        + '<div class="lyric-row" style="position:relative;margin-left:32px;">     Run</div>'
    );
});
