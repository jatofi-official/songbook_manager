const test = require('node:test');
const assert = require('node:assert/strict');

const {
    isChordLine,
    renderSong,
    transposeChordToken,
    transposeContent
} = require('../js/song_render.js');

test('recognizes Czech/Slovak chord lines and rejects lyrics', () => {
    assert.equal(isChordLine('C  G/H  Am7  F#'), true);
    assert.equal(isChordLine('(C/G) D7sus4 Aadd9 x2'), true);
    assert.equal(isChordLine('Hello world'), false);
    assert.equal(isChordLine(''), false);
});

test('transposes Czech/Slovak notes, flats, slash chords and inline chords', () => {
    assert.equal(transposeChordToken('H7', 1, false), 'C7');
    assert.equal(transposeChordToken('B', 2, false), 'C');
    assert.equal(transposeChordToken('Bb/D', 2, true), 'C/E');
    assert.equal(transposeChordToken('(C/G)', 2, false), '(D/A)');
    assert.equal(transposeContent('C G/H\nText {Am F}', 2), 'D A/C#\nText {Hm G}');
});

test('keeps chord columns aligned when note widths change', () => {
    assert.equal(transposeContent('C  F#  G', 1), 'C# G   G#');
});

test('escapes song content before adding formatting markup', () => {
    const rendered = renderSong('<img src=x onerror=alert(1)> {C}');
    assert.equal(rendered.includes('<img'), false);
    assert.equal(rendered.includes('&lt;img'), true);
    assert.equal(rendered.includes('<strong>C</strong>'), true);
});
