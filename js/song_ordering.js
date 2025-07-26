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

const PAGE_HEIGHT = 1123;




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

function measureSongHeight(pages, container, headerOptions = {}) {
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
      const header = createSongHeader(page, headerOptions);
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
  const packed = [];
  let currentPage = [];
  let currentHeight = 0;

  // Sort by height descending to pack largest songs first
  const remainingSongs = songs.slice().sort((a, b) => b.height - a.height);

  while (remainingSongs.length > 0) {
    let placed = false;

    for (let i = 0; i < remainingSongs.length; i++) {
      const song = remainingSongs[i];
      
      if (song.height > PAGE_HEIGHT) {
        // Song is too big, must be placed alone
        if (currentPage.length > 0) {
          packed.push(currentPage);
          currentPage = [];
          currentHeight = 0;
        }

        packed.push([song]);
        remainingSongs.splice(i, 1);
        placed = true;
        break;
      }

      if (currentHeight + song.height <= PAGE_HEIGHT) {
        currentPage.push(song);
        currentHeight += song.height;
        remainingSongs.splice(i, 1);
        placed = true;
        break;
      }
    }

    if (!placed) {
      if (currentPage.length > 0) {
        packed.push(currentPage);
        currentPage = [];
        currentHeight = 0;
      } else {
        // Fallback: force a too-big song to be placed alone
        packed.push([remainingSongs.shift()]);
      }
    }
  }

  if (currentPage.length > 0) {
    packed.push(currentPage);
  }

  return packed.flat(); // Flatten back into a list of songs
}





function getOrderedIds(finalOrderedList) {
  return finalOrderedList.map(song => song.id);
}


function orderArtistsByEdges(artistGroups, PAGE_HEIGHT) {
  if (artistGroups.length === 0) return [];

  const { filledGroups, remainingGroups } = fillFirstPage(artistGroups, PAGE_HEIGHT);
  const ordered = [...filledGroups];

  let currentPageHeight = 0;
  let currentPage = filledGroups.length > 0 ? 2 : 1;  // next page after filled first

  let remaining = [...remainingGroups];

  while (remaining.length > 0) {
    let bestIndex = -1;
    let bestWaste = Infinity;

    for (let i = 0; i < remaining.length; i++) {
      const group = remaining[i];
      const firstSong = group.songs[0];

      // If song spans 2 pages, only allow it on even pages
      const isDoublePage = firstSong.pages > 1;
      if (isDoublePage && currentPage % 2 !== 0) continue;

      const waste = PAGE_HEIGHT - ((currentPageHeight + firstSong.height) % PAGE_HEIGHT || PAGE_HEIGHT);
      if (waste < bestWaste) {
        bestWaste = waste;
        bestIndex = i;
      }
    }

    if (bestIndex === -1) {
      // No fitting artist found for this page (maybe because it's odd and all songs are double-page)
      currentPage++;
      currentPageHeight = 0;
      continue;
    }

    const bestGroup = remaining.splice(bestIndex, 1)[0];
    ordered.push(bestGroup);

    // Simulate placing songs and track current page height
    bestGroup.songs.forEach(song => {
      const isDoublePage = song.pages > 1;

      // If double-page song and we're on an odd page, skip to next page
      if (isDoublePage && currentPage % 2 !== 0) {
        currentPage++;
        currentPageHeight = 0;
      }

      if (song.height > PAGE_HEIGHT - currentPageHeight) {
        currentPage++;
        currentPageHeight = 0;
      }

      currentPageHeight += song.height;

      if (currentPageHeight >= PAGE_HEIGHT) {
        currentPage++;
        currentPageHeight = 0;
      }
    });
  }

  return ordered;
}



function fillFirstPage(artistGroups, PAGE_HEIGHT) {
  const filledGroups = [];
  const remainingGroups = [...artistGroups];

  let usedSpace = 0;
  let usedArtistNames = new Set();

  // Look for single-page songs that fit on the first page
  while (remainingGroups.length > 0) {
    let addedSomething = false;

    for (let i = 0; i < remainingGroups.length; i++) {
      const group = remainingGroups[i];
      const song = group.songs[0];

      if (usedArtistNames.has(group.artist)) continue;
      if (song.pages > 1) continue;

      if (song.height <= PAGE_HEIGHT - usedSpace) {
        filledGroups.push({
          artist: group.artist,
          songs: [song]
        });

        usedArtistNames.add(group.artist);
        usedSpace += song.height;

        // Remove the used song from the group
        group.songs.shift();

        // If group is now empty, remove it entirely
        if (group.songs.length === 0) {
          remainingGroups.splice(i, 1);
        }

        addedSomething = true;
        break;
      }
    }

    if (!addedSomething) break; // no more songs fit
  }

  return {
    filledGroups,
    remainingGroups
  };
}










