// Create off-screen span for measuring text width
// Setup only once
const measurer = document.createElement('span');
measurer.style.position = 'absolute';
measurer.style.visibility = 'hidden';
measurer.style.whiteSpace = 'pre';
measurer.style.fontFamily = 'Fira Mono, monospace'; // Match preview
measurer.style.fontSize = '14px';                   // Match preview font size
measurer.style.lineHeight = '22px';                 // Match preview line height
document.body.appendChild(measurer);

// Returns pixel width of a line in Fira Mono
function measureTextWidth(line) {
  measurer.textContent = line.replace(/\s+$/, '');
  return measurer.getBoundingClientRect().width;
}

// Group songs by artist
function groupSongsByArtist(songs) {
    const map = new Map();

    songs.forEach(song => {
        const artist = song.artist?.trim() || "Unknown";
        if (!map.has(artist)) {
            map.set(artist, []);
        }
        map.get(artist).push(song);
    });

    return Array.from(map.values());
}

function splitSongIfNeeded(song) {
  const lines = song.content?.split('\n') || [];

  // Condition 1: Only split if more than 8 lines
  if (lines.length <= 8) {
    return {
      ...song,
      left_content: song.content,
      right_content: null,
    };
  }

  // Condition 2: Longest line must fit in half-page column (by pixel width)
  const maxLinePixelWidth = 360; // Approximate half of usable A4 width (adjust as needed)
  let longestLineWidth = 0;

  for (const line of lines) {
    const trimmed = line.replace(/\s+$/, '');
    const lineWidth = measureTextWidth(trimmed);
    if (lineWidth > longestLineWidth) {
      longestLineWidth = lineWidth;
    }
  }

  if (longestLineWidth > maxLinePixelWidth) {
    return {
      ...song,
      left_content: song.content,
      right_content: null,
    };
  }

  // Condition 3: Find a good empty line near the middle
  const mid = Math.floor(lines.length / 2);
  let splitIndex = -1;
  for (let offset = 0; offset <= mid; offset++) {
    if (lines[mid - offset]?.trim() === '') {
      splitIndex = mid - offset;
      break;
    }
    if (lines[mid + offset]?.trim() === '') {
      splitIndex = mid + offset;
      break;
    }
  }

  if (splitIndex === -1) {
    return {
      ...song,
      left_content: song.content,
      right_content: null,
    };
  }

  // Return with left/right content
  return {
    ...song,
    left_content: lines.slice(0, splitIndex).join('\n'),
    right_content: lines.slice(splitIndex).join('\n'),
  };
}




// Main function to order songs
function optimizeSongOrder(songs) {
    const grouped = groupSongsByArtist(songs);
    const ordered = [];

    grouped.forEach(group => {
        group.forEach(song => {
            const splitSong = splitSongIfNeeded(song);
            ordered.push(splitSong);
        });
    });

    return ordered;
}
