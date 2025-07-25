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
  const lines = (song.content?.split('\n') || []).map(line => line.replace(/\s+$/, ''));

  if (lines.length <= 8) {
    return {
      ...song,
      left_content: lines.join('\n'),
      right_content: null,
    };
  }

  const maxLinePixelWidth = 450;
  const extraTolerance = 50;

  const anyTooLong = lines.some(line => {
    const width = measureTextWidth(line);
    return isChordLine(line) && width > maxLinePixelWidth;
  });

  const absurdlyTooLong = lines.some(line => measureTextWidth(line) > maxLinePixelWidth + extraTolerance);

  if (anyTooLong || absurdlyTooLong) {
    return {
      ...song,
      left_content: lines.join('\n'),
      right_content: null,
    };
  }

  const totalLines = lines.length;
  const minSplit = Math.floor(totalLines * 0.3);
  const maxSplit = Math.floor(totalLines * 0.7);

  let bestSplitIndex = null;
  let bestAdjustedHeight = Infinity;

  let firstChorusIndex = lines.findIndex(line =>
    line.trim().startsWith('®') || line.trim().startsWith('Ruty')
  );

  console.log(`--- Analyzing: ${song.title} (${totalLines} lines)`);

  for (let i = minSplit; i <= maxSplit; i++) {
    const line = lines[i];
    const isEmpty = line?.trim() === '';
    const isChorus = line?.trim().startsWith('®') || line?.trim().startsWith('Ruty');

    if (isEmpty || isChorus) {
      let leftLen = i;
      let rightLen = totalLines - i;
      let height = Math.max(leftLen, rightLen);

      // 🎯 Adjust score to prefer chorus on left
      const chorusGoesRight = firstChorusIndex !== -1 && i <= firstChorusIndex;
      const adjustmentFactor = chorusGoesRight ? 1.1 : 1.0; // 10% penalty
      const adjustedHeight = height * adjustmentFactor;

      console.log(`  Split at ${i}: L=${leftLen} R=${rightLen} Max=${height} ${chorusGoesRight ? '🚫 Chorus moves right (+10%)' : '✅ Chorus stays left'}`);

      if (
        adjustedHeight < bestAdjustedHeight ||
        (adjustedHeight === bestAdjustedHeight && (bestSplitIndex === null || i < bestSplitIndex))
      ) {
        bestAdjustedHeight = adjustedHeight;
        bestSplitIndex = i;
      }
    }
  }

  if (bestSplitIndex === null) {
    console.log(`  No good split found for: ${song.title}`);
    return {
      ...song,
      left_content: lines.join('\n'),
      right_content: null,
    };
  }

  // 🎸 Handle chord line before chorus
  let offset = 0;
  const currentLine = lines[bestSplitIndex];
  const prevLine = lines[bestSplitIndex - 1];

  const isChorus = currentLine.trim().startsWith('®') || currentLine.trim().startsWith('Ruty');
  const chordAbove = prevLine && isChordLine(prevLine);

  if (isChorus && chordAbove) {
    console.log(`  🎸 Moving chord line with chorus at split ${bestSplitIndex}`);
    offset = -1;
  }

  const finalSplit = bestSplitIndex + offset;

  console.log(`  ✅ Final split at line ${finalSplit} (L=${finalSplit}, R=${totalLines - finalSplit})`);

  let leftLines = lines.slice(0, finalSplit);
  let rightLines = lines.slice(finalSplit);

  while (leftLines.length && leftLines[leftLines.length - 1].trim() === '') leftLines.pop();
  while (rightLines.length && rightLines[0].trim() === '') rightLines.shift();

  return {
    ...song,
    left_content: leftLines.join('\n'),
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
  if (fullHeight <= maxHeight) return [song];

  // Step 1: Find all empty lines that could be good split points
  let bestSplit = -1;

  for (let i = 1; i < lines.length; i++) {
    if (lines[i].trim() !== '') continue; // not an empty line

    const testLines = lines.slice(0, i + 1); // include the empty line
    const testHeight = measureHeight(testLines.join('\n'), container);

    if (testHeight <= maxHeight) {
      bestSplit = i + 1; // split after the empty line
    } else {
      break; // further lines will only be taller
    }
  }

  // Step 2: Fallback — if no good empty line split found, do original logic
  if (bestSplit === -1) {
    // Binary search fallback
    bestSplit = lines.length;
    for (let i = Math.floor(lines.length / 2); i < lines.length; i++) {
      const leftLines = lines.slice(0, i);
      const leftHeight = measureHeight(leftLines.join('\n'), container);
      if (leftHeight > maxHeight) {
        bestSplit = i - 1;
        break;
      }
    }
  }

  if (bestSplit <= 0) bestSplit = 1;

  // Trim leading empty lines on the right part
  let rightLines = lines.slice(bestSplit);
  while (rightLines.length && rightLines[0].trim() === '') {
    rightLines.shift();
  }

    return [
    {
        ...song,
        left_content: lines.slice(0, bestSplit).join('\n'),
        right_content: null,
    },
    {
        ...song,
        left_content: rightLines.join('\n'),
        right_content: null,
        continuation: true, // 👈 NEW FLAG
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

