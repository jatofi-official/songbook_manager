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

test('recognizes German/Czech "s" flat suffix (Es, As...) without misreading "sus" chords', () => {
    assert.equal(isChordLine('Esdim  Asm  Es'), true);
    // "sus"/"susN" must still parse as a quality, not "s" (flat) + "us...".
    assert.equal(isChordLine('Esus4  Csus2'), true);
});

test('transposes the "s" flat suffix (Es = E-flat) correctly, distinct from "sus" chords', () => {
    assert.equal(transposeChordToken('Esdim', 0, false), 'D#dim');
    assert.equal(transposeChordToken('Esdim', 2, false), 'Fdim');
    assert.equal(transposeChordToken('As', 0, false), 'G#');
    // Esus4 is E (natural) + "sus4", not "Es" (E-flat) + "us4".
    assert.equal(transposeChordToken('Esus4', 0, false), 'Esus4');
    assert.equal(transposeChordToken('Esus4', 2, false), 'F#sus4');
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
