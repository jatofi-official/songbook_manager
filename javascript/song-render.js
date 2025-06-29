function isChordLine(line) {
    const chordToken = "\\(?[A-GH](#|b)?(mi|m|dim|aug|sus|add)?[0-9]*[\\+\\-]?\\)?";
    const allowedSymbols = "[®:]+";
    const fullPatternStr = `^\\s*(${chordToken}|${allowedSymbols})(\\s+(${chordToken}|${allowedSymbols}))*\\s*$`;
    const fullPattern = new RegExp(fullPatternStr, 'i');

    return fullPattern.test(line.trim());
}


function renderSong(content) {
    const lines = content.split(/\r?\n/);
    return lines.map(line => {
        if (isChordLine(line)) {
            // whole line bold
            return `<strong>${line}</strong>`;
        } else {
            // only bold the ® symbols in the text
            return line.replace(/®/g, '<strong>®</strong>');
        }
    }).join('\n');
}


function enhanceSongView() {
    const el = document.getElementById("song-content-js");
    if (!el) {
        console.log("enhanceSongView: element #song-content-js not found!");
        return;
    }

    const raw = el.textContent;
    el.innerHTML = renderSong(raw);
}
