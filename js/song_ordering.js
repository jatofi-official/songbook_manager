// Create off-screen span for measuring text width
// Setup only once
const measurer = document.createElement('span');
measurer.style.position = 'absolute';
measurer.style.visibility = 'hidden';
measurer.style.whiteSpace = 'pre';
measurer.style.fontFamily = 'Fira Mono, monospace'; // Match preview
measurer.style.fontSize = '14px';                   // Match preview font size
measurer.style.lineHeight = '21px';                 // Match preview line height (.a4-page: font-size 14px * line-height 1.5)
document.body.appendChild(measurer);

const PAGE_HEIGHT = 1123;




// Returns pixel width of a line in Fira Mono
function measureTextWidth(line) {
  measurer.textContent = line.replace(/\s+$/, '');
  return measurer.getBoundingClientRect().width;
}

// Measures the real rendered height of `text` inside `container`, built the
// exact same way the real render builds a song body: same renderSong() HTML
// (chord bolding can shift wrapping vs. raw text) inside a plain <pre>,
// relying on `.a4-page pre`'s own white-space:pre-wrap;word-break:break-word
// (styles/a4_page.css) to wrap it - which only applies because `container`
// is itself a real .a4-page now (see createHiddenMeasureContainer). `widthPx`,
// when given, constrains the probe to a column's width instead of the full
// page width - see measureColumnWidth().
function measureHeight(text, container, widthPx) {
  const probe = document.createElement('pre');
  probe.innerHTML = renderSong(text);
  if (widthPx !== undefined) probe.style.width = widthPx + 'px';
  container.appendChild(probe);
  const height = probe.getBoundingClientRect().height;
  container.removeChild(probe);
  return height;
}

// Measures the real rendered width of one song column (.left-column/
// .right-column live in a `.d-flex` row with `flex-grow-1` plus a fixed
// `width: 48%` - actual resolved width depends on flex layout, not just the
// 48%, so this builds the same structure and reads it back rather than
// recomputing the percentage by hand.
function measureColumnWidth(container) {
  const row = document.createElement('div');
  row.className = 'd-flex';
  const left = document.createElement('div');
  left.className = 'left-column flex-grow-1 pe-3';
  const right = document.createElement('div');
  right.className = 'right-column flex-grow-1 ps-3';
  row.append(left, right);
  container.appendChild(row);
  const width = left.getBoundingClientRect().width;
  container.removeChild(row);
  return width;
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



// Splits an already-trimmed line array into page-sized chunks (array of
// line arrays). Same empty-line-preferred / binary-search-fallback logic
// that splitPageIfNeeded always used for its single split - just run in a
// loop so a song (or a single column of one) can spill onto as many pages
// as it actually needs instead of stopping after one split. The first
// chunk is measured against firstPageBudget (page height minus the song
// header, since only the first page carries it); every later chunk against
// restPageBudget (the full page height).
function splitLinesIntoPageChunks(lines, container, firstPageBudget, restPageBudget, widthPx) {
  const chunks = [];
  let remaining = lines;
  let budget = firstPageBudget;

  while (remaining.length > 0) {
    const fullHeight = measureHeight(remaining.join('\n'), container, widthPx);
    if (fullHeight <= budget) {
      chunks.push(remaining);
      break;
    }

    // Step 1: Find all empty lines that could be good split points
    let bestSplit = -1;

    for (let i = 1; i < remaining.length; i++) {
      if (remaining[i].trim() !== '') continue; // not an empty line

      const testLines = remaining.slice(0, i + 1); // include the empty line
      const testHeight = measureHeight(testLines.join('\n'), container, widthPx);

      if (testHeight <= budget) {
        bestSplit = i + 1; // split after the empty line
      } else {
        break; // further lines will only be taller
      }
    }

    // Step 2: Fallback — if no good empty line split found, do original logic
    if (bestSplit === -1) {
      // Binary search fallback
      bestSplit = remaining.length;
      for (let i = Math.floor(remaining.length / 2); i < remaining.length; i++) {
        const leftLines = remaining.slice(0, i);
        const leftHeight = measureHeight(leftLines.join('\n'), container, widthPx);
        if (leftHeight > budget) {
          bestSplit = i - 1;
          break;
        }
      }
    }

    if (bestSplit <= 0) bestSplit = 1;

    // Trim leading empty lines on the remaining part
    let rest = remaining.slice(bestSplit);
    while (rest.length && rest[0].trim() === '') {
      rest.shift();
    }

    chunks.push(remaining.slice(0, bestSplit));
    remaining = rest;
    budget = restPageBudget; // only the very first page pays the header cost
  }

  return chunks;
}

// Two-column songs are built by splitSongIfNeeded assuming the whole song
// fits on one page. If the resulting columns are still too tall, continue
// each column independently with splitLinesIntoPageChunks (the exact same
// per-column chunking a single-column song would get), then zip the two
// columns' chunks back together page by page. A song whose columns already
// fit (the overwhelming majority - the legacy one-page-two-column case) is
// returned completely unchanged.
function splitTwoColumnSongAcrossPages(song, container, headerHeight) {
  const leftLines = (song.left_content || '').split('\n').map(line => line.replace(/\s+$/, ''));
  const rightLines = (song.right_content || '').split('\n').map(line => line.replace(/\s+$/, ''));

  const firstPageBudget = PAGE_HEIGHT - headerHeight;
  const columnWidth = measureColumnWidth(container);
  const leftHeight = measureHeight(leftLines.join('\n'), container, columnWidth);
  const rightHeight = measureHeight(rightLines.join('\n'), container, columnWidth);

  if (Math.max(leftHeight, rightHeight) <= firstPageBudget) return [song];

  const leftChunks = splitLinesIntoPageChunks(leftLines, container, firstPageBudget, PAGE_HEIGHT, columnWidth);
  const rightChunks = splitLinesIntoPageChunks(rightLines, container, firstPageBudget, PAGE_HEIGHT, columnWidth);

  const pageCount = Math.max(leftChunks.length, rightChunks.length);
  const pages = [];
  for (let i = 0; i < pageCount; i++) {
    let leftPart = (leftChunks[i] || []).join('\n');
    let rightPart = (rightChunks[i] || []).join('\n');
    // If the columns finish unevenly (one runs out of content on an
    // earlier page than the other), keep any remaining text on the left so
    // a page never renders an empty left column next to a populated right one.
    if (!leftPart && rightPart) {
      leftPart = rightPart;
      rightPart = '';
    }
    pages.push({
      ...song,
      left_content: leftPart,
      right_content: rightPart || null, // fall back to single column once one side runs out
      continuation: i > 0,
    });
  }
  return pages;
}

// $headerHeight (optional, in px) is the space the song's title/artist
// header takes up on its first page - only the first page of a song pays
// that cost, continuation pages start right at the top. Defaults to 0 to
// keep existing callers (e.g. orderSongs' pre-pass) behaving exactly as
// before.
function splitPageIfNeeded(song, container, headerHeight = 0) {
  if (song.right_content) {
    return splitTwoColumnSongAcrossPages(song, container, headerHeight);
  }

  const lines = (song.left_content || song.content || '').split('\n').map(line => line.replace(/\s+$/, ''));
  if (lines.length === 0) return [song];

  const firstPageBudget = PAGE_HEIGHT - headerHeight;
  const chunks = splitLinesIntoPageChunks(lines, container, firstPageBudget, PAGE_HEIGHT);
  if (chunks.length <= 1) return [song];

  return chunks.map((chunkLines, index) => ({
    ...song,
    left_content: chunkLines.join('\n'),
    right_content: null,
    continuation: index > 0,
  }));
}

// Carries the real `.a4-page`/`p-4` classes instead of hand-duplicating their
// CSS (width, padding, font, line-height...) here. That duplication was the
// actual source of past measurement/render mismatches - any tweak to
// styles/a4_page.css had to be remembered and copied here too, and every rule
// scoped to `.a4-page` (e.g. `.a4-page pre`) silently didn't apply at all
// since a bespoke div never carried that class. Deliberately not built via
// createPage() (songbook_render_preview.js): that returns a *fixed*-height,
// overflow:hidden page (page.contentEl holds the real padded/clipped area) -
// exactly wrong for measuring, since the point here is to find out how tall
// content actually is even past one page's worth, not to clip it.
function createHiddenMeasureContainer() {
  const container = document.createElement('div');
  container.className = 'a4-page p-4';
  container.style.position = 'absolute';
  container.style.visibility = 'hidden';
  container.style.left = '-9999px';
  container.style.top = '0';
  container.style.width = '794px';
  container.style.boxSizing = 'border-box';
  return container;
}

function orderSongs(songs, container) {
  const grouped = groupSongsByArtist(songs);

  // Measure and order songs inside each artist group
  const measuredArtistGroups = grouped.map(group => {
    const measuredSongs = group.map(song => {
      const splitSong = splitSongIfNeeded(song);

      // Real header height (title/artist row only - the ordering pre-pass
      // doesn't know the eventual render's blackAndWhite/showDifficulty/etc.
      // options, so it measures the plain default header; still the real
      // createSongHeader markup/CSS, not a stand-in, so it's accurate for the
      // common case of no extra badges).
      const measuredHeader = createSongHeader(splitSong, {});
      container.appendChild(measuredHeader);
      const headerHeight = measuredHeader.getBoundingClientRect().height
        + (parseFloat(getComputedStyle(measuredHeader).marginBottom) || 0);
      container.removeChild(measuredHeader);

      const pages = splitPageIfNeeded(splitSong, container, headerHeight);
      const totalHeight = measureSongPages(pages, container);

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


// Builds each page of a song exactly the way the real render loop does
// (createSongHeader for the header, renderSong() HTML inside plain <pre>s for
// the body) and measures its real footprint (height + own margin-bottom),
// same as songbook_render_preview.js's blockHeight. Only used by orderSongs'
// pre-pass to estimate total height for bin-packing - not part of the real
// render itself.
function measureSongPages(pages, container) {
  let totalHeight = 0;

  pages.forEach(page => {
    const songDiv = document.createElement('div');
    songDiv.className = page.right_content ? 'song-block split-song mb-4' : 'song-block mb-4';

    if (!page.continuation) {
      songDiv.appendChild(createSongHeader(page, {}));
    }

    if (page.right_content) {
      // .split-song's own CSS is a row (display:flex; justify-content:space-between) -
      // the real render loop overrides it inline to stack the header above the
      // columns row instead of beside it. Match that here too.
      songDiv.style.display = 'flex';
      songDiv.style.flexDirection = 'column';

      const columns = document.createElement('div');
      columns.className = 'd-flex';

      const leftCol = document.createElement('div');
      leftCol.className = 'left-column flex-grow-1 pe-3';
      const leftPre = document.createElement('pre');
      leftPre.innerHTML = renderSong(page.left_content);
      leftCol.appendChild(leftPre);

      const rightCol = document.createElement('div');
      rightCol.className = 'right-column flex-grow-1 ps-3';
      const rightPre = document.createElement('pre');
      rightPre.innerHTML = renderSong(page.right_content);
      rightCol.appendChild(rightPre);

      columns.appendChild(leftCol);
      columns.appendChild(rightCol);
      songDiv.appendChild(columns);
    } else {
      const pre = document.createElement('pre');
      pre.innerHTML = renderSong(page.left_content || page.content || '');
      songDiv.appendChild(pre);
    }

    container.appendChild(songDiv);
    const marginBottom = parseFloat(getComputedStyle(songDiv).marginBottom) || 0;
    const height = songDiv.getBoundingClientRect().height + marginBottom;
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
  let currentPage = filledGroups.length > 0 ? 2 : 1;  // Start from next page

  let remaining = [...remainingGroups];

  while (remaining.length > 0) {
    let bestIndex = -1;
    let bestWaste = Infinity;

    for (let i = 0; i < remaining.length; i++) {
      const group = remaining[i];
      const firstSong = group.songs[0];

      const isDoublePage = firstSong.pages > 1;
      if (isDoublePage && currentPage % 2 !== 0) continue;

      const waste = PAGE_HEIGHT - ((currentPageHeight + firstSong.height) % PAGE_HEIGHT || PAGE_HEIGHT);
      if (waste < bestWaste) {
        bestWaste = waste;
        bestIndex = i;
      }
    }

    if (bestIndex === -1) {
      currentPage++;
      currentPageHeight = 0;
      continue;
    }

    const bestGroup = remaining.splice(bestIndex, 1)[0];
    ordered.push(bestGroup);

    // Simulate layout more accurately
    bestGroup.songs.forEach(song => {
      const isDoublePage = song.pages > 1;

      // If the song can't fit in the remaining space, move to next page
      if (song.height > PAGE_HEIGHT - currentPageHeight) {
        currentPage++;
        currentPageHeight = 0;
      }

      // Save the page number where this song starts
      song._startPage = currentPage;

      if (isDoublePage) {
        const isOdd = currentPage % 2 !== 0;
        // console.log(`📄 Multi-page song: "${song.title}" [${song.id}] starts on page ${currentPage} (${isOdd ? '⚠️ ODD' : 'even'})`);

        // Force move to next page if we're starting on odd and this is a multi-pager
        if (isOdd) {
          // console.log(`↪️ Moving song [${song.id}] to even page...`);
          currentPage++;
          currentPageHeight = 0;
          song._startPage = currentPage; // update page after skip
        }
      }

      currentPageHeight += song.height;

      // If we overflow, go to next page
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










