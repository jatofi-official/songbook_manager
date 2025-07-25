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

const PAGE_HEIGHT = 1300;




// Returns pixel width of a line in Fira Mono
function measureTextWidth(line) {
  measurer.textContent = line.replace(/\s+$/, '');
  return measurer.getBoundingClientRect().width;
}

function measureHeight(text) {
  measurer.textContent = text;
  return measurer.getBoundingClientRect().height;
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

  const maxLinePixelWidth = 420;
  const extraTolerance = 60;

  const anyTooLong = lines.some(line =>
    isChordLine(line) && measureTextWidth(line) > maxLinePixelWidth
  );

  const absurdlyTooLong = lines.some(line =>
    measureTextWidth(line) > maxLinePixelWidth + extraTolerance
  );

  if (anyTooLong || absurdlyTooLong) {
    return {
      ...song,
      left_content: lines.join('\n'),
      right_content: null,
    };
  }

  const totalLines = lines.length;
  const firstChorusIndex = lines.findIndex(line =>
    line.trim().startsWith('®') || line.trim().startsWith('Ruty')
  );

//   console.log(`--- Analyzing: ${song.title} (${totalLines} lines)`);

  function findBestSplit(startRatio, endRatio) {
    const minSplit = Math.floor(totalLines * startRatio);
    const maxSplit = Math.floor(totalLines * endRatio);

    let bestSplitIndex = null;
    let bestAdjustedHeight = Infinity;

    for (let i = minSplit; i <= maxSplit; i++) {
      const line = lines[i];
      const isEmpty = line?.trim() === '';
      const isChorus = line?.trim().startsWith('®') || line?.trim().startsWith('Ruty');

      if (isEmpty || isChorus) {
        const leftLen = i;
        const rightLen = totalLines - i;
        const height = Math.max(leftLen, rightLen);

        const chorusGoesRight = firstChorusIndex !== -1 && i <= firstChorusIndex;
        const adjustmentFactor = chorusGoesRight ? 1.1 : 1.0; // Penalize chorus going right
        const adjustedHeight = height * adjustmentFactor;

        // console.log(`  Split at ${i}: L=${leftLen} R=${rightLen} Max=${height} ${chorusGoesRight ? '🚫 Chorus moves right (+10%)' : '✅ Chorus stays left'}`);

        if (
          adjustedHeight < bestAdjustedHeight ||
          (adjustedHeight === bestAdjustedHeight && (bestSplitIndex === null || i < bestSplitIndex))
        ) {
          bestAdjustedHeight = adjustedHeight;
          bestSplitIndex = i;
        }
      }
    }

    return bestSplitIndex;
  }

  // Phase 1: Try middle 50–60%
  let splitIndex = findBestSplit(0.5, 0.6);

  // Phase 2: If nothing found, try wider 30–70%
  if (splitIndex === null) {
    // console.log(`  No split found between 50–60%, trying 30–70%...`);
    splitIndex = findBestSplit(0.3, 0.7);
  }

  if (splitIndex === null) {
    // console.log(`  ❌ No suitable split point found for: ${song.title}`);
    return {
      ...song,
      left_content: lines.join('\n'),
      right_content: null,
    };
  }

  // Adjust for chord line before chorus
  let offset = 0;
  const currentLine = lines[splitIndex];
  const prevLine = lines[splitIndex - 1];

  const isChorus = currentLine.trim().startsWith('®') || currentLine.trim().startsWith('Ruty');
  const chordAbove = prevLine && isChordLine(prevLine);

  if (isChorus && chordAbove) {
    // console.log(`  🎸 Moving chord line with chorus at split ${splitIndex}`);
    offset = -1;
  }

  const finalSplit = splitIndex + offset;

//   console.log(`  ✅ Final split at line ${finalSplit} (L=${finalSplit}, R=${totalLines - finalSplit})`);

  let leftLines = lines.slice(0, finalSplit);
  let rightLines = lines.slice(finalSplit);

  // Trim blank lines
  while (leftLines.length && leftLines[leftLines.length - 1].trim() === '') leftLines.pop();
  while (rightLines.length && rightLines[0].trim() === '') rightLines.shift();

  return {
    ...song,
    left_content: leftLines.join('\n'),
    right_content: rightLines.join('\n'),
  };
}



function splitPageIfNeeded(song, container) {
  if (song.right_content) return [song];

  const lines = (song.left_content || song.content || '').split('\n').map(line => line.replace(/\s+$/, ''));
  if (lines.length === 0) return [song];

  const fullHeight = measureHeight(lines.join('\n'), container);
  if (fullHeight <= PAGE_HEIGHT) return [song];

  // Step 1: Find all empty lines that could be good split points
  let bestSplit = -1;

  for (let i = 1; i < lines.length; i++) {
    if (lines[i].trim() !== '') continue; // not an empty line

    const testLines = lines.slice(0, i + 1); // include the empty line
    const testHeight = measureHeight(testLines.join('\n'), container);

    if (testHeight <= PAGE_HEIGHT) {
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
      if (leftHeight > PAGE_HEIGHT) {
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

// Creates shared song container
function createHiddenMeasureContainer() {
  const container = document.createElement('div');
  container.style.position = 'absolute';
  container.style.visibility = 'hidden';
  container.style.width = '794px';
  container.style.fontFamily = 'Fira Mono, monospace';
  container.style.fontSize = '14px';
  container.style.lineHeight = '22px';
  container.style.whiteSpace = 'pre-wrap';
  container.style.padding = '32px';
  container.style.boxSizing = 'border-box';
  container.style.zIndex = '-1';
  return container;
}

function measureSongHeight(pages, container) {
  let totalHeight = 0;

  pages.forEach(page => {
    const songDiv = document.createElement('div');
    songDiv.className = page.right_content ? 'song-block split-song mb-4' : 'song-block mb-4';
    songDiv.style.border = '1px dashed red';
    songDiv.style.backgroundColor = '#fdfdfd';
    songDiv.style.display = 'flex';
    songDiv.style.flexDirection = 'column';

    // Add title/artist header unless it's a continuation
    if (!page.continuation) {
      const header = document.createElement('div');
      header.innerHTML = `<strong>${page.title}</strong> <em>${page.artist}</em>`;
      header.style.marginBottom = '10px';
      songDiv.appendChild(header);
    }

    if (page.right_content) {
      const columns = document.createElement('div');
      columns.className = 'd-flex';

      const leftCol = document.createElement('div');
      leftCol.className = 'left-column flex-grow-1 pe-3';
      const leftPre = document.createElement('pre');
      leftPre.textContent = page.left_content;
      leftPre.style.margin = 0;
      leftCol.appendChild(leftPre);

      const rightCol = document.createElement('div');
      rightCol.className = 'right-column flex-grow-1 ps-3';
      const rightPre = document.createElement('pre');
      rightPre.textContent = page.right_content;
      rightPre.style.margin = 0;
      rightCol.appendChild(rightPre);

      columns.appendChild(leftCol);
      columns.appendChild(rightCol);
      songDiv.appendChild(columns);
    } else {
      const pre = document.createElement('pre');
      pre.textContent = page.left_content || page.content || '';
      pre.style.margin = 0;
      songDiv.appendChild(pre);
    }

    container.appendChild(songDiv);
    const height = songDiv.getBoundingClientRect().height;
    container.removeChild(songDiv);
    totalHeight += height;
  });

  return totalHeight;
}


function orderSongs(songs, container) {
  const grouped = groupSongsByArtist(songs);

  // Measure and order songs inside each artist group
  const measuredArtistGroups = grouped.map(group => {
    const measuredSongs = group.map(song => {
      const splitSong = splitSongIfNeeded(song);
      const pages = splitPageIfNeeded(splitSong, container);
      const totalHeight = measureSongPages(pages, container);  // your existing function

      return {
        id: song.id,
        artist: song.artist?.trim() || "Unknown",
        height: totalHeight,
        pages: pages.length,
        twoPages: pages.length > 1,
        originalSong: song,
      };
    });

    const orderedGroup = orderSongGroup(measuredSongs);

    const firstSongHeight = orderedGroup.length > 0 ? orderedGroup[0].height : 0;
    const lastSongHeight = orderedGroup.length > 1 ? orderedGroup[orderedGroup.length - 1].height : firstSongHeight;

    return {
      artist: orderedGroup.length > 0 ? orderedGroup[0].artist : "Unknown",
      songs: orderedGroup,
      firstSongHeight,
      lastSongHeight,
    };
  });

  // Order artist groups by edges
  const orderedArtistGroups = orderArtistsByEdges(measuredArtistGroups, PAGE_HEIGHT);

  // Flatten final ordered song list
  const finalOrderedList = [];
  orderedArtistGroups.forEach(artistGroup => {
    finalOrderedList.push(...artistGroup.songs);
  });

  return finalOrderedList;
}


function measureSongPages(pages, container) {
  let totalHeight = 0;

  pages.forEach((page, index) => {
    const songDiv = document.createElement('div');
    songDiv.className = page.right_content ? 'song-block split-song mb-4' : 'song-block mb-4';
    songDiv.style.border = '1px dashed red';
    songDiv.style.backgroundColor = '#fdfdfd';
    songDiv.style.display = 'flex';
    songDiv.style.flexDirection = 'column';

    if (!page.continuation) {
      const header = document.createElement('div');
      header.innerHTML = `<strong>${page.title}</strong> <em>${page.artist}</em>`;
      header.style.marginBottom = '10px';
      songDiv.appendChild(header);
    }

    if (page.right_content) {
      const columns = document.createElement('div');
      columns.className = 'd-flex';

      const leftCol = document.createElement('div');
      leftCol.className = 'left-column flex-grow-1 pe-3';
      const leftPre = document.createElement('pre');
      leftPre.textContent = page.left_content;
      leftPre.style.margin = 0;
      leftCol.appendChild(leftPre);

      const rightCol = document.createElement('div');
      rightCol.className = 'right-column flex-grow-1 ps-3';
      const rightPre = document.createElement('pre');
      rightPre.textContent = page.right_content;
      rightPre.style.margin = 0;
      rightCol.appendChild(rightPre);

      columns.appendChild(leftCol);
      columns.appendChild(rightCol);
      songDiv.appendChild(columns);
    } else {
      const pre = document.createElement('pre');
      pre.textContent = page.left_content || page.content || '';
      pre.style.margin = 0;
      songDiv.appendChild(pre);
    }

    container.appendChild(songDiv);
    const height = songDiv.getBoundingClientRect().height;
    container.removeChild(songDiv);

    totalHeight += height;
  });

  return totalHeight;
}

function orderSongGroup(songs) {
  if (songs.length <= 2) return songs;

  // Precompute leftover waste for all songs
  songs.forEach(song => {
    const leftover = PAGE_HEIGHT - (song.height % PAGE_HEIGHT || PAGE_HEIGHT);
    song.wasteScore = leftover + (song.twoPages ? 2000 : 0);  // penalty for multi-page songs
  });

  // Limit candidates for first and last to first 3 and last 3 by height ascending (smallest songs)
  // because smaller songs tend to fit better at edges
  const candidates = songs.slice().sort((a, b) => a.height - b.height);

  const firstCandidates = candidates.slice(0, Math.min(3, candidates.length));
  const lastCandidates = candidates.slice(0, Math.min(3, candidates.length));

  let bestPair = null;
  let bestWasteSum = Infinity;

  // Try all pairs (first, last) distinct
  for (let firstCandidate of firstCandidates) {
    for (let lastCandidate of lastCandidates) {
      if (firstCandidate.id === lastCandidate.id) continue;

      const firstWaste = PAGE_HEIGHT - (firstCandidate.height % PAGE_HEIGHT || PAGE_HEIGHT);
      const lastWaste = PAGE_HEIGHT - (lastCandidate.height % PAGE_HEIGHT || PAGE_HEIGHT);

      const wasteSum = firstWaste + lastWaste;

      if (wasteSum < bestWasteSum) {
        bestWasteSum = wasteSum;
        bestPair = [firstCandidate, lastCandidate];
      }
    }
  }

  if (!bestPair) {
    // fallback if no pair found (shouldn't happen)
    bestPair = [songs[0], songs[songs.length - 1]];
  }

  // Middle songs = all excluding chosen first and last
  const middle = songs.filter(s => s.id !== bestPair[0].id && s.id !== bestPair[1].id)
                      .sort((a, b) => b.height - a.height);

  // Debug logs for insight
  console.log(`--- Ordering songs for artist: ${songs[0]?.artist || "Unknown"} ---`);
  console.log(`Chosen first song ID: ${bestPair[0].id}, Height: ${bestPair[0].height}px, Waste: ${PAGE_HEIGHT - (bestPair[0].height % PAGE_HEIGHT || PAGE_HEIGHT)}px`);
  console.log(`Chosen last song ID: ${bestPair[1].id}, Height: ${bestPair[1].height}px, Waste: ${PAGE_HEIGHT - (bestPair[1].height % PAGE_HEIGHT || PAGE_HEIGHT)}px`);
  console.log('Packing middle songs:');

  let pageNumber = 1;
  let spaceLeft = PAGE_HEIGHT;

  middle.forEach(song => {
    if (song.height > spaceLeft) {
      console.log(`  -- Page ${pageNumber} full, space left: ${spaceLeft.toFixed(0)}px`);
      pageNumber++;
      spaceLeft = PAGE_HEIGHT;
    }
    console.log(`  Page ${pageNumber} - Song ID: ${song.id}, Height: ${song.height}px, Space left before placing: ${spaceLeft.toFixed(0)}px`);
    spaceLeft -= song.height;
  });

  console.log(`  -- Page ${pageNumber} ends with space left: ${spaceLeft.toFixed(0)}px`);

  return [bestPair[0], ...middle, bestPair[1]];
}



function getOrderedIds(finalOrderedList) {
  return finalOrderedList.map(song => song.id);
}


function orderArtistsByEdges(artists, PAGE_HEIGHT) {
  if (artists.length === 0) return [];

  const singleSongArtists = artists.filter(group => group.songs.length === 1);
  const multiSongArtists = artists.filter(group => group.songs.length > 1);

  let bestStartCombo = null;
  let bestWaste = Infinity;

  // Try each single-song artist as the first song
  for (let i = 0; i < singleSongArtists.length; i++) {
    const firstArtist = singleSongArtists[i];
    const firstSong = firstArtist.songs[0];
    const usedArtists = new Set([firstArtist.artist]);

    let spaceLeft = PAGE_HEIGHT - firstSong.height;
    const packed = [firstArtist];

    // Try to fill rest of first page with songs from other single-song artists
    for (let j = 0; j < singleSongArtists.length; j++) {
      if (i === j) continue;
      const candidateArtist = singleSongArtists[j];
      const candidateSong = candidateArtist.songs[0];

      if (usedArtists.has(candidateArtist.artist)) continue;
      if (candidateSong.height <= spaceLeft) {
        packed.push(candidateArtist);
        spaceLeft -= candidateSong.height;
        usedArtists.add(candidateArtist.artist);
      }
    }

    const waste = spaceLeft;

    if (waste < bestWaste) {
      bestWaste = waste;
      bestStartCombo = packed;
    }
  }

  let orderedArtists = [];
  let usedArtistNames = new Set();

  if (bestStartCombo) {
    orderedArtists.push(...bestStartCombo);
    bestStartCombo.forEach(ag => usedArtistNames.add(ag.artist));
  }

  // Add the rest of the artists, ignoring used ones
  const remainingArtists = artists.filter(ag => !usedArtistNames.has(ag.artist));

  let remaining = [...remainingArtists];

  // If we already placed some artists, continue from last placed
  while (remaining.length > 0) {
    let lastArtist = orderedArtists[orderedArtists.length - 1];
    let lastHeight = lastArtist.lastSongHeight;

    // Find best next artist that fits well with the previous one
    let bestFitIndex = 0;
    let bestFitLeftover = PAGE_HEIGHT;

    for (let i = 0; i < remaining.length; i++) {
      let candidate = remaining[i];
      let combinedHeight = lastHeight + candidate.firstSongHeight;
      let leftover = PAGE_HEIGHT - (combinedHeight % PAGE_HEIGHT);

      if (leftover < bestFitLeftover) {
        bestFitLeftover = leftover;
        bestFitIndex = i;
      }
    }

    orderedArtists.push(remaining.splice(bestFitIndex, 1)[0]);
  }

  return orderedArtists;
}










