function renderA4Preview(songs, orderedIds) {
  const container = document.getElementById('a4-preview-container');
  container.innerHTML = '';

  let processedSongs = songs.map(song => splitSongIfNeeded(song));
  const tempContainer = createHiddenMeasureContainer();
  document.body.appendChild(tempContainer);

  const maxHeight = 1300;

  // Start rendering from page 1 (no blank page)
  let currentPageNum = 1;

  // Create first page row and append first page
  let pageRow = document.createElement('div');
  pageRow.className = 'page-pair d-flex justify-content-center gap-4 mb-5';
  container.appendChild(pageRow);

  let currentPage = createPage(currentPageNum);
  pageRow.appendChild(currentPage);

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
      songDiv.style.border = '1px dashed red';
      songDiv.style.backgroundColor = '#fdfdfd';

      if (!partSong.continuation) {
        const header = createSongHeader(partSong);
        songDiv.appendChild(header);
      }

      if (!partSong.right_content) {
        // single column
        const pre = document.createElement('pre');
        pre.textContent = partSong.left_content;
        pre.style.margin = 0;
        songDiv.appendChild(pre);
      } else {
        // two column - do NOT add header here again!
        songDiv.classList.add('split-song');
        songDiv.style.display = 'flex';
        songDiv.style.flexDirection = 'column';

        const columns = document.createElement('div');
        columns.className = 'd-flex';

        const leftCol = document.createElement('div');
        leftCol.className = 'left-column flex-grow-1 pe-3';
        const leftPre = document.createElement('pre');
        leftPre.textContent = partSong.left_content;
        leftPre.style.margin = 0;
        leftCol.appendChild(leftPre);

        const rightCol = document.createElement('div');
        rightCol.className = 'right-column flex-grow-1 ps-3';
        const rightPre = document.createElement('pre');
        rightPre.textContent = partSong.right_content;
        rightPre.style.margin = 0;
        rightCol.appendChild(rightPre);

        columns.appendChild(leftCol);
        columns.appendChild(rightCol);
        songDiv.appendChild(columns);
      }

      tempContainer.appendChild(songDiv);

      // Width measurement
      let maxLineWidth = 0;
      const preElements = songDiv.querySelectorAll('pre');
      preElements.forEach(pre => {
        const lines = pre.textContent.split('\n');
        lines.forEach(line => {
          const trimmedLine = line.replace(/\s+$/, '');
          const measurer = document.createElement('span');
          measurer.style.visibility = 'hidden';
          measurer.style.position = 'absolute';
          measurer.style.whiteSpace = 'pre';
          measurer.style.fontFamily = 'Fira Mono, monospace';
          measurer.style.fontSize = '14px';
          measurer.textContent = trimmedLine;
          tempContainer.appendChild(measurer);
          const lineWidth = measurer.getBoundingClientRect().width;
          tempContainer.removeChild(measurer);
          if (lineWidth > maxLineWidth) maxLineWidth = lineWidth;
        });
      });

      const blockHeight = songDiv.getBoundingClientRect().height;
      tempContainer.removeChild(songDiv);

      // If doesn't fit, create a new page
      if (currentHeight + blockHeight > maxHeight) {
        currentPageNum++;
        currentHeight = 0;

        // If even page number, start a new row (page pairs)
        if (currentPageNum % 2 === 0) {
          pageRow = document.createElement('div');
          pageRow.className = 'page-pair d-flex justify-content-center gap-4 mb-5';
          container.appendChild(pageRow);
        }

        currentPage = createPage(currentPageNum);
        pageRow.appendChild(currentPage);
      }

      // Add debug label
      const debugLabel = document.createElement('div');
      debugLabel.style.position = 'absolute';
      debugLabel.style.right = '-200px';
      debugLabel.style.bottom = '50px';
      debugLabel.style.fontSize = '12px';
      debugLabel.style.color = '#007bff';
      debugLabel.textContent = `Height: ${blockHeight.toFixed(0)}px Width: ${maxLineWidth.toFixed(0)}px`;

      const wrapper = document.createElement('div');
      wrapper.style.position = 'relative';
      wrapper.appendChild(songDiv);
      wrapper.appendChild(debugLabel);

      currentPage.appendChild(wrapper);
      currentHeight += blockHeight;
    }
  }

  document.body.removeChild(tempContainer);
  console.log(`✅ Total pages used: ${currentPageNum}`);
}



function createSongHeader(song, options = {}) {
  const header = document.createElement('div');
  header.style.display = 'inline-block';
  header.style.border = options.border || '1px solid #ccc';
  header.style.padding = options.padding || '6px 10px';
  header.style.borderRadius = options.borderRadius || '6px';
  header.style.backgroundColor = options.backgroundColor || '#f9f9f9';

  // Title and optional artist
  const titleLine = document.createElement('div');
  titleLine.style.fontWeight = 'bold';
  titleLine.style.fontSize = '16px';

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
    artistText.style.fontSize = '14px';
    artistText.style.color = '#555';

    titleLine.appendChild(separator);
    titleLine.appendChild(artistText);
  }

  header.appendChild(titleLine);

  // Capo line
  if (song.capo !== undefined && song.capo !== null && song.capo !== 0) {
    const capoLine = document.createElement('div');
    capoLine.style.marginTop = '4px';
    capoLine.style.fontSize = '13px';
    capoLine.style.color = '#444';

    const capoLabel = document.createElement('span');
    capoLabel.textContent = 'Capo: ';
    capoLine.appendChild(capoLabel);

    const capoCircle = document.createElement('span');
    capoCircle.textContent = song.capo;
    capoCircle.style.display = 'inline-block';
    capoCircle.style.width = '20px';
    capoCircle.style.height = '20px';
    capoCircle.style.lineHeight = '20px';
    capoCircle.style.borderRadius = '50%';
    capoCircle.style.backgroundColor = '#ddd';
    capoCircle.style.textAlign = 'center';
    capoCircle.style.fontWeight = 'bold';
    capoCircle.style.marginLeft = '4px';
    capoCircle.style.fontSize = '13px';

    capoLine.appendChild(capoCircle);
    header.appendChild(capoLine);
  }

  // Margin bottom (default 10px)
  header.style.marginBottom = options.marginBottom ?? '10px';

  // Fixed height if specified
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


// function createPage() {
//   const page = document.createElement('div');
//   page.className = 'a4-page p-4';
//   page.style.position = 'relative';
//   page.style.minHeight = '1123px';
//   page.style.maxWidth = '794px'; // A4 width at 96dpi
//   page.style.margin = '16px auto'; // Center horizontally + add vertical space
//   page.style.backgroundColor = 'white';
//   page.style.border = '1px solid #aaa';
//   page.style.boxSizing = 'border-box';
//   page.style.overflow = 'visible';
//   page.style.padding = '32px';

//   // Add vertical debug line at actual content midpoint
//   const debugLine = document.createElement('div');
//   debugLine.style.position = 'absolute';
//   debugLine.style.top = '0';
//   debugLine.style.bottom = '0';
//   debugLine.style.left = '397px'; // 32px padding + half of 730px usable width
//   debugLine.style.width = '1px';
//   debugLine.style.backgroundColor = 'rgba(255, 0, 0, 0.5)';
//   debugLine.style.zIndex = '999';

//   page.appendChild(debugLine);

//   return page;
// }

function createPage(pageNumber = null) {
  const page = document.createElement('div');
  page.className = 'a4-page border border-secondary mb-4 p-4 bg-white position-relative';
  page.style.width = '794px'; // A4 width in pixels
  page.style.minHeight = '1123px'; // A4 height in pixels
  page.style.boxSizing = 'border-box';
  page.style.pageBreakAfter = 'always';

  if (pageNumber !== null) {
    const pageNumberDiv = document.createElement('div');
    pageNumberDiv.className = 'page-number';
    pageNumberDiv.textContent = `Page ${pageNumber}`;
    pageNumberDiv.style.position = 'absolute';
    pageNumberDiv.style.bottom = '10px';
    pageNumberDiv.style.left = '50%';
    pageNumberDiv.style.transform = 'translateX(-50%)';
    pageNumberDiv.style.fontSize = '12px';
    pageNumberDiv.style.color = '#555';
    page.appendChild(pageNumberDiv);
  }

  return page;
}
