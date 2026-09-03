function renderA4Preview(songs, orderedIds, options = {}) {
  const debug = options.debug || false;
  const category = options.category;
  const blackAndWhite = options.blackAndWhite || false;
  const allowClicking = options.allowClicking || false;  // default false
  const transposeToCapoZero = options.transposeToCapoZero || false;
  const showDifficulty = options.showDifficulty || false;
  const showTags = options.showTags || false;
  const showInstruments = options.showInstruments || false;
  const ignoredInstrument = options.ignoredInstrument || '';
  const showLockName = options.showLockName || false;
  const lockNames = options.lockNames || {}; // song_id (string) -> { lock_id, name, locked_at }

  const container = document.getElementById('a4-preview-container');
  const pagesCreatedThisCall = []; // { page, pageNumber } - for the post-render lock-name label pass

  if (transposeToCapoZero) {
    // Instruments without a capo (e.g. accordion) need the real sounding chords
    // written out, so transpose up by the capo fret. We deliberately keep
    // song.capo/song.capo_alt at their original (truthy) values so the capo
    // line in the header still renders — dropping it to 0 would remove that
    // line and shrink the header height, throwing off pagination/ordering
    // compared to a non-transposed render of the same songbook. Instead,
    // createSongHeader() uses transposedFromCapo to swap the displayed text.
    songs = songs.map(song => {
      const capo = Number(song.capo) || 0;
      if (!capo) return song;
      return { ...song, content: transposeContent(song.content, capo), transposedFromCapo: capo };
    });
  }

  let processedSongs = songs.map(song => splitSongIfNeeded(song));
  const tempContainer = createHiddenMeasureContainer();
  document.body.appendChild(tempContainer);

  const maxHeight = 1123;
  const pagePadding = 24; // matches createPage's `.a4-page-content` p-4 (1.5rem)
  let currentPageNum = 1;

  let pageRow = document.createElement('div');
  pageRow.className = 'page-pair d-flex justify-content-center gap-4 mb-5';
  container.appendChild(pageRow);

  let currentPage = createPage(currentPageNum, category);
  if (options.categoryId !== undefined) currentPage.dataset.categoryId = options.categoryId;
  currentPage.dataset.categoryLabel = category || '';
  pageRow.appendChild(currentPage);
  pagesCreatedThisCall.push({ page: currentPage, pageNumber: currentPageNum });

  if (debug) {
    const line = document.createElement('div');
    line.style.position = 'absolute';
    line.style.top = 0;
    line.style.bottom = 0;
    line.style.left = '50%';
    line.style.width = '1px';
    line.style.backgroundColor = '#007bff';
    line.style.zIndex = '10';
    currentPage.appendChild(line);
  }

  let currentHeight = 0;

  for (const id of orderedIds) {
    let song = processedSongs.find(s => s.id === id);
    if (!song) continue;

    if (song.right_content && song.part === 'right') continue;

    // Measure the header once, ahead of splitting, so splitPageIfNeeded can
    // reserve that space on the song's first page (it isn't reprinted on
    // continuation pages). Header height isn't included via getBoundingClientRect()
    // alone - createSongHeader sets its own marginBottom (default 10px)
    // inline, which margins never count toward an element's own rect.
    const songIsLocked = !!lockNames[String(song.id)];
    const measuredHeader = createSongHeader(song, {
      blackAndWhite, showDifficulty, showTags, showInstruments, ignoredInstrument,
      locked: allowClicking && songIsLocked,
      lockedName: songIsLocked ? lockNames[String(song.id)].name : null
    });
    tempContainer.appendChild(measuredHeader);
    const headerRect = measuredHeader.getBoundingClientRect();
    const headerMarginBottom = parseFloat(getComputedStyle(measuredHeader).marginBottom) || 0;
    const headerHeight = headerRect.height + headerMarginBottom;
    tempContainer.removeChild(measuredHeader);

    const songParts = splitPageIfNeeded(song, tempContainer, headerHeight);

    for (const partSong of songParts) {
      const songDiv = document.createElement('div');
      songDiv.className = 'song-block mb-4';
      songDiv.dataset.songId = partSong.id;
      songDiv.dataset.songTitle = partSong.title || '';
      songDiv.dataset.songArtist = partSong.artist || '';

      const isLocked = !!lockNames[String(partSong.id)];

      if (allowClicking && isLocked) {
        // Locked songs can't be reordered/removed - make that obvious and non-interactive.
        songDiv.style.borderLeft = '4px solid #dc3545';
        songDiv.style.cursor = 'not-allowed';
        songDiv.title = `Locked (${lockNames[String(partSong.id)].name}) - can't be reordered or removed`;
      }

      if (allowClicking && !isLocked) {
        // Attach click event
        songDiv.addEventListener('click', (e) => {
          e.stopPropagation();
          const clickedId = partSong.id;

          if (selectedSongId !== null && selectedSongId !== clickedId) {
            const idx1 = orderedIds.indexOf(selectedSongId);
            const idx2 = orderedIds.indexOf(clickedId);

            if (idx1 !== -1 && idx2 !== -1) {
              undoStack.push([...orderedIds]);
              if (undoStack.length > 100) undoStack.shift();

              [orderedIds[idx1], orderedIds[idx2]] = [orderedIds[idx2], orderedIds[idx1]];
            }

            selectedSongId = null;
            document.querySelectorAll('.song-block.selected').forEach(el =>
              el.classList.remove('selected')
            );

            beginRender(orderedIds, currentCategory);
          } else {
            selectedSongId = clickedId;

            document.querySelectorAll('.song-block.selected').forEach(el =>
              el.classList.remove('selected')
            );
            songDiv.classList.add('selected');
          }
        });

        // Attach right-click event
        songDiv.addEventListener('contextmenu', (e) => {
          e.preventDefault();
          e.stopPropagation();
          const targetId = partSong.id;

          if (selectedSongId !== null && selectedSongId !== targetId) {
            const fromIndex = orderedIds.indexOf(selectedSongId);
            const toIndex = orderedIds.indexOf(targetId);

            if (fromIndex !== -1 && toIndex !== -1) {
              undoStack.push([...orderedIds]);
              if (undoStack.length > 100) undoStack.shift();

              // Remove and insert
              const [moved] = orderedIds.splice(fromIndex, 1);
              const insertIndex = fromIndex < toIndex ? toIndex : toIndex + 1;
              orderedIds.splice(insertIndex, 0, moved);

              selectedSongId = null;
              document.querySelectorAll('.song-block.selected').forEach(el =>
                el.classList.remove('selected')
              );

              beginRender(orderedIds, currentCategory);
            }
          }
        });
      }

      if (debug) {
        songDiv.style.border = '1px dashed red';
        songDiv.style.backgroundColor = '#fdfdfd';
      }

      if (!partSong.continuation) {
        const header = createSongHeader(partSong, {
          blackAndWhite, showDifficulty, showTags, showInstruments, ignoredInstrument,
          locked: allowClicking && isLocked,
          lockedName: isLocked ? lockNames[String(partSong.id)].name : null
        });
        songDiv.appendChild(header);
      }

      if (!partSong.right_content) {
        const pre = document.createElement('pre');
        pre.style.margin = 0;
        pre.innerHTML = renderSong(partSong.left_content);
        songDiv.appendChild(pre);
      } else {
        songDiv.classList.add('split-song');
        songDiv.style.display = 'flex';
        songDiv.style.flexDirection = 'column';

        const columns = document.createElement('div');
        columns.className = 'd-flex';

        const leftCol = document.createElement('div');
        leftCol.className = 'left-column flex-grow-1 pe-3';
        const leftPre = document.createElement('pre');
        leftPre.style.margin = 0;
        leftPre.innerHTML = renderSong(partSong.left_content);
        leftCol.appendChild(leftPre);

        const rightCol = document.createElement('div');
        rightCol.className = 'right-column flex-grow-1 ps-3';
        const rightPre = document.createElement('pre');
        rightPre.style.margin = 0;
        rightPre.innerHTML = renderSong(partSong.right_content);
        rightCol.appendChild(rightPre);

        columns.appendChild(leftCol);
        columns.appendChild(rightCol);
        songDiv.appendChild(columns);
      }

      tempContainer.appendChild(songDiv);

      // Measure max line width
      let maxLineWidth = 0;
      const preElements = songDiv.querySelectorAll('pre');
      preElements.forEach(pre => {
        const lines = pre.textContent.split('\n');
        lines.forEach(line => {
          const trimmed = line.replace(/\s+$/, '');
          const span = document.createElement('span');
          span.style.visibility = 'hidden';
          span.style.position = 'absolute';
          span.style.whiteSpace = 'pre';
          span.style.fontFamily = 'Fira Mono, monospace';
          span.style.fontSize = '14px';
          span.textContent = trimmed;
          tempContainer.appendChild(span);
          const w = span.getBoundingClientRect().width;
          tempContainer.removeChild(span);
          if (w > maxLineWidth) maxLineWidth = w;
        });
      });

      // getBoundingClientRect() never includes margin, so songDiv's own
      // mb-4 bottom margin (the gap the page actually reserves before the
      // next block) has to be added back in explicitly. Left out, this
      // under-counts every song block's real footprint - harmless for one
      // song, but with many small songs on a page the missed margins add up
      // and the loop packs more songs onto a page than actually fit.
      const blockMarginBottom = parseFloat(getComputedStyle(songDiv).marginBottom) || 0;
      let blockHeight = songDiv.getBoundingClientRect().height + blockMarginBottom;

      // Debug-only breakdown so an over-tall measurement can be traced to
      // its actual source (header vs. single body vs. one specific column)
      // instead of only seeing the combined total.
      let debugBreakdown = '';
      if (debug) {
        const headerEl = songDiv.querySelector('.song-header');
        const headerElHeight = headerEl ? headerEl.getBoundingClientRect().height : 0;
        if (partSong.right_content) {
          const leftColEl = songDiv.querySelector('.left-column');
          const rightColEl = songDiv.querySelector('.right-column');
          const leftColHeight = leftColEl ? leftColEl.getBoundingClientRect().height : 0;
          const rightColHeight = rightColEl ? rightColEl.getBoundingClientRect().height : 0;
          debugBreakdown = ` (Header: ${headerElHeight.toFixed(0)}px Left: ${leftColHeight.toFixed(0)}px Right: ${rightColHeight.toFixed(0)}px)`;
        } else {
          const preEl = songDiv.querySelector('pre');
          const preHeight = preEl ? preEl.getBoundingClientRect().height : 0;
          debugBreakdown = ` (Header: ${headerElHeight.toFixed(0)}px Body: ${preHeight.toFixed(0)}px)`;
        }
      }

      tempContainer.removeChild(songDiv);

      let overflow = (currentHeight + blockHeight) - maxHeight;

      // If this block only misses fitting on the current (non-empty) page
      // by a small margin - no more than its own bottom spacing - squeeze
      // that spacing down instead of pushing it to a whole new page.
      if (overflow > 0 && currentHeight > 0 && overflow <= blockMarginBottom) {
        const squeezedMarginBottom = blockMarginBottom - overflow;
        songDiv.style.setProperty('margin-bottom', squeezedMarginBottom + 'px', 'important');
        blockHeight -= overflow;
        overflow = 0;
      }

      if (overflow > 0) {
        if (debug) {
          addPageRemainingSpaceLabel(currentPage, currentHeight, maxHeight);
          flagPageOverflowIfAny(currentPage, currentHeight, maxHeight, pagePadding);
        }

        currentPageNum++;
        currentHeight = 0;

        if (currentPageNum % 2 === 0) {
          pageRow = document.createElement('div');
          pageRow.className = 'page-pair d-flex justify-content-center gap-4 mb-5';
          container.appendChild(pageRow);
        }

        currentPage = createPage(currentPageNum, category);
        if (options.categoryId !== undefined) currentPage.dataset.categoryId = options.categoryId;
        currentPage.dataset.categoryLabel = category || '';
        pageRow.appendChild(currentPage);
        pagesCreatedThisCall.push({ page: currentPage, pageNumber: currentPageNum });

        if (debug) {
          const line = document.createElement('div');
          line.style.position = 'absolute';
          line.style.top = 0;
          line.style.bottom = 0;
          line.style.left = '50%';
          line.style.width = '1px';
          line.style.backgroundColor = '#007bff';
          line.style.zIndex = '10';
          currentPage.appendChild(line);
        }
      }

      const wrapper = document.createElement('div');
      wrapper.style.position = 'relative';
      wrapper.appendChild(songDiv);

      currentPage.contentEl.appendChild(wrapper);
      // wrapper itself has no margin, and songDiv's mb-4 margin collapses
      // through it (wrapper doesn't establish its own block-formatting
      // context) - so re-measuring the wrapper here would silently drop the
      // margin again. blockHeight above already accounts for it.
      const blockTop = currentHeight; // this block's own top, before the increment below
      currentHeight += blockHeight;

      if (debug) {
        // The debug label used to live inside `wrapper`, but `wrapper` is now
        // inside `page.contentEl`, which clips (overflow:hidden) so genuinely
        // overflowing songs get cut off instead of silently stretching the
        // page - and that would clip the label too, even though it's meant
        // to be visible outside the page bounds (left/right:-200px). Instead,
        // mount it on a same-sized, same-positioned overlay attached directly
        // to the unclipped outer `page`.
        const debugOverlay = document.createElement('div');
        debugOverlay.style.position = 'absolute';
        debugOverlay.style.left = pagePadding + 'px';
        debugOverlay.style.width = `calc(100% - ${2 * pagePadding}px)`;
        debugOverlay.style.top = (pagePadding + blockTop) + 'px';
        debugOverlay.style.height = blockHeight + 'px';

        const debugLabel = document.createElement('div');
        debugLabel.style.position = 'absolute';
        // Pages sit side by side in a page-pair row - a label pushed off
        // the inner edge of a page (right edge of a LEFT/odd page, left
        // edge of a RIGHT/even page) lands on top of the neighboring page
        // instead of empty space. Push each page's label off its own
        // outer edge instead, so it never renders under the other page.
        if (currentPageNum % 2 === 1) {
          debugLabel.style.right = '-200px';
        } else {
          debugLabel.style.left = '-200px';
        }
        debugLabel.style.bottom = '50px';
        debugLabel.style.fontSize = '12px';
        debugLabel.style.color = '#007bff';
        debugLabel.textContent = `Height: ${blockHeight.toFixed(0)}px Width: ${maxLineWidth.toFixed(0)}px${debugBreakdown}`;
        debugOverlay.appendChild(debugLabel);
        currentPage.appendChild(debugOverlay);
      }
    }
  }

  document.body.removeChild(tempContainer);

  // The loop above only labels a page's remaining space when it's finalized
  // by overflow - the very last page never overflows into a new one, so it
  // needs its own label here.
  if (debug) {
    addPageRemainingSpaceLabel(currentPage, currentHeight, maxHeight);
    flagPageOverflowIfAny(currentPage, currentHeight, maxHeight, pagePadding);
  }

  // Songbooks print double-sided, so pages are padded to an even count.
  // A standalone single-song export has no such constraint.
  const padToEvenPages = options.padToEvenPages !== false;

  // Check if total pages is odd, add blank page if so
  if (padToEvenPages && currentPageNum % 2 === 1) {
    // If last page is odd-numbered, we add a blank page to make total even

    // Check if last pageRow is the correct one
    // If currentPageNum is odd, next page is even, so we should create a new pageRow (because pageRow groups even pages)
    if (currentPageNum % 2 === 1) {
      // Create a new pageRow for the even page
      pageRow = document.createElement('div');
      pageRow.className = 'page-pair d-flex justify-content-center gap-4 mb-5';
      container.appendChild(pageRow);
    }

    currentPageNum++;

    // Create the blank page with no content
    const blankPage = createPage(currentPageNum, category);
    if (options.categoryId !== undefined) blankPage.dataset.categoryId = options.categoryId;
    blankPage.dataset.categoryLabel = category || '';
    pageRow.appendChild(blankPage);
  }

  if (showLockName) {
    pagesCreatedThisCall.forEach(({ page, pageNumber }) => {
      addLockNameLabelToPage(page, pageNumber, lockNames);
    });
  }

  return currentPageNum;

}

function renumberRenderedPages(container) {
  Array.from(container.querySelectorAll('.a4-page')).forEach((page, index) => {
    const pageNumber = index + 1;
    let label = page.querySelector('.page-number');
    if (!label) {
      label = document.createElement('div');
      label.className = 'page-number';
      label.style.cssText = 'position:absolute;bottom:20px;font-size:12px;color:#555';
      page.appendChild(label);
    }
    const category = page.dataset.categoryLabel || '';
    label.textContent = category ? `${category} · ${pageNumber}` : String(pageNumber);
    label.style.left = pageNumber % 2 === 0 ? '30px' : 'auto';
    label.style.right = pageNumber % 2 === 0 ? 'auto' : '30px';
    label.style.textAlign = pageNumber % 2 === 0 ? 'left' : 'right';
  });
}

function addTableOfContents(container) {
  const contentPages = Array.from(container.querySelectorAll('.a4-page'));
  const seen = new Set();
  const entries = [];

  contentPages.forEach(page => {
    page.querySelectorAll('.song-block[data-song-id]').forEach(song => {
      const key = `${page.dataset.categoryId || ''}:${song.dataset.songId}`;
      if (seen.has(key)) return;
      seen.add(key);
      entries.push({
        category: page.dataset.categoryLabel || '',
        title: song.dataset.songTitle || '',
        artist: song.dataset.songArtist || '',
        // Each category restarts its own page numbering at 1 (see createPage),
        // and that's the number actually printed at the bottom of this page -
        // so that's what the contents entry should point to, not this song's
        // absolute position across the whole rendered book.
        pageNumber: page.dataset.pageNumber || ''
      });
    });
  });

  entries.sort((a, b) => a.title.localeCompare(b.title, undefined, { sensitivity: 'base' }));

  function buildTocRow(entry) {
    const row = document.createElement('div');
    row.style.cssText = 'display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;border-bottom:1px dotted #bbb;padding:3px 0;font-family:system-ui,sans-serif;font-size:13px';
    const text = document.createElement('span');
    text.textContent = `${entry.title}${entry.artist ? ` — ${entry.artist}` : ''}`;
    text.style.overflow = 'hidden';
    text.style.textOverflow = 'ellipsis';
    text.style.whiteSpace = 'nowrap';
    const location = document.createElement('span');
    location.textContent = entry.category ? `${entry.category} ${entry.pageNumber}` : String(entry.pageNumber);
    location.style.whiteSpace = 'nowrap';
    row.append(text, location);
    return row;
  }

  // createPage() pages are 1123px tall with 24px padding on every side
  // (Bootstrap's .p-4) and a page-number footer absolutely positioned near
  // the bottom, which doesn't take up flow height but does need clearance so
  // the listing doesn't run into it.
  const pageContentHeight = 1123 - 2 * 24 - 40;
  const tempContainer = createHiddenMeasureContainer();
  document.body.appendChild(tempContainer);
  const heading = document.createElement('h2');
  heading.textContent = 'Contents';
  const headingHeight = measureBlockHeight(heading, tempContainer);
  const rowHeight = entries.length
    ? measureBlockHeight(buildTocRow(entries[0]), tempContainer)
    : 20;
  document.body.removeChild(tempContainer);

  // The first contents page has less room than continuation pages, since it
  // also carries the heading (see below for why only the first page gets one).
  const firstPageCapacity = Math.max(1, Math.floor((pageContentHeight - headingHeight) / rowHeight));
  const otherPageCapacity = Math.max(1, Math.floor(pageContentHeight / rowHeight));

  const pageEntrySlices = [];
  if (entries.length) {
    pageEntrySlices.push(entries.slice(0, firstPageCapacity));
    for (let i = firstPageCapacity; i < entries.length; i += otherPageCapacity) {
      pageEntrySlices.push(entries.slice(i, i + otherPageCapacity));
    }
  } else {
    pageEntrySlices.push([]);
  }

  const tocPageCount = pageEntrySlices.length;
  const fragment = document.createDocumentFragment();

  for (let pageIndex = 0; pageIndex < tocPageCount; pageIndex += 2) {
    const pair = document.createElement('div');
    pair.className = 'page-pair d-flex justify-content-center gap-4 mb-5';

    for (let side = 0; side < 2 && pageIndex + side < tocPageCount; side++) {
      const tocIndex = pageIndex + side;
      const page = createPage(tocIndex + 1, 'Contents');
      page.dataset.categoryLabel = 'Contents';

      // Only the first contents page gets the heading - long songbooks can
      // spill the listing onto several pages, and repeating a big "Contents"
      // heading on every one of them just eats space without adding anything.
      if (tocIndex === 0) {
        page.contentEl.appendChild(heading.cloneNode(true));
      }

      pageEntrySlices[tocIndex].forEach(entry => page.contentEl.appendChild(buildTocRow(entry)));
      pair.appendChild(page);
    }
    fragment.appendChild(pair);
  }

  // Content pages already carry their real, per-category page numbers from
  // createPage (matching what addTableOfContents just referenced above), and
  // the Contents pages number themselves correctly since they're always
  // inserted at the very front - so no renumbering pass is needed (and
  // running one would flatten everything to an absolute count, breaking the
  // per-category references this contents listing relies on).
  container.prepend(fragment);
}

// Labels a page with the newest lock covering the songs on it (skips pages
// with no locked songs). A page can legitimately span multiple locks - e.g. the
// last song of an older lock sharing a page with the first song of a newer one -
// but only the newest is shown, since that's the lock whose print this page
// actually belongs to. Placed on the opposite side from the page number, which
// alternates left/right by page parity - see createPage().
function addLockNameLabelToPage(page, pageNumber, lockNames) {
  const songIds = Array.from(page.querySelectorAll('.song-block[data-song-id]')).map(el => el.dataset.songId);

  const locksById = new Map();
  songIds.forEach(id => {
    const info = lockNames[id];
    if (info && !locksById.has(info.lock_id)) {
      locksById.set(info.lock_id, info);
    }
  });

  if (!locksById.size) return;

  const newest = Array.from(locksById.values())
    .sort((a, b) => new Date(b.locked_at) - new Date(a.locked_at))[0];

  const label = document.createElement('div');
  label.className = 'lock-name-label';
  label.textContent = newest.name;
  label.style.position = 'absolute';
  label.style.bottom = '20px';
  label.style.fontSize = '12px';
  label.style.color = '#555';

  // Page number: even -> left, odd -> right (see createPage()). Lock label goes on the other side.
  if (pageNumber % 2 === 0) {
    label.style.right = '30px';
    label.style.left = 'auto';
    label.style.textAlign = 'right';
  } else {
    label.style.left = '30px';
    label.style.right = 'auto';
    label.style.textAlign = 'left';
  }

  page.appendChild(label);
}







// Small single-line badges used by the "Extra Display" dev-tools options.
// Kept deliberately compact (small font, tight padding) so they never grow
// taller than the title line's own text, and never change header height.
// The A4 page is a fixed 794px sheet (see createPage: p-4 = 24px Bootstrap
// padding each side) and the song header always uses its default 6px/10px
// padding (see createSongHeader) - so, unlike a responsive page, the space
// available for badges is a known constant we can fit against directly,
// rather than something that needs live layout to discover. A small safety
// margin absorbs the header's 1px border and any box-sizing rounding.
const HEADER_CONTENT_WIDTH = 794 - 24 * 2 - 10 * 2 - 10; // ~696px
const EXTRA_WRAPPER_MAX_WIDTH = HEADER_CONTENT_WIDTH * 0.55; // matches the old 55% CSS cap
const BADGE_GAP = 4;

// Measures the rendered width of an element via a throwaway offscreen probe,
// without needing it attached anywhere in particular first. Badges/capo text
// all inherit the header's system-ui font, so the probe matches that.
function measureElementWidth(el) {
  const probe = document.createElement('div');
  probe.style.position = 'absolute';
  probe.style.visibility = 'hidden';
  probe.style.left = '-9999px';
  probe.style.top = '0';
  probe.style.whiteSpace = 'nowrap';
  probe.style.fontFamily = 'system-ui, sans-serif';
  document.body.appendChild(probe);
  probe.appendChild(el.cloneNode(true));
  const width = probe.getBoundingClientRect().width;
  document.body.removeChild(probe);
  return width;
}

// Greedily fits as many leading `badges` as possible within `maxWidth` with
// no fallback - used when whatever doesn't fit gets a second chance
// elsewhere (the capo row) rather than needing to collapse immediately.
function fitBadgesNoOverflow(badges, maxWidth, gap = BADGE_GAP) {
  const widths = badges.map(measureElementWidth);
  let used = 0;
  let cutoff = badges.length;
  for (let i = 0; i < badges.length; i++) {
    const next = used + (i > 0 ? gap : 0) + widths[i];
    if (next > maxWidth) { cutoff = i; break; }
    used = next;
  }
  return { fitting: badges.slice(0, cutoff), overflow: badges.slice(cutoff) };
}

// Fits as many leading `badges` as possible within `maxWidth`, falling back
// to a "+N" badge (same visual style as a tag) summarizing whatever doesn't
// fit. Tries the largest N that still leaves room for the "+N" badge itself,
// so one oversized badge doesn't collapse the whole row down to just "+N".
function fitBadgesWithOverflow(badges, maxWidth, blackAndWhite, gap = BADGE_GAP) {
  if (!badges.length) return [];

  const widths = badges.map(measureElementWidth);

  for (let n = badges.length; n >= 0; n--) {
    let total = 0;
    for (let i = 0; i < n; i++) total += widths[i] + (i > 0 ? gap : 0);

    const remaining = badges.length - n;
    let plusBadge = null;
    if (remaining > 0) {
      plusBadge = buildTagBadge(`+${remaining}`, blackAndWhite);
      total += (n > 0 ? gap : 0) + measureElementWidth(plusBadge);
    }

    if (total <= maxWidth || n === 0) {
      const visible = badges.slice(0, n);
      if (plusBadge) visible.push(plusBadge);
      return visible;
    }
  }

  return []; // unreachable - the n === 0 pass above always returns
}

function buildDifficultyBadge(difficulty, blackAndWhite) {
  if (!difficulty) return null;
  const diff = String(difficulty).toLowerCase();
  const colors = { easy: '#198754', normal: '#6c757d', hard: '#dc3545' };

  const span = document.createElement('span');
  span.textContent = diff.charAt(0).toUpperCase() + diff.slice(1);
  // inline-block + lineHeight (not inline-flex + align-items) so the text
  // centers via the box model instead of flex alignment: html2canvas doesn't
  // reliably vertically-center text inside flex containers, which left this
  // text sitting off-center in the exported/rendered PDF even though it
  // looked fine in a real browser.
  span.style.display = 'inline-block';
  span.style.fontSize = '0.65rem';
  span.style.fontWeight = '600';
  span.style.padding = '3px 8px';
  span.style.borderRadius = '4px'; // badge-like (rectangular), not a pill
  span.style.lineHeight = '1';
  span.style.whiteSpace = 'nowrap';
  span.style.flexShrink = '0';

  if (blackAndWhite) {
    // distinct gray shades so Easy/Normal/Hard stay distinguishable without color
    const bwShades = {
      easy: { bg: '#e0e0e0', color: '#222' },
      normal: { bg: '#999999', color: '#fff' },
      hard: { bg: '#333333', color: '#fff' }
    };
    const shade = bwShades[diff] || bwShades.normal;
    span.style.backgroundColor = shade.bg;
    span.style.color = shade.color;
    span.style.border = '1px solid #222';
  } else {
    span.style.backgroundColor = colors[diff] || colors.normal;
    span.style.color = '#fff';
  }
  return span;
}

function buildTagBadge(text, blackAndWhite) {
  const span = document.createElement('span');
  span.textContent = text;
  // See buildDifficultyBadge for why this is inline-block + lineHeight
  // rather than inline-flex + align-items.
  span.style.display = 'inline-block';
  span.style.fontSize = '0.65rem';
  span.style.padding = '3px 8px';
  span.style.borderRadius = '10px'; // pill-shaped, matching the tag look in get_song.php
  span.style.lineHeight = '1';
  span.style.whiteSpace = 'nowrap';
  span.style.flexShrink = '0';

  if (blackAndWhite) {
    span.style.backgroundColor = 'transparent';
    span.style.color = '#222';
    span.style.border = '1px solid #999';
  } else {
    span.style.backgroundColor = '#e9ecef';
    span.style.color = '#495057';
  }
  return span;
}

function createSongHeader(song, options = {}) {
  const header = document.createElement('div');
  const blackAndWhite = options.blackAndWhite || false;
  const showDifficulty = options.showDifficulty || false;
  const showTags = options.showTags || false;
  const showInstruments = options.showInstruments || false;
  const ignoredInstrument = (options.ignoredInstrument || '').trim();
  const locked = options.locked || false;
  const lockedName = options.lockedName || null;

  const hasCapo = !!((song.capo && song.capo !== 0) || (song.capo_alt && song.capo_alt !== 0));


  header.classList.add('song-header');
  header.style.border = options.border || '1px solid #ccc';
  header.style.padding = options.padding || '6px 10px';
  header.style.borderRadius = options.borderRadius || '6px';
  header.style.backgroundColor = options.backgroundColor || '#f9f9f9';
  header.style.fontFamily = 'system-ui, sans-serif';

  // Title + Artist Line
  const titleLine = document.createElement('div');
  titleLine.style.fontWeight = 'bold';
  titleLine.style.fontSize = '1.1rem';
  titleLine.style.display = 'flex';
  titleLine.style.alignItems = 'center';
  titleLine.style.justifyContent = 'space-between';
  titleLine.style.gap = '8px';

  // Title + artist get their own shrinkable, single-line wrapper so extra
  // badges on the right never force the row to wrap onto a second line.
  const titleTextWrapper = document.createElement('span');
  titleTextWrapper.style.display = 'flex';
  titleTextWrapper.style.alignItems = 'baseline';
  titleTextWrapper.style.minWidth = '0';
  titleTextWrapper.style.flex = '1 1 auto';
  titleTextWrapper.style.overflow = 'hidden';
  titleTextWrapper.style.whiteSpace = 'nowrap';
  titleTextWrapper.style.textOverflow = 'ellipsis';

  if (locked && lockedName) {
    const lockLabel = document.createElement('span');
    lockLabel.textContent = lockedName;
    lockLabel.style.marginRight = '6px';
    lockLabel.style.flexShrink = '0';
    lockLabel.style.fontSize = '0.65rem';
    lockLabel.style.fontWeight = '600';
    lockLabel.style.padding = '3px 8px';
    lockLabel.style.borderRadius = '4px';
    lockLabel.style.lineHeight = '1';
    // inline-block + lineHeight, not inline-flex + align-items — see
    // buildDifficultyBadge for why (html2canvas mis-centers flex text).
    lockLabel.style.display = 'inline-block';
    lockLabel.style.backgroundColor = blackAndWhite ? 'transparent' : '#dc3545';
    lockLabel.style.color = blackAndWhite ? '#222' : '#fff';
    if (blackAndWhite) lockLabel.style.border = '1px solid #222';
    titleTextWrapper.appendChild(lockLabel);
  }

  const titleText = document.createElement('span');
  titleText.textContent = song.title;
  titleTextWrapper.appendChild(titleText);

  if (song.artist && song.artist.trim() !== '') {
    const separator = document.createElement('span');
    separator.textContent = ' – ';
    separator.style.margin = '0 4px';

    const artistText = document.createElement('span');
    artistText.textContent = song.artist;
    artistText.style.fontWeight = 'normal';
    artistText.style.fontSize = '0.95rem';
    artistText.style.color = '#555';

    titleTextWrapper.appendChild(separator);
    titleTextWrapper.appendChild(artistText);
  }

  titleLine.appendChild(titleTextWrapper);

  // Extra Display: difficulty, tags and instruments, rendered to the right of
  // the title. Difficulty always renders in full - its width is reserved
  // first and it's never dropped. Tags (most-used tag first, per the
  // usage_count ordering the server already applies) and instruments fill
  // whatever's left in the title row; anything that doesn't fit there spills
  // into the capo row if the song has one (capoOverflowBadges, appended
  // below), or collapses into a "+N" badge immediately otherwise - either
  // way the header's line count, and therefore pagination, never changes
  // based on how many tags/instruments a song happens to have.
  let capoOverflowBadges = [];
  // X offset (from the header's left content edge) of the title row's
  // rightmost tag/instrument badge - excluding difficulty, which always sits
  // furthest right on its own - so the capo row's overflow badges align
  // under that instead of bunching up against the capo circle.
  let extraWrapperLeftOffset = 0;

  if (showDifficulty || showTags || showInstruments) {
    const extraWrapper = document.createElement('span');
    extraWrapper.style.display = 'flex';
    extraWrapper.style.alignItems = 'center';
    extraWrapper.style.gap = `${BADGE_GAP}px`;
    extraWrapper.style.flex = '0 1 auto';
    extraWrapper.style.whiteSpace = 'nowrap';

    const difficultyBadge = showDifficulty ? buildDifficultyBadge(song.difficulty, blackAndWhite) : null;

    const overflowCandidates = [];
    if (showTags && song.tags) {
      song.tags.split(',')
        .map(t => t.trim())
        .filter(Boolean)
        .forEach(tag => overflowCandidates.push(buildTagBadge(tag, blackAndWhite)));
    }

    if (showInstruments) {
      // Not gated on song.instruments being non-empty - a song with no
      // instruments at all still needs to be flagged as missing the ignored
      // default (e.g. Guitar) if one is configured.
      const instrumentNames = (song.instruments || '').split(',').map(i => i.trim()).filter(Boolean);
      const ignoredLower = ignoredInstrument.toLowerCase();

      instrumentNames
        .filter(instrument => !ignoredInstrument || instrument.toLowerCase() !== ignoredLower)
        .forEach(instrument => {
          const badge = buildTagBadge(instrument, blackAndWhite);
          badge.title = 'Instrument';
          overflowCandidates.push(badge);
        });

      // The ignored "default" instrument (e.g. Guitar, almost always
      // present) is assumed and hidden entirely when the song does have it;
      // shown struck-through only to flag a song that deviates from that
      // assumption by NOT having it.
      if (ignoredInstrument && !instrumentNames.some(i => i.toLowerCase() === ignoredLower)) {
        const missingBadge = buildTagBadge(ignoredInstrument, blackAndWhite);
        missingBadge.title = `Missing default instrument (${ignoredInstrument})`;
        missingBadge.style.textDecoration = 'line-through';
        missingBadge.style.opacity = '0.6';
        overflowCandidates.unshift(missingBadge);
      }
    }

    const difficultyWidth = difficultyBadge ? measureElementWidth(difficultyBadge) : 0;
    const rowBudget = Math.max(
      0,
      EXTRA_WRAPPER_MAX_WIDTH - difficultyWidth - (difficultyBadge && overflowCandidates.length ? BADGE_GAP : 0)
    );

    let rowBadges;
    if (hasCapo) {
      // Give unfit badges a second chance in the capo row instead of
      // collapsing them into "+N" immediately.
      const { fitting, overflow } = fitBadgesNoOverflow(overflowCandidates, rowBudget);
      rowBadges = fitting;
      capoOverflowBadges = overflow;
    } else {
      rowBadges = fitBadgesWithOverflow(overflowCandidates, rowBudget, blackAndWhite);
    }

    // Measured before rowBadges are moved into the real extraWrapper below -
    // a throwaway wrapper holding the same (still-unattached) elements gives
    // their combined width without difficulty, then appendChild() below just
    // moves them out of it into extraWrapper for real.
    if (rowBadges.length) {
      const rowBadgesOnlyWrapper = document.createElement('span');
      rowBadgesOnlyWrapper.style.display = 'flex';
      rowBadgesOnlyWrapper.style.gap = `${BADGE_GAP}px`;
      rowBadges.forEach(el => rowBadgesOnlyWrapper.appendChild(el));
      const rowBadgesOnlyWidth = measureElementWidth(rowBadgesOnlyWrapper);
      const lastBadgeWidth = measureElementWidth(rowBadges[rowBadges.length - 1]);

      const fullWidth = rowBadgesOnlyWidth + (difficultyBadge ? BADGE_GAP + difficultyWidth : 0);
      const extraWrapperLeft = Math.max(0, HEADER_CONTENT_WIDTH - fullWidth);
      extraWrapperLeftOffset = extraWrapperLeft + rowBadgesOnlyWidth - lastBadgeWidth;
    }

    rowBadges.forEach(el => extraWrapper.appendChild(el));
    if (difficultyBadge) extraWrapper.appendChild(difficultyBadge);

    if (extraWrapper.children.length) {
      titleLine.appendChild(extraWrapper);
    }
  }

  header.appendChild(titleLine);

  // Chord source/translator only matter for spiritual/parody songs (typically
  // translations or adaptations of an original) - hidden for every other category.
  const isSpiritualOrParody = ['Duchovné', 'Paródie'].includes(song.category_name);

  const songMetadata = [];
  if (isSpiritualOrParody && song.chord_source && song.chord_source !== 'unknown') songMetadata.push(`Chords: ${song.chord_source}`);
  if (song.original_artist) songMetadata.push(`Original: ${song.original_artist}`);
  if (isSpiritualOrParody && song.translator) songMetadata.push(`Translation: ${song.translator}`);
  if (songMetadata.length) {
    const metadataLine = document.createElement('div');
    metadataLine.style.marginTop = '4px';
    metadataLine.style.fontSize = '0.75rem';
    metadataLine.style.color = '#555';
    metadataLine.textContent = songMetadata.join(' · ');
    header.appendChild(metadataLine);
  }

  // Capo Display
  if (hasCapo) {
    const capoLine = document.createElement('div');
    capoLine.style.marginTop = '6px';
    capoLine.style.fontSize = '0.9rem';
    capoLine.style.color = '#444';
    capoLine.style.display = 'flex';
    capoLine.style.alignItems = 'center';
    capoLine.style.gap = '6px';

    const capoLabel = document.createElement('span');
    capoLabel.textContent = 'Capo:';
    capoLabel.style.fontWeight = '500';
    capoLine.appendChild(capoLabel);

    const transposedFromCapo = Number(song.transposedFromCapo) || 0;

    if (song.capo && song.capo !== 0) {
      const capoCircle = document.createElement('span');
      capoCircle.className = 'capo-circle';

      if (transposedFromCapo) {
        // capo was removed by transposing the chords up; show the delta
        // (e.g. original capo 2 -> "Transp. -2") instead of shrinking the header.
        capoCircle.textContent = `Transp. ${0 - transposedFromCapo}`;
        capoCircle.style.width = 'auto';
        capoCircle.style.minWidth = '24px';
        capoCircle.style.height = '24px';
        capoCircle.style.borderRadius = '12px';
        capoCircle.style.padding = '0 8px';
        capoCircle.style.whiteSpace = 'nowrap';
      } else {
        capoCircle.textContent = song.capo;
      }

    if (blackAndWhite) {
      // gray background fill for capo
      capoCircle.style.backgroundColor = '#bbb';
      capoCircle.style.color = '#222';
      capoCircle.style.border = '1px solid #888';
      capoCircle.style.borderRadius = transposedFromCapo ? '12px' : '50%';
      capoCircle.style.padding = transposedFromCapo ? '0 8px' : '2px 6px';
      capoCircle.style.minWidth = '24px';
      capoCircle.style.height = '24px';              // added for circle shape
      capoCircle.style.textAlign = 'center';
      capoCircle.style.display = 'flex';             // changed to flex
      capoCircle.style.alignItems = 'center';        // vertical center
      capoCircle.style.justifyContent = 'center';    // horizontal center
    }

      capoLine.appendChild(capoCircle);
    }

    if (song.capo_alt && song.capo_alt !== 0) {
      const capoAltCircle = document.createElement('span');
      capoAltCircle.className = 'capo-circle-alt';
      capoAltCircle.textContent = transposedFromCapo
        ? (Number(song.capo_alt) - transposedFromCapo)
        : song.capo_alt;

    if (blackAndWhite) {
      // outline only for capo_alt
      capoAltCircle.style.backgroundColor = 'transparent';
      capoAltCircle.style.color = '#222';
      capoAltCircle.style.border = '1px solid #222';
      capoAltCircle.style.borderRadius = '50%';
      capoAltCircle.style.padding = '2px 6px';
      capoAltCircle.style.minWidth = '24px';
      capoAltCircle.style.height = '24px';            // added for circle shape
      capoAltCircle.style.textAlign = 'center';
      capoAltCircle.style.display = 'flex';           // changed to flex
      capoAltCircle.style.alignItems = 'center';      // vertical center
      capoAltCircle.style.justifyContent = 'center';  // horizontal center
    }

      capoLine.appendChild(capoAltCircle);
    }

    // Tag/instrument badges that didn't fit in the title row get a second
    // chance here, since the capo row already reserves its own vertical
    // space - using it doesn't change the header's height. Anything that
    // still doesn't fit (rare - the capo row is much wider than the title
    // row's badge budget) collapses into "+N" the same as the title row does.
    // The group aligns under the title row's rightmost tag/instrument badge
    // (excluding difficulty, which sits apart on the far right) rather than
    // bunching up against the capo circle.
    if (capoOverflowBadges.length) {
      const capoRowGap = 6; // matches capoLine.style.gap above
      const usedWidth = measureElementWidth(capoLine);
      const naturalLeft = usedWidth + capoRowGap;
      // Falls back to right after the circle(s) if those are themselves
      // wider than the title row's rightmost badge position (rare - e.g. a
      // long "Transp. -2" label), rather than overlapping them.
      const badgeGroupLeft = Math.max(naturalLeft, extraWrapperLeftOffset);
      const capoRowBudget = Math.max(0, HEADER_CONTENT_WIDTH - badgeGroupLeft);

      const fitted = fitBadgesWithOverflow(capoOverflowBadges, capoRowBudget, blackAndWhite, BADGE_GAP);
      if (fitted.length) {
        const badgeGroup = document.createElement('span');
        badgeGroup.style.display = 'flex';
        badgeGroup.style.alignItems = 'center';
        badgeGroup.style.gap = `${BADGE_GAP}px`;
        badgeGroup.style.marginLeft = `${badgeGroupLeft - naturalLeft}px`;
        fitted.forEach(el => badgeGroup.appendChild(el));
        capoLine.appendChild(badgeGroup);
      }
    }

  header.appendChild(capoLine);
}

  header.style.marginBottom = options.marginBottom ?? '10px';

  if (options.fixedHeight !== undefined) {
    header.style.height = options.fixedHeight;
    header.style.overflow = 'hidden';
  }

  return header;
}







function measureBlockHeight(block, tempContainer) {
  tempContainer.appendChild(block);
  const height = block.getBoundingClientRect().height;
  tempContainer.removeChild(block);
  return height;
}

function wrapWithDebug(div, height) {
  const wrapper = document.createElement('div');
  wrapper.style.position = 'relative';

  const label = document.createElement('div');
  label.style.position = 'absolute';
  label.style.right = '-80px';
  label.style.fontSize = '12px';
  label.style.color = '#007bff';
  label.textContent = `${height.toFixed(0)}px`;

  wrapper.appendChild(div);
  wrapper.appendChild(label);
  return wrapper;
}

// Debug-only: draws a box over a finished page's unused space, in the same
// dashed-border/`#fdfdfd` style used for the song boxes, so the leftover
// area reads the same way a song block does instead of as a floating label.
function addPageRemainingSpaceLabel(page, usedHeight, pageMaxHeight) {
  const pagePadding = 24; // matches createPage's `.a4-page-content` p-4 (1.5rem)
  // pageMaxHeight (1123) is the page's OUTER height (createPage: box-sizing
  // border-box, fixed height, p-4 padding on the inner content layer) - the
  // content area inside the top+bottom padding is only
  // pageMaxHeight - 2*pagePadding tall. `top` below already accounts for the
  // top padding, but the old `remainingPx` never subtracted the bottom
  // padding too, so the box ran 24px past the real bottom of the content area
  // (and past the visible page on a nearly-full page).
  const contentHeight = pageMaxHeight - 2 * pagePadding;
  const remainingPx = contentHeight - usedHeight;

  const box = document.createElement('div');
  box.style.position = 'absolute';
  box.style.left = pagePadding + 'px';
  box.style.right = pagePadding + 'px';
  box.style.top = (pagePadding + usedHeight) + 'px';
  box.style.height = Math.max(0, remainingPx) + 'px';
  box.style.border = '1px dashed red';
  box.style.backgroundColor = '#fdfdfd';
  box.style.display = 'flex';
  box.style.alignItems = 'center';
  box.style.justifyContent = 'center';
  box.style.fontSize = '12px';
  box.style.fontWeight = 'bold';
  box.style.color = remainingPx < 0 ? '#dc3545' : '#28a745';
  box.style.zIndex = '5';
  box.textContent = `${Math.round(remainingPx)}px left`;
  page.appendChild(box);
}

// Debug-only: the loop's own overflow threshold (maxHeight, checked against
// currentHeight) doesn't account for the page's own padding, so a page can
// end up with more real content than actually fits inside its padded area
// without ever triggering a new page. createPage() now clips that content
// (page.contentEl has overflow:hidden) instead of silently stretching the
// page, which means it would otherwise just vanish - flag it loudly instead,
// directly on the unclipped outer `page` so the warning itself is never
// clipped away too.
function flagPageOverflowIfAny(page, usedHeight, pageMaxHeight, pagePadding) {
  const contentHeight = pageMaxHeight - 2 * pagePadding;
  const overflowPx = usedHeight - contentHeight;
  if (overflowPx <= 0) return;

  const warning = document.createElement('div');
  warning.style.position = 'absolute';
  warning.style.left = '0';
  warning.style.right = '0';
  warning.style.top = '0';
  warning.style.padding = '6px 10px';
  warning.style.backgroundColor = '#dc3545';
  warning.style.color = '#fff';
  warning.style.fontSize = '13px';
  warning.style.fontWeight = 'bold';
  warning.style.textAlign = 'center';
  warning.style.zIndex = '20';
  warning.textContent = `⚠ OVERFLOW: content is ${Math.round(overflowPx)}px taller than the page - clipped`;
  page.appendChild(warning);

  // Make the clipping itself obvious on the page too, not just the banner.
  page.style.outline = '4px solid #dc3545';
  page.style.outlineOffset = '-4px';
}

function createPage(pageNumber = null,category="") {
  const page = document.createElement('div');
  page.className = 'a4-page mb-4 bg-white position-relative';
  page.style.width = '794px'; // A4 width in pixels
  page.style.height = '1123px'; // A4 height in pixels - fixed, not min-height, so overpacked content clips instead of stretching the page taller than a real A4 sheet
  page.style.boxSizing = 'border-box';
  page.style.pageBreakAfter = 'always';

  // Real content (song blocks, TOC rows...) lives in this inner layer, not
  // directly on `page` - it carries the actual p-4 padding and is the thing
  // that clips overflowing content (overflow:hidden), while `page` itself
  // stays unclipped. That way debug-only decorations attached straight to
  // `page` (remaining-space box, per-song labels, overflow warnings...) -
  // some of which are deliberately positioned outside the page bounds, like
  // the per-song debug labels at left/right:-200px - keep showing instead of
  // being clipped along with genuinely overflowing content. Real content
  // should be appended to `page.contentEl`, not `page`.
  const content = document.createElement('div');
  content.className = 'a4-page-content p-4';
  content.style.position = 'absolute';
  content.style.inset = '0';
  content.style.boxSizing = 'border-box';
  content.style.overflow = 'hidden';
  page.appendChild(content);
  page.contentEl = content;

  const bottomDistance="20px";
  const sideDistance="30px";

  if (pageNumber !== null) {
    // Exposed so the table of contents can reference the exact page number
    // printed on this page (each category restarts its own numbering at 1).
    page.dataset.pageNumber = pageNumber;

    const pageNumberDiv = document.createElement('div');
    pageNumberDiv.className = 'page-number';
    pageNumberDiv.textContent = `${category} ${pageNumber}`;
    pageNumberDiv.style.position = 'absolute';
    pageNumberDiv.style.bottom = bottomDistance;
    if (pageNumber % 2 === 0) {
      // Even: align left
      pageNumberDiv.style.left = sideDistance;
      pageNumberDiv.style.right = 'auto';
      pageNumberDiv.style.textAlign = 'left';
    } else {
      // Odd: align right
      pageNumberDiv.style.right = sideDistance;
      pageNumberDiv.style.left = 'auto';
      pageNumberDiv.style.textAlign = 'right';
    }

    pageNumberDiv.style.fontSize = '12px';
    pageNumberDiv.style.color = '#555';
    page.appendChild(pageNumberDiv);
  }
  // Add vertical debug line at actual content midpoint
  const debugLine = document.createElement('div');
  debugLine.style.position = 'absolute';
  debugLine.style.top = '0';
  debugLine.style.bottom = '0';
  debugLine.style.left = '397px'; // 32px padding + half of 730px usable width
  debugLine.style.width = '1px';
  debugLine.style.backgroundColor = 'rgba(255, 0, 0, 0.5)';
  debugLine.style.zIndex = '999';
  return page;
}



// Exporting
async function exportToPDF() {
  const btn = document.getElementById('export-pdf-btn');
  btn.disabled = true;
  btn.textContent = '⏳ Generating...';

  try {
    await doExportLogic();
  } catch (err) {
    console.error('❌ Failed to export PDF:', err);
    alert('Failed to export PDF. See console for details.');
  } finally {
    btn.disabled = false;
    btn.textContent = '📄 Export to PDF';
  }
}

// Builds a jsPDF document from whatever's currently in #a4-preview-container.
// Shared by the local "Export to PDF" download and by saving a copy to the
// server (see songbook.php's "Save PDF" flow) so both produce identical output.
async function generatePdfDocument() {
  const { jsPDF } = window.jspdf;

  const container = document.getElementById('a4-preview-container');
  const pages = container.querySelectorAll('.a4-page');

  const pdf = new jsPDF({
    orientation: 'portrait',
    unit: 'pt',
    format: 'a4'
  });

  for (let i = 0; i < pages.length; i++) {
    const page = pages[i];

    // Clone and style page for accurate rendering
    const clone = page.cloneNode(true);
    clone.style.width = '794px';
    clone.style.height = '1123px';
    clone.style.padding = '0';
    clone.style.margin = '0';
    clone.style.backgroundColor = 'white';

    const wrapper = document.createElement('div');
    wrapper.style.width = '794px';
    wrapper.style.height = '1123px';
    wrapper.style.position = 'absolute';
    wrapper.style.top = '-9999px';
    wrapper.style.left = '-9999px';
    wrapper.style.backgroundColor = 'white';
    wrapper.appendChild(clone);
    document.body.appendChild(wrapper);

    const canvas = await html2canvas(wrapper, {
      scale: 1.5,  // try 1.5 or 1
      useCORS: true,
      backgroundColor: '#ffffff',
    });


    // const imgData = canvas.toDataURL('image/png');

    const imgData = canvas.toDataURL('image/jpeg', 0.9); // quality 0.7, adjust from 0.5 to 0.85


    const pdfWidth = pdf.internal.pageSize.getWidth();
    const pdfHeight = pdf.internal.pageSize.getHeight();

    if (i > 0) pdf.addPage();
    pdf.addImage(imgData, 'PNG', 0, 0, pdfWidth, pdfHeight);

    document.body.removeChild(wrapper);
  }

  return pdf;
}

async function doExportLogic(filename = 'songbook.pdf') {
  const pdf = await generatePdfDocument();
  pdf.save(filename);
}

// Used by the "Save PDF to server" flow - same rendering as the local
// download, just returned as a Blob for upload instead of triggering a
// browser save.
async function generatePdfBlob() {
  const pdf = await generatePdfDocument();
  return pdf.output('blob');
}

window.exportToPDF = exportToPDF;
window.generatePdfBlob = generatePdfBlob;
