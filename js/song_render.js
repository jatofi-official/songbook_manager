function isChordLine(line) {
    const chordToken = "\\(?[A-GH](#|b)?(maj|min|mi|m|dim|aug|sus|add)?[0-9]*(7|9|11|13)?[\\+\\-]?\\)?(\\/([A-GH](#|b)?))?";
    const allowedSymbols = "(®:|®|:|/:|:/)";
    const dashToken = "-";
    const tokenPatternStr = `^(${chordToken}|${allowedSymbols}|${dashToken})$`;
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

    const raw = el.textContent;
    el.innerHTML = renderSong(raw);
}

function enhanceSongDiv(el) {
    if (!el) return;

    const raw = el.textContent;
    el.innerHTML = renderSong(raw);
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



