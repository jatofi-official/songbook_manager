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
    let scrollRemainder = 0;

    const stop = () => {
        if (frameId !== null) cancelAnimationFrame(frameId);
        frameId = null;
        previousTime = null;
        scrollRemainder = 0;
        toggle.textContent = "Start auto-scroll";
        toggle.setAttribute("aria-pressed", "false");
    };

    const scrollFrame = (time) => {
        if (previousTime !== null) {
            const pixelsPerSecond = 6 + (Number(speedInput.value) - 1) * 15;
            // scrollBy rounds/truncates to whole pixels, so at low speeds the
            // per-frame amount (well under 1px) would otherwise be dropped
            // every frame and the page would never move. Accumulate the
            // fractional remainder across frames instead.
            const distance = pixelsPerSecond * (time - previousTime) / 1000 + scrollRemainder;
            const wholePixels = Math.trunc(distance);
            scrollRemainder = distance - wholePixels;
            if (wholePixels !== 0) {
                window.scrollBy(0, wholePixels);
            }
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

// Keeps Unicode letters/diacritics (e.g. "Škoda", "café") intact and only
// strips characters that are actually illegal in filenames, instead of the
// old ASCII-only allowlist that mangled any non-English title into hyphens.
function sanitizeFilename(name, fallback = "song") {
    const cleaned = String(name ?? "")
        .normalize("NFC")
        .replace(/[<>:"/\\|?*\x00-\x1F]/g, "")
        .trim()
        .replace(/\s+/g, " ");
    return cleaned || fallback;
}

async function fetchSongForExport(songId) {
    const res = await fetch("php/get_songs_by_ids_extended.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ ids: [songId] })
    });
    const songs = await res.json();
    return songs[0] || null;
}

// Renders the single song through the exact same A4-page pipeline used for
// full songbooks (js/songbook_render_preview.js + js/song_ordering.js) into
// the hidden #a4-preview-container, then exports it with the same
// html2canvas/jsPDF routine, instead of screenshotting the visible page.
async function exportSongToPDFViaA4Renderer(songId, options, button) {
    const container = document.getElementById("a4-preview-container");
    if (!container || typeof renderA4Preview !== "function" || typeof doExportLogic !== "function") {
        console.error("A4 renderer is not available on this page.");
        return;
    }

    const originalLabel = button.textContent;
    button.disabled = true;
    button.textContent = "Generating PDF...";

    try {
        const song = await fetchSongForExport(songId);
        if (!song) throw new Error("Song not found");

        const title = song.title || "song";
        container.innerHTML = "";
        await renderA4Preview([song], [song.id], {
            category: title,
            blackAndWhite: options.blackAndWhite,
            showDifficulty: options.showDifficulty,
            showTags: options.showTags,
            showInstruments: options.showInstruments,
            padToEvenPages: false
        });

        const filename = `${sanitizeFilename(title)}.pdf`;
        await doExportLogic(filename);

        const modalEl = document.getElementById("songPdfOptionsModal");
        if (modalEl) bootstrap.Modal.getInstance(modalEl)?.hide();
    } catch (error) {
        console.error("PDF export failed", error);
        alert("Failed to export PDF. See console for details.");
    } finally {
        container.innerHTML = "";
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

// Font stacks for the non-monospace song-font choices in user_settings.php
// (song_font DB column / $_SESSION['song_font']). 'monospace' isn't listed
// here - it keeps the original <pre>-based renderSong() output untouched.
// Keys must match the whitelist in php/update_preferences.php,
// php/subpage/get_song.php and php/functions.php (load_user_settings_into_session)
// exactly, and each stack should match its .song-font-<key> CSS rule in
// styles/songs_styles.css (that rule is only the pre-JS/no-JS fallback look -
// this is what actually renders once JS takes over).
const SONG_FONT_STACKS = {
    calibri: "Calibri, 'Segoe UI', Candara, Verdana, sans-serif",
    corbel: "Corbel, Candara, 'Segoe UI', Calibri, sans-serif",
    candara: "Candara, Corbel, 'Segoe UI', Calibri, sans-serif",
    arial: "Arial, Helvetica, sans-serif",
    verdana: "Verdana, Geneva, sans-serif",
    georgia: "Georgia, 'Times New Roman', serif",
    times: "'Times New Roman', Times, serif"
};

// How much bigger than the surrounding text the ® chorus-marker symbol
// renders (see chord_font_layout.js's renderLyricRow/renderChordRow,
// isMarkerToken) - a single fixed multiplier doesn't work here because
// fonts don't agree on how big their OWN ® glyph already is: Calibri's is
// famously tiny (close to a subscript), so it needs a big boost to read
// clearly, while Verdana's is already a large, bold, easily-legible glyph -
// applying Calibri's boost to it made ® comically oversized. 1 (no boost)
// is the safe default for an unlisted font, since UNDER-sizing ® is a much
// smaller problem (still legible, just a bit small) than over-sizing it.
const SONG_FONT_MARKER_SCALE = {
    calibri: 1.5,
    corbel: 1.5,
    candara: 1.5,
    arial: 1.15,
    verdana: 1,
    georgia: 1.3,
    times: 1.3
};

// A fixed font-size scale per font (relative to the A4 renderer's own base
// size - A4_FONT_SIZE_PX, songbook_render_preview.js), used INSTEAD of
// measuring each song/column's own content and picking whatever scale just
// barely fits it (see buildSongBodyElement/buildTwoColumnSongBodies,
// songbook_render_preview.js - the previous approach). That per-chunk
// measurement made every song, and even the two columns of the SAME song,
// potentially render at a different size from one another depending on how
// dense their own content happened to be - individually correct, but
// visually inconsistent across a songbook. A flat, pre-chosen number per
// font is simple and always consistent instead. All 1 (full size, no
// pre-shrink) - proportional fonts are generally already narrower per
// character than a fixed-pitch monospace font (which reserves the same
// width for every character, including narrow ones like "i"), so most
// content shouldn't need shrinking below the page's own font-size at all.
// computeBodyFontScale (songbook_render_preview.js) is still consulted as a
// safety cap below this value - not to pre-shrink by default, but so a
// genuinely too-wide/tall chunk (an unusually long line, a wide font like
// Verdana on a dense line) still shrinks just that one chunk instead of
// overflowing the page. Tune a font's own number down from 1 only if real
// songbook content shows it consistently needs the safety cap anyway.
const SONG_FONT_FIXED_SCALE = {
    calibri: 1.2,
    corbel: 1.2,
    candara: 1,
    arial: 1,
    verdana: 1.1,
    georgia: 1,
    times: 1
};

// Renders proportional-font-safe HTML into `el` via chord_font_layout.js:
// chords get repositioned by measured text width (not column count) so they
// stay above the right point in the lyrics.
//
// Deliberately does NOT call chord_font_layout.js's computeFontScale here to
// shrink the font down to the original monospace line width - that
// constraint exists for the (separate, not-yet-touched) songbook/PDF
// renderer, which lays out fixed-size printed pages where a wider line
// would overflow. song.php has no such fixed budget; adjustFontSizeToFitWidth()
// (called right after this, in song.php) already handles real overflow
// based on the actual browser container width. Applying the monospace
// budget here just meant proportionally-wide fonts (Georgia, Times New
// Roman) got squeezed down further than the page ever needed - small enough
// that browser font hinting (glyph stems snapping to whole device pixels
// for crisper small-text rendering) started visibly distorting chord
// alignment, since hinting makes the actually-rendered text subtly
// different from its mathematically "true" proportional width - no amount
// of measurement precision can predict or correct for that, only avoiding
// the unnecessary shrink in the first place does.
function renderProportionalInto(el, content, fontKey) {
    const fontFamily = SONG_FONT_STACKS[fontKey] || SONG_FONT_STACKS.calibri;
    const baseFontSize = parseFloat(window.getComputedStyle(el).fontSize) || 16;

    // Still needed (unrelated to the shrink question above) to extrapolate
    // chord positions past the end of a short/absent lyric line - see
    // chord_font_layout.js measurePrefixWidth.
    const monoCharWidth = createDomMeasurer("monospace", baseFontSize)("M", false);

    const measure = createDomMeasurer(fontFamily, baseFontSize);
    const markerScale = SONG_FONT_MARKER_SCALE[fontKey] ?? 1;
    el.innerHTML = renderSongProportional(content, monoCharWidth, measure, markerScale);
    el.style.fontFamily = fontFamily;
    el.style.fontSize = baseFontSize + "px";
    // Line breaks are now real block-level rows (.chord-row/.lyric-row), not
    // literal "\n" characters relying on pre-wrap to render as line breaks.
    el.style.whiteSpace = "normal";
}

function applyTranspose(el, steps) {
    if (!el) return;
    if (el.dataset.rawContent === undefined) {
        el.dataset.rawContent = el.textContent;
    }
    el.dataset.transposeSteps = String(steps);

    const transposed = transposeContent(el.dataset.rawContent, steps);
    const songFont = el.dataset.songFont;

    if (songFont && songFont !== "monospace" && SONG_FONT_STACKS[songFont]) {
        renderProportionalInto(el, transposed, songFont);
    } else {
        el.style.removeProperty("font-family");
        el.style.removeProperty("font-size");
        el.style.whiteSpace = "pre-wrap";
        el.innerHTML = renderSong(transposed);
    }

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
        if (!button) return;
        const modalEl = document.getElementById("songPdfOptionsModal");
        if (!modalEl) return;
        // The trigger button carries the id of whichever song is currently
        // shown. On pages like songs.php the modal is a single shared
        // element while the embedded song detail changes via AJAX, so the
        // id has to be captured here rather than baked into the modal markup.
        modalEl.dataset.songId = button.dataset.songId || "";
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    });

    document.addEventListener("click", function (e) {
        const confirmBtn = e.target.closest("#song-pdf-options-confirm");
        if (!confirmBtn) return;
        const modalEl = document.getElementById("songPdfOptionsModal");
        const songId = parseInt(modalEl?.dataset.songId, 10);
        const options = {
            showTags: document.getElementById("song-pdf-opt-tags")?.checked || false,
            showInstruments: document.getElementById("song-pdf-opt-instruments")?.checked || false,
            showDifficulty: document.getElementById("song-pdf-opt-difficulty")?.checked || false,
            blackAndWhite: document.getElementById("song-pdf-opt-bw")?.checked || false
        };
        exportSongToPDFViaA4Renderer(songId, options, confirmBtn)
            .catch(error => console.error("PDF export failed", error));
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



