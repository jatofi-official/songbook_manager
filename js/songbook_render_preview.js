function renderA4Preview(songs, orderedIds, options = {}) {
  const debug = options.debug || false;
  const category = options.category;
  const blackAndWhite = options.blackAndWhite || false;
  
  const container = document.getElementById('a4-preview-container');
  container.innerHTML = '';

  let processedSongs = songs.map(song => splitSongIfNeeded(song));
  const tempContainer = createHiddenMeasureContainer();
  document.body.appendChild(tempContainer);

  const maxHeight = 1123;
  let currentPageNum = 1;

  let pageRow = document.createElement('div');
  pageRow.className = 'page-pair d-flex justify-content-center gap-4 mb-5';
  container.appendChild(pageRow);

  let currentPage = createPage(currentPageNum,category);
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
      // Clickable songs
      songDiv.dataset.songId = partSong.id;

      // Left-click = select or swap
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

      // Right-click = move selected song after this one
      songDiv.addEventListener('contextmenu', (e) => {
        e.preventDefault(); // prevent context menu
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





      if (debug) {
        songDiv.style.border = '1px dashed red';
        songDiv.style.backgroundColor = '#fdfdfd';
      }

      if (!partSong.continuation) {
        const header = createSongHeader(partSong,{blackAndWhite});
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

        currentPage = createPage(currentPageNum,category);
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
  console.log(`✅ Total pages used: ${currentPageNum}`);
}






function createSongHeader(song, options = {}) {
  const header = document.createElement('div');
  const blackAndWhite = options.blackAndWhite || false;


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

  const titleText = document.createElement('span');
  titleText.textContent = song.title;
  titleLine.appendChild(titleText);

  if (song.artist && song.artist.trim() !== '') {
    const separator = document.createElement('span');
    separator.textContent = ' – ';
    separator.style.margin = '0 4px';

    const artistText = document.createElement('span');
    artistText.textContent = song.artist;
    artistText.style.fontWeight = 'normal';
    artistText.style.fontSize = '0.95rem';
    artistText.style.color = '#555';

    titleLine.appendChild(separator);
    titleLine.appendChild(artistText);
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

    if (song.capo && song.capo !== 0) {
      const capoCircle = document.createElement('span');
      capoCircle.className = 'capo-circle';
      capoCircle.textContent = song.capo;

    if (blackAndWhite) {
      // gray background fill for capo
      capoCircle.style.backgroundColor = '#bbb';
      capoCircle.style.color = '#222';
      capoCircle.style.border = '1px solid #888';
      capoCircle.style.borderRadius = '50%';
      capoCircle.style.padding = '2px 6px';
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
      capoAltCircle.textContent = song.capo_alt;

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
  page.className = 'a4-page border border-secondary mb-4 p-4 bg-white position-relative';
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
      scale: 2,
      useCORS: true,
      backgroundColor: '#ffffff',
    });

    const imgData = canvas.toDataURL('image/png');

    const pdfWidth = pdf.internal.pageSize.getWidth();
    const pdfHeight = pdf.internal.pageSize.getHeight();

    if (i > 0) pdf.addPage();
    pdf.addImage(imgData, 'PNG', 0, 0, pdfWidth, pdfHeight);

    document.body.removeChild(wrapper);
  }

  pdf.save('songbook.pdf');
}



window.exportToPDF = exportToPDF;
