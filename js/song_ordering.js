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
  // Split into lines and trim trailing spaces on each line
  const lines = (song.content?.split('\n') || []).map(line => line.replace(/\s+$/, ''));

  if (lines.length <= 8) {
    return {
      ...song,
      left_content: lines.join('\n'),
      right_content: null,
    };
  }

  const maxLinePixelWidth = 400;
  const anyTooLong = lines.some(line => measureTextWidth(line) > maxLinePixelWidth);
  if (anyTooLong) {
    console.log(song.title);
    return {
      ...song,
      left_content: lines.join('\n'),
      right_content: null,
    };
  }

  const totalLines = lines.length;
  let splitIndex = -1;

  // Look for empty line between 30% and 60% of the way through the song
  const minSplit = Math.floor(totalLines * 0.3);
  const maxSplit = Math.floor(totalLines * 0.6);

  for (let i = maxSplit; i >= minSplit; i--) {
    if (lines[i]?.trim() === '') {
      splitIndex = i;
      break;
    }
  }

  // If no good split point found, don't split
  if (splitIndex === -1) {
    return {
      ...song,
      left_content: lines.join('\n'),
      right_content: null,
    };
  }

  // Slice right side lines
  let rightLines = lines.slice(splitIndex);

  // Remove leading empty lines from rightLines
  while (rightLines.length && rightLines[0].trim() === '') {
    rightLines.shift();
  }

  return {
    ...song,
    left_content: lines.slice(0, splitIndex).join('\n'),
    right_content: rightLines.join('\n'),
  };
}

function measureHeight(text) {
  // This should use your existing hidden container 'measurer' element
  measurer.textContent = text;
  return measurer.getBoundingClientRect().height;
}


function splitPageIfNeeded(song, container, maxHeight) {
  if (song.right_content) return [song];

  const lines = (song.left_content || song.content || '').split('\n').map(line => line.replace(/\s+$/, ''));

  if (lines.length === 0) return [song];

  const fullHeight = measureHeight(lines.join('\n'), container);
  if (fullHeight <= maxHeight) {
    return [song];
  }

  let splitIndex = lines.length;
  for (let i = Math.floor(lines.length / 2); i < lines.length; i++) {
    const leftLines = lines.slice(0, i);
    const leftHeight = measureHeight(leftLines.join('\n'), container);
    if (leftHeight > maxHeight) {
      splitIndex = i - 1;
      break;
    }
  }

  if (splitIndex <= 0) splitIndex = 1;

  let rightLines = lines.slice(splitIndex);
  while (rightLines.length && rightLines[0].trim() === '') {
    rightLines.shift();
  }

  return [
    {
      ...song,
      left_content: lines.slice(0, splitIndex).join('\n'),
      right_content: null,
    },
    {
      ...song,
      left_content: rightLines.join('\n'),
      right_content: null,
    },
  ];
}






const LINES_PER_PAGE = 45; // Adjust as needed

function estimateSongLines(song) {
  const leftLines = song.left_content?.split('\n').length || 0;
  const rightLines = song.right_content?.split('\n').length || 0;
  return {
    leftLines,
    rightLines,
    totalLines: leftLines + rightLines
  };
}

function groupSongsByPages(songs) {
  const pages = [];
  let currentPage = [];
  let currentLines = 0;

  for (const song of songs) {
    const { leftLines, rightLines } = estimateSongLines(song);

    // Try to fit left part
    if (currentLines + leftLines > LINES_PER_PAGE) {
      if (currentPage.length) pages.push(currentPage);
      currentPage = [];
      currentLines = 0;
    }
    currentPage.push({ ...song, part: "left" });
    currentLines += leftLines;

    // Try to fit right part (if it exists)
    if (song.right_content) {
      if (currentLines + rightLines > LINES_PER_PAGE) {
        if (currentPage.length) pages.push(currentPage);
        currentPage = [];
        currentLines = 0;
      }
      currentPage.push({ ...song, part: "right" });
      currentLines += rightLines;
    }
  }

  if (currentPage.length) pages.push(currentPage);
  return pages.flat(); // Flatten pages back into a list
}

function optimizeSongOrder(songs) {
  const grouped = groupSongsByArtist(songs);

  const authorBlocks = grouped.map(group => {
    const splitSongs = group.map(song => splitSongIfNeeded(song));
    const filledPages = groupSongsByPages(splitSongs);

    return {
      songs: filledPages,
      slack: filledPages.reduce((sum, song) => {
        const lines = estimateSongLines(song);
        return sum + lines.totalLines;
      }, 0) % LINES_PER_PAGE
    };
  });

  // Sort author blocks: tight fills in middle, slacky fills on edges
  authorBlocks.sort((a, b) => a.slack - b.slack);

  // Place most efficient (low-slack) authors in the middle
  const centerOutOrder = [];
  let left = 0;
  let right = authorBlocks.length - 1;
  let toggle = true;
  while (left <= right) {
    if (toggle) {
      centerOutOrder.push(authorBlocks[left++]);
    } else {
      centerOutOrder.unshift(authorBlocks[right--]);
    }
    toggle = !toggle;
  }

  // Flatten ordered song blocks
  const ordered = centerOutOrder.flatMap(block => block.songs);

  return ordered;
}

