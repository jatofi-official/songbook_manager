// Czech/Slovak notation uses H for natural B and B for A#/Bb.
const CHORD_NOTE_NAMES_CZ = ["C", "C#", "D", "D#", "E", "F", "F#", "G", "G#", "A", "B", "H"];
const CHORD_BASE_SEMITONE = { C: 0, D: 2, E: 4, F: 5, G: 7, A: 9 };
const CHORD_TOKEN_REGEX = /^(\()?([A-GH])(#|b)?([^/\s)]*)(?:\/([A-GH])(#|b)?)?(\))?$/i;
const CHORD_LINE_TOKEN_REGEX = /^(\(?[A-GH](?:#|b)?(?:(?:maj|min|mi|m|dim|aug|sus|add)?[0-9]*(?:sus|add)?[0-9]*(?:\+|-)?)(?:\/[A-GH](?:#|b)?)?\)?|®:|®|:|\/:|:\/|-|x[0-9]+)$/i;

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function detectUseInternationalNotation(content) {
    return /\bBb/.test(content);
}

function noteToSemitone(letter, accidental, useInternational) {
    const note = letter.toUpperCase();
    if (note === "H") return 11;
    if (note === "B") {
        if (accidental && accidental.toLowerCase() === "b") return 10;
        return useInternational ? 11 : 10;
    }

    const base = CHORD_BASE_SEMITONE[note];
    if (base === undefined) return null;
    if (accidental === "#") return (base + 1) % 12;
    if (accidental && accidental.toLowerCase() === "b") return (base + 11) % 12;
    return base;
}

function semitoneToNote(semitone) {
    return CHORD_NOTE_NAMES_CZ[((semitone % 12) + 12) % 12];
}

function transposeChordToken(token, steps, useInternational) {
    const match = token.match(CHORD_TOKEN_REGEX);
    if (!match) return token;

    const [, openParen, rootLetter, rootAccidental, quality, bassLetter, bassAccidental, closeParen] = match;
    const root = noteToSemitone(rootLetter, rootAccidental, useInternational);
    if (root === null) return token;

    let result = `${openParen || ""}${semitoneToNote(root + steps)}${quality || ""}`;
    if (bassLetter) {
        const bass = noteToSemitone(bassLetter, bassAccidental, useInternational);
        result += `/${bass === null ? bassLetter + (bassAccidental || "") : semitoneToNote(bass + steps)}`;
    }
    return result + (closeParen || "");
}

function transposeChordLine(line, steps, useInternational) {
    const parts = line.split(/([\s,]+)/);
    let pendingWidthChange = 0;

    return parts.map(part => {
        if (/^[\s,]+$/.test(part)) {
            if (pendingWidthChange > 0) {
                const removable = Math.min(pendingWidthChange, Math.max(part.length - 1, 0));
                part = part.slice(0, part.length - removable);
            } else if (pendingWidthChange < 0) {
                part += " ".repeat(-pendingWidthChange);
            }
            pendingWidthChange = 0;
            return part;
        }

        if (/^(®:|®|:|\/:|:\/|-|x[0-9]+)$/i.test(part)) return part;
        const transposed = transposeChordToken(part, steps, useInternational);
        pendingWidthChange = transposed.length - part.length;
        return transposed;
    }).join("");
}

function transposeContent(content, steps) {
    const source = String(content ?? "");
    const useInternational = detectUseInternationalNotation(source);
    return source.split(/\r?\n/).map(line => {
        if (isChordLine(line)) return transposeChordLine(line, steps, useInternational);
        return line.replace(/\{([^}]+)\}/g, (_, chords) => `{${transposeChordLine(chords, steps, useInternational)}}`);
    }).join("\n");
}

function isChordLine(line) {
    const tokens = String(line ?? "").split(/[\s,]+/).filter(Boolean);
    return tokens.length > 0 && tokens.every(token => CHORD_LINE_TOKEN_REGEX.test(token));
}

function renderSong(content) {
    return String(content ?? "").split(/\r?\n/).map(line => {
        if (isChordLine(line)) return `<strong>${escapeHtml(line)}</strong>`;

        return escapeHtml(line)
            .replace(/®/g, "<strong>®</strong>")
            .replace(/\{([^}]+)\}/g, (_, chords) => ` <strong>${chords}</strong> `);
    }).join("\n");
}

function applyTranspose(element, steps) {
    if (!element) return;
    if (element.dataset.rawContent === undefined) element.dataset.rawContent = element.textContent;

    element.dataset.transposeSteps = String(steps);
    element.innerHTML = renderSong(transposeContent(element.dataset.rawContent, steps));
    document.querySelectorAll("[data-transpose-label]").forEach(label => {
        label.textContent = steps > 0 ? `+${steps}` : String(steps);
    });
}

function enhanceSongView() {
    const element = document.getElementById("song-content-js");
    if (!element) return;
    element.dataset.rawContent = element.textContent;
    applyTranspose(element, 0);
}

function enhanceSongDiv(element) {
    if (!element) return;
    element.dataset.rawContent = element.textContent;
    applyTranspose(element, 0);
}

function initAutoScroll() {
    const button = document.getElementById("auto-scroll-toggle");
    const speedInput = document.getElementById("auto-scroll-speed");
    if (!button || !speedInput || button.dataset.initialized === "1") return;

    const storageKey = "songbook.autoScrollSpeed";
    const savedSpeed = Number(localStorage.getItem(storageKey));
    if (savedSpeed >= 1 && savedSpeed <= 5) speedInput.value = String(savedSpeed);

    let frameId = null;
    let previousTime = null;

    const stop = () => {
        if (frameId !== null) cancelAnimationFrame(frameId);
        frameId = null;
        previousTime = null;
        button.textContent = "Start auto-scroll";
        button.setAttribute("aria-pressed", "false");
    };

    const tick = time => {
        if (previousTime !== null) {
            const pixelsPerSecond = 12 + Number(speedInput.value) * 12;
            window.scrollBy(0, pixelsPerSecond * (time - previousTime) / 1000);
            const atBottom = window.scrollY + window.innerHeight >= document.documentElement.scrollHeight - 1;
            if (atBottom) return stop();
        }
        previousTime = time;
        frameId = requestAnimationFrame(tick);
    };

    button.addEventListener("click", () => {
        if (frameId !== null) return stop();
        button.textContent = "Pause auto-scroll";
        button.setAttribute("aria-pressed", "true");
        frameId = requestAnimationFrame(tick);
    });
    speedInput.addEventListener("input", () => localStorage.setItem(storageKey, speedInput.value));
    document.addEventListener("visibilitychange", () => {
        if (document.hidden) stop();
    });
    button.dataset.initialized = "1";
}

if (typeof document !== "undefined") {
    document.addEventListener("click", event => {
        const button = event.target.closest("[data-transpose]");
        if (!button) return;

        const element = document.getElementById("song-content-js");
        if (!element) return;
        let steps = Number.parseInt(element.dataset.transposeSteps || "0", 10);
        if (button.dataset.transpose === "up") steps++;
        if (button.dataset.transpose === "down") steps--;
        if (button.dataset.transpose === "reset") steps = 0;
        applyTranspose(element, steps);
    });
}

function adjustFontSizeToFitWidth(element) {
    if (!element) return;

    const style = window.getComputedStyle(element);
    let fontSize = Number.parseInt(style.fontSize, 10);
    const measurement = document.createElement("span");
    measurement.style.cssText = "position:absolute;visibility:hidden;white-space:nowrap";
    measurement.style.fontFamily = style.fontFamily;
    measurement.style.fontWeight = style.fontWeight;
    document.body.appendChild(measurement);

    while (fontSize >= 8) {
        measurement.style.fontSize = `${fontSize}px`;
        const fits = element.innerText.split(/\r?\n/).every(line => {
            measurement.textContent = line;
            return measurement.offsetWidth <= element.clientWidth;
        });
        if (fits) break;
        fontSize--;
    }

    element.style.fontSize = `${fontSize}px`;
    measurement.remove();
}

if (typeof module !== "undefined" && module.exports) {
    module.exports = {
        escapeHtml,
        isChordLine,
        renderSong,
        transposeChordToken,
        transposeContent
    };
}
