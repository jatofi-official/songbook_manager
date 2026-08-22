// Central European (Czech/Slovak) chord notation uses "H" for natural B and
// "B" for A#/Bb. If a song also contains an explicit "Bb" chord, that song is
// using international notation instead, so plain "B" then means natural B.
const CHORD_NOTE_NAMES_CZ = ["C", "C#", "D", "D#", "E", "F", "F#", "G", "G#", "A", "B", "H"];
const CHORD_NOTE_NAMES_INTL = ["C", "C#", "D", "D#", "E", "F", "F#", "G", "G#", "A", "A#", "B"];
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

function transposeChordToken(token, steps, useInternational) {
    const m = token.match(CHORD_TOKEN_REGEX);
    if (!m) return token;
    const [, openParen, rootLetter, rootAcc, quality, bassLetter, bassAcc, closeParen] = m;

    // useInternational only affects how the ambiguous input "B" is interpreted;
    // output is always respelled in Czech/Slovak notation (B = A#/Bb, H = natural B).
    const rootSemi = noteToSemitone(rootLetter, rootAcc, useInternational);
    if (rootSemi === null) return token;

    let result = (openParen || "") + semitoneToNote(rootSemi + steps, false) + (quality || "");

    if (bassLetter) {
        const bassSemi = noteToSemitone(bassLetter, bassAcc, useInternational);
        result += "/" + (bassSemi !== null ? semitoneToNote(bassSemi + steps, false) : bassLetter + (bassAcc || ""));
    }

    return result + (closeParen || "");
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
    const source = String(content ?? "");
    const useInternational = detectUseInternationalNotation(source);
    return source.split(/\r?\n/).map(line => {
        if (isChordLine(line)) {
            return transposeChordLine(line, steps, useInternational);
        }
        return line.replace(/\{([^}]+)\}/g, (match, inner) => "{" + transposeChordLine(inner, steps, useInternational) + "}");
    }).join("\n");
}

function isChordLine(line) {
    const tokens = String(line ?? "").split(/[\s,]+/).filter(Boolean);
    return tokens.length > 0 && tokens.every(token => CHORD_LINE_TOKEN_REGEX.test(token));
}






function renderSong(content) {
  const lines = String(content ?? "").split(/\r?\n/);
  return lines.map(line => {
    if (isChordLine(line)) {
      // whole line bold
      return `<strong>${escapeHtml(line)}</strong>`;
    } else {
      // bold the ® symbols
      // and bold chords inside {...}, replacing braces with a space
      return escapeHtml(line)
        .replace(/®/g, '<strong>®</strong>')
        .replace(/\{([^}]+)\}/g, (match, chordInside) => ` <strong>${chordInside}</strong> `);
    }
  }).join('\n');
}

function initAutoScroll() {
    const toggle = document.getElementById("auto-scroll-toggle");
    const speedInput = document.getElementById("auto-scroll-speed");
    if (!toggle || !speedInput || toggle.dataset.initialized) return;

    const storageKey = "songbook.autoScrollSpeed";
    const savedSpeed = localStorage.getItem(storageKey);
    if (savedSpeed && Number(savedSpeed) >= 1 && Number(savedSpeed) <= 5) {
        speedInput.value = savedSpeed;
    }

    let frameId = null;
    let previousTime = null;

    const stop = () => {
        if (frameId !== null) cancelAnimationFrame(frameId);
        frameId = null;
        previousTime = null;
        toggle.textContent = "Start auto-scroll";
        toggle.setAttribute("aria-pressed", "false");
    };

    const scrollFrame = (time) => {
        if (previousTime !== null) {
            const pixelsPerSecond = 12 + Number(speedInput.value) * 12;
            window.scrollBy(0, pixelsPerSecond * (time - previousTime) / 1000);
        }
        previousTime = time;

        const atBottom = window.scrollY + window.innerHeight >= document.documentElement.scrollHeight - 1;
        if (atBottom) {
            stop();
            return;
        }
        frameId = requestAnimationFrame(scrollFrame);
    };

    toggle.dataset.initialized = "true";
    toggle.addEventListener("click", () => {
        if (frameId !== null) {
            stop();
            return;
        }
        toggle.textContent = "Pause auto-scroll";
        toggle.setAttribute("aria-pressed", "true");
        frameId = requestAnimationFrame(scrollFrame);
    });
    speedInput.addEventListener("input", () => localStorage.setItem(storageKey, speedInput.value));
    document.addEventListener("visibilitychange", () => {
        if (document.hidden) stop();
    });
}

async function exportCurrentSongToPDF(button) {
    const source = document.getElementById("song-display");
    if (!source || !window.html2canvas || !window.jspdf) return;

    const originalLabel = button.textContent;
    button.disabled = true;
    button.textContent = "Generating PDF...";
    const clone = source.cloneNode(true);
    clone.querySelector(".song-header > .d-flex > div:last-child")?.remove();
    clone.querySelectorAll("form, .transpose-controls, .auto-scroll-controls").forEach(element => element.remove());

    const wrapper = document.createElement("div");
    wrapper.style.cssText = "position:absolute;left:-9999px;top:0;width:794px;min-height:1123px;padding:48px;background:#fff;color:#111;box-sizing:border-box";
    wrapper.appendChild(clone);
    document.body.appendChild(wrapper);

    try {
        const canvas = await window.html2canvas(wrapper, { scale: 1.5, useCORS: true, backgroundColor: "#ffffff" });
        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF({ orientation: "portrait", unit: "pt", format: "a4" });
        const width = pdf.internal.pageSize.getWidth();
        const pageHeight = pdf.internal.pageSize.getHeight();
        const height = canvas.height * width / canvas.width;
        const image = canvas.toDataURL("image/jpeg", 0.9);
        for (let offset = 0; offset < height; offset += pageHeight) {
            if (offset > 0) pdf.addPage();
            pdf.addImage(image, "JPEG", 0, -offset, width, height);
        }
        const title = clone.querySelector("h2")?.textContent?.trim() || "song";
        pdf.save(`${title.replace(/[^a-z0-9_-]+/gi, "-").replace(/^-|-$/g, "") || "song"}.pdf`);
    } finally {
        wrapper.remove();
        button.disabled = false;
        button.textContent = originalLabel;
    }
}



function enhanceSongView() {
    const el = document.getElementById("song-content-js");
    if (!el) {
        console.log("enhanceSongView: element #song-content-js not found!");
        return;
    }

    el.dataset.rawContent = el.textContent;
    applyTranspose(el, 0);
    initAutoScroll();
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

if (typeof document !== "undefined") {
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

    document.addEventListener("click", function (e) {
        const btn = e.target.closest("[data-song-history-id]");
        if (!btn) return;

        const modalEl = document.getElementById("songHistoryModal");
        const body = document.getElementById("songHistoryModalBody");
        if (!modalEl || !body) return;

        body.innerHTML = "<p>Loading history...</p>";
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();

        fetch("php/subpage/get_song_history.php?id=" + btn.dataset.songHistoryId)
            .then(response => response.text())
            .then(html => {
                body.innerHTML = html;
            })
            .catch(err => {
                body.innerHTML = "<p>Error loading history.</p>";
                console.error(err);
            });
    });

    document.addEventListener("click", function (e) {
        const button = e.target.closest("[data-export-song-pdf]");
        if (button) exportCurrentSongToPDF(button).catch(error => console.error("PDF export failed", error));
    });
}


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

if (typeof module !== "undefined" && module.exports) {
    module.exports = { escapeHtml, isChordLine, renderSong, transposeChordToken, transposeContent };
}



