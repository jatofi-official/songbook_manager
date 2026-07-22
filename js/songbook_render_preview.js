function renderA4Preview(songs, orderedIds, options = {}) {
  const debug = options.debug || false;
  const category = options.category;
  const blackAndWhite = options.blackAndWhite || false;
  const allowClicking = options.allowClicking || false;  // default false
  const transposeToCapoZero = options.transposeToCapoZero || false;
  const showDifficulty = options.showDifficulty || false;
  const showTags = options.showTags || false;
  const showInstruments = false; // instruments extra-display is still in development

  const container = document.getElementById('a4-preview-container');

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
  let currentPageNum = 1;

  let pageRow = document.createElement('div');
  pageRow.className = 'page-pair d-flex justify-content-center gap-4 mb-5';
  container.appendChild(pageRow);

  let currentPage = createPage(currentPageNum, category);
  pageRow.appendChild(currentPage);

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

    const songParts = song.right_content
      ? [song]
      : splitPageIfNeeded(song, tempContainer, maxHeight);

    for (const partSong of songParts) {
      const songDiv = document.createElement('div');
      songDiv.className = 'song-block mb-4';
      songDiv.dataset.songId = partSong.id;

      if (allowClicking) {
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
        const header = createSongHeader(partSong, { blackAndWhite, showDifficulty, showTags, showInstruments });
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

      const blockHeight = songDiv.getBoundingClientRect().height;
      tempContainer.removeChild(songDiv);

      if (currentHeight + blockHeight > maxHeight) {
        currentPageNum++;
        currentHeight = 0;

        if (currentPageNum % 2 === 0) {
          pageRow = document.createElement('div');
          pageRow.className = 'page-pair d-flex justify-content-center gap-4 mb-5';
          container.appendChild(pageRow);
        }

        currentPage = createPage(currentPageNum, category);
        pageRow.appendChild(currentPage);

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

      if (debug) {
        const debugLabel = document.createElement('div');
        debugLabel.style.position = 'absolute';
        debugLabel.style.right = '-200px';
        debugLabel.style.bottom = '50px';
        debugLabel.style.fontSize = '12px';
        debugLabel.style.color = '#007bff';
        debugLabel.textContent = `Height: ${blockHeight.toFixed(0)}px Width: ${maxLineWidth.toFixed(0)}px`;
        wrapper.appendChild(debugLabel);
      }

      currentPage.appendChild(wrapper);
      currentHeight += wrapper.getBoundingClientRect().height;
    }
  }

  document.body.removeChild(tempContainer);
  
  // Check if total pages is odd, add blank page if so
  if (currentPageNum % 2 === 1) {
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
    pageRow.appendChild(blankPage);
  }




  return currentPageNum;

}







// Small single-line badges used by the "Extra Display" dev-tools options.
// Kept deliberately compact (small font, tight padding) so they never grow
// taller than the title line's own text, and never change header height.
function buildDifficultyBadge(difficulty, blackAndWhite) {
  if (!difficulty) return null;
  const diff = String(difficulty).toLowerCase();
  const colors = { easy: '#198754', normal: '#6c757d', hard: '#dc3545' };

  const span = document.createElement('span');
  span.textContent = diff.charAt(0).toUpperCase() + diff.slice(1);
  span.style.display = 'inline-flex';
  span.style.alignItems = 'center';
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
  span.style.display = 'inline-flex';
  span.style.alignItems = 'center';
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
  // showInstruments intentionally unused for now - feature still in development


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

  // Extra Display: difficulty + tags, rendered to the right of the title.
  // Rendered into a fixed-shrink, overflow-hidden, single-line wrapper so it
  // can never make the header (and therefore the whole song block) taller.
  if (showDifficulty || showTags) {
    const extraWrapper = document.createElement('span');
    extraWrapper.style.display = 'flex';
    extraWrapper.style.alignItems = 'center';
    extraWrapper.style.gap = '4px';
    extraWrapper.style.flex = '0 1 auto';
    extraWrapper.style.maxWidth = '55%';
    extraWrapper.style.overflow = 'hidden';
    extraWrapper.style.whiteSpace = 'nowrap';

    
    if (showTags && song.tags) {
      song.tags.split(',')
      .map(t => t.trim())
      .filter(Boolean)
      .forEach(tag => extraWrapper.appendChild(buildTagBadge(tag, blackAndWhite)));
    }
    
    if (showDifficulty) {
      const diffBadge = buildDifficultyBadge(song.difficulty, blackAndWhite);
      if (diffBadge) extraWrapper.appendChild(diffBadge);
    }
    if (extraWrapper.children.length) {
      titleLine.appendChild(extraWrapper);
    }
  }

  header.appendChild(titleLine);

  // Capo Display
  if ((song.capo && song.capo !== 0) || (song.capo_alt && song.capo_alt !== 0)) {
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

function createPage(pageNumber = null,category="") {
  const page = document.createElement('div');
  page.className = 'a4-page mb-4 p-4 bg-white position-relative';
  page.style.width = '794px'; // A4 width in pixels
  page.style.minHeight = '1123px'; // A4 height in pixels
  page.style.boxSizing = 'border-box';
  page.style.pageBreakAfter = 'always';

  const bottomDistance="20px";
  const sideDistance="30px";

  if (pageNumber !== null) {
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

async function doExportLogic() {
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

  pdf.save('songbook.pdf');
}



window.exportToPDF = exportToPDF;
