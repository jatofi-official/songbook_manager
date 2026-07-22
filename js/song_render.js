// Central European (Czech/Slovak) chord notation uses "H" for natural B and
// "B" for A#/Bb. If a song also contains an explicit "Bb" chord, that song is
// using international notation instead, so plain "B" then means natural B.
const CHORD_NOTE_NAMES_CZ = ["C", "C#", "D", "D#", "E", "F", "F#", "G", "G#", "A", "B", "H"];
const CHORD_NOTE_NAMES_INTL = ["C", "C#", "D", "D#", "E", "F", "F#", "G", "G#", "A", "A#", "B"];
const CHORD_BASE_SEMITONE = { C: 0, D: 2, E: 4, F: 5, G: 7, A: 9 };

function detectUseInternationalNotation(content) {
    return /\bBb/.test(content);
}

function noteToSemitone(letter, accidental, useInternational) {
    letter = letter.toUpperCase();
    if (letter === "H") return 11;
    if (letter === "B") {
        if (accidental && accidental.toLowerCase() === "b") return 10;
        return useInternational ? 11 : 10;
    }
    const base = CHORD_BASE_SEMITONE[letter];
    if (base === undefined) return null;
    if (accidental === "#") return (base + 1) % 12;
    if (accidental && accidental.toLowerCase() === "b") return (base + 11) % 12;
    return base;
}

function semitoneToNote(semitone, useInternational) {
    const names = useInternational ? CHORD_NOTE_NAMES_INTL : CHORD_NOTE_NAMES_CZ;
    return names[((semitone % 12) + 12) % 12];
}

const CHORD_TOKEN_REGEX = /^(\()?([A-GH])(#|b)?([^\/\)]*)(\))?(?:\/([A-GH])(#|b)?)?$/i;

function transposeChordToken(token, steps, useInternational) {
    const m = token.match(CHORD_TOKEN_REGEX);
    if (!m) return token;
    const [, openParen, rootLetter, rootAcc, quality, closeParen, bassLetter, bassAcc] = m;

    // useInternational only affects how the ambiguous input "B" is interpreted;
    // output is always respelled in Czech/Slovak notation (B = A#/Bb, H = natural B).
    const rootSemi = noteToSemitone(rootLetter, rootAcc, useInternational);
    if (rootSemi === null) return token;

    let result = (openParen || "") + semitoneToNote(rootSemi + steps, false) + (quality || "") + (closeParen || "");

    if (bassLetter) {
        const bassSemi = noteToSemitone(bassLetter, bassAcc, useInternational);
        result += "/" + (bassSemi !== null ? semitoneToNote(bassSemi + steps, false) : bassLetter + (bassAcc || ""));
    }

    return result;
}

function transposeChordLine(line, steps, useInternational) {
    // split while keeping the separators (spaces/commas) so alignment is preserved
    const parts = line.split(/([\s,]+)/);
    let pendingAdjust = 0; // chars to trim (>0) or add (<0) to the next separator if the previous chord changed length

    return parts.map(part => {
        const isSeparator = part.trim() === "";

        if (isSeparator) {
            if (pendingAdjust > 0 && part.length > 0) {
                const maxRemovable = Math.max(part.length - 1, 0); // always keep >= 1 char between chords
                const removed = Math.min(pendingAdjust, maxRemovable);
                part = part.slice(0, part.length - removed);
            } else if (pendingAdjust < 0) {
                part += " ".repeat(-pendingAdjust);
            }
            pendingAdjust = 0;
            return part;
        }

        if (/^(®:|®|:|\/:|:\/|-|x[0-9]+)$/i.test(part)) return part;

        const transposed = transposeChordToken(part, steps, useInternational);
        pendingAdjust = transposed.length - part.length;
        return transposed;
    }).join("");
}

function transposeContent(content, steps) {
    // always run (even at steps=0) so input notation gets respelled to Czech/Slovak
    const useInternational = detectUseInternationalNotation(content);
    return content.split(/\r?\n/).map(line => {
        if (isChordLine(line)) {
            return transposeChordLine(line, steps, useInternational);
        }
        return line.replace(/\{([^}]+)\}/g, (match, inner) => "{" + transposeChordLine(inner, steps, useInternational) + "}");
    }).join("\n");
}

function isChordLine(line) {
    const chordToken = "\\(?[A-GH](#|b)?(maj|min|mi|m|dim|aug|sus|add)?[0-9]*(7|9|11|13)?[\\+\\-]?\\)?(\\/([A-GH](#|b)?))?";
    const allowedSymbols = "(®:|®|:|/:|:/)";
    const dashToken = "-";
    const repeatToken = "x[0-9]+";
    const tokenPatternStr = `^(${chordToken}|${allowedSymbols}|${dashToken}|${repeatToken})$`;
    const tokenPattern = new RegExp(tokenPatternStr, 'i');

    // Split by spaces or commas, trimming tokens
    const tokens = line.split(/[\s,]+/).filter(t => t.length > 0);

    // Check if every token matches chord, allowed symbol, or dash
    return tokens.every(token => tokenPattern.test(token));
}






function renderSong(content) {
  const lines = content.split(/\r?\n/);
  return lines.map(line => {
    if (isChordLine(line)) {
      // whole line bold
      return `<strong>${line}</strong>`;
    } else {
      // bold the ® symbols
      // and bold chords inside {...}, replacing braces with a space
      return line
        .replace(/®/g, '<strong>®</strong>')
        .replace(/\{([^}]+)\}/g, (match, chordInside) => ` <strong>${chordInside}</strong> `);
    }
  }).join('\n');
}



function enhanceSongView() {
    const el = document.getElementById("song-content-js");
    if (!el) {
        console.log("enhanceSongView: element #song-content-js not found!");
        return;
    }

    el.dataset.rawContent = el.textContent;
    applyTranspose(el, 0);
}

function enhanceSongDiv(el) {
    if (!el) return;

    el.dataset.rawContent = el.textContent;
    applyTranspose(el, 0);
}

function applyTranspose(el, steps) {
    if (!el) return;
    if (el.dataset.rawContent === undefined) {
        el.dataset.rawContent = el.textContent;
    }
    el.dataset.transposeSteps = String(steps);
    el.innerHTML = renderSong(transposeContent(el.dataset.rawContent, steps));

    document.querySelectorAll("[data-transpose-label]").forEach(label => {
        label.textContent = steps > 0 ? "+" + steps : String(steps);
    });
}

document.addEventListener("click", function (e) {
    const btn = e.target.closest("[data-transpose]");
    if (!btn) return;

    const el = document.getElementById("song-content-js");
    if (!el) return;

    const action = btn.dataset.transpose;
    let steps = parseInt(el.dataset.transposeSteps || "0", 10);
    if (action === "up") steps += 1;
    else if (action === "down") steps -= 1;
    else if (action === "reset") steps = 0;

    applyTranspose(el, steps);
});


function adjustFontSizeToFitWidth(el) {
    if (!el) return;

    const style = window.getComputedStyle(el);
    let fontSize = parseInt(style.fontSize, 10);
    const minFontSize = 8;

    el.style.overflow = "visible";

    const lines = el.innerText.split(/\r?\n/);

    // Temp span for measurement
    const temp = document.createElement('span');
    temp.style.position = 'absolute';
    temp.style.visibility = 'hidden';
    temp.style.whiteSpace = 'nowrap';
    temp.style.fontFamily = style.fontFamily;
    temp.style.fontWeight = style.fontWeight;
    document.body.appendChild(temp);

    while (fontSize >= minFontSize) {
        temp.style.fontSize = fontSize + 'px';
        let fits = true;

        for (const line of lines) {
            temp.textContent = line;
            if (temp.offsetWidth > el.clientWidth) {
                fits = false;
                break;
            }
        }

        if (fits) {
            break; // found the first size that actually fits
        }

        fontSize--; // keep shrinking until it does fit
    }

    el.style.fontSize = fontSize + 'px';
    document.body.removeChild(temp);
}



