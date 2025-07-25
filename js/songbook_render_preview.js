function renderA4Preview(songs, orderedIds) {
  const container = document.getElementById('a4-preview-container');
  container.innerHTML = '';

  // Preprocess songs: add left_content and right_content
  let processedSongs = songs.map(song => splitSongIfNeeded(song));

  // Create hidden temp container for measuring
  const tempContainer = createHiddenMeasureContainer();

  document.body.appendChild(tempContainer);

  const maxHeight = 1300;
  let currentPage = createPage();
  container.appendChild(currentPage);
  let currentHeight = 0;
  let totalPages = 1;

  for (const id of orderedIds) {
    let song = processedSongs.find(s => s.id === id);
    if (!song) continue;

    // Skip right half standalone songs
    if (song.right_content && song.part === 'right') continue;

    // *** New: for single-column songs, try splitting by page height ***
    if (!song.right_content) {
      // Use your existing global splitPageIfNeeded that returns an array (one or two parts)
      const splitSongs = splitPageIfNeeded(song, tempContainer, maxHeight);

      for (const partSong of splitSongs) {
        // Render each partSong below (same as usual)

        let songDiv = document.createElement('div');
        songDiv.className = 'song-block mb-4';
        songDiv.style.border = '1px dashed red';
        songDiv.style.backgroundColor = '#fdfdfd';

        if (!partSong.continuation) {
          const header = document.createElement('div');
          header.innerHTML = `<strong>${partSong.title}</strong> <em>${partSong.artist}</em>`;
          header.style.marginBottom = '10px';
          songDiv.appendChild(header);
        }

        const pre = document.createElement('pre');
        pre.textContent = partSong.left_content;
        pre.style.margin = 0;

        songDiv.appendChild(pre);

        tempContainer.appendChild(songDiv);

        // Measure widest line width (reuse your existing logic)
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

        if (currentHeight + blockHeight > maxHeight) {
          currentPage = createPage();
          container.appendChild(currentPage);
          currentHeight = 0;
          totalPages++;
        }

        // Add debug label to side
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

    } else {
  // For two-column songs, render with full-width title/author header

  let songDiv = document.createElement('div');
  songDiv.className = 'song-block split-song mb-4';
  songDiv.style.border = '1px dashed red';
  songDiv.style.backgroundColor = '#fdfdfd';
  songDiv.style.display = 'flex';
  songDiv.style.flexDirection = 'column';

  // Full-width title and artist
  const header = document.createElement('div');
  header.innerHTML = `<strong>${song.title}</strong> <em>${song.artist}</em>`;
  header.style.marginBottom = '10px';
  songDiv.appendChild(header);

  // Columns wrapper
  const columns = document.createElement('div');
  columns.className = 'd-flex';

  const leftCol = document.createElement('div');
  leftCol.className = 'left-column flex-grow-1 pe-3';

  const leftPre = document.createElement('pre');
  leftPre.textContent = song.left_content;
  leftPre.style.margin = 0;
  leftCol.appendChild(leftPre);

  const rightCol = document.createElement('div');
  rightCol.className = 'right-column flex-grow-1 ps-3';

  const rightPre = document.createElement('pre');
  rightPre.textContent = song.right_content;
  rightPre.style.margin = 0;
  rightCol.appendChild(rightPre);

  columns.appendChild(leftCol);
  columns.appendChild(rightCol);
  songDiv.appendChild(columns);

  tempContainer.appendChild(songDiv);

  // Measure widest line width
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

  if (currentHeight + blockHeight > maxHeight) {
    currentPage = createPage();
    container.appendChild(currentPage);
    currentHeight = 0;
    totalPages++;
  }

  // Add debug label to side
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
  console.log(`✅ Total pages used: ${totalPages}`);
}




function renderSingleColumnSong(song, content) {
  const songDiv = document.createElement('div');
  songDiv.className = 'song-block mb-4';
  songDiv.style.border = '1px dashed red';
  songDiv.style.backgroundColor = '#fdfdfd';

  const header = document.createElement('div');
  header.innerHTML = `<strong>${song.title}</strong> <em>${song.artist}</em>`;
  header.style.marginBottom = '10px';

  const pre = document.createElement('pre');
  pre.textContent = content;
  pre.style.margin = 0;

  songDiv.appendChild(header);
  songDiv.appendChild(pre);
  return songDiv;
}

function renderTwoColumnHalf(song, content, isLeft) {
  const colDiv = document.createElement('div');
  colDiv.className = 'song-block mb-4';
  colDiv.style.border = '1px dashed red';
  colDiv.style.backgroundColor = '#fdfdfd';

  if (isLeft) {
    const header = document.createElement('div');
    header.innerHTML = `<strong>${song.title}</strong> <em>${song.artist}</em>`;
    header.style.marginBottom = '10px';
    colDiv.appendChild(header);
  }

  const pre = document.createElement('pre');
  pre.textContent = content;
  pre.style.margin = 0;
  colDiv.appendChild(pre);

  return colDiv;
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
//   page.style.minHeight = '1123px';
//   page.style.maxWidth = '794px';
//   page.style.border = '1px solid #aaa';
//   page.style.margin = '16px auto';
//   page.style.backgroundColor = 'white';
//   page.style.boxSizing = 'border-box';
//   return page;
// }
function createPage() {
  const page = document.createElement('div');
  page.className = 'a4-page p-4';
  page.style.position = 'relative';
  page.style.minHeight = '1123px';
  page.style.maxWidth = '794px'; // A4 width at 96dpi
  page.style.margin = '16px auto'; // Center horizontally + add vertical space
  page.style.backgroundColor = 'white';
  page.style.border = '1px solid #aaa';
  page.style.boxSizing = 'border-box';
  page.style.overflow = 'visible';
  page.style.padding = '32px';

  // Add vertical debug line at actual content midpoint
  const debugLine = document.createElement('div');
  debugLine.style.position = 'absolute';
  debugLine.style.top = '0';
  debugLine.style.bottom = '0';
  debugLine.style.left = '397px'; // 32px padding + half of 730px usable width
  debugLine.style.width = '1px';
  debugLine.style.backgroundColor = 'rgba(255, 0, 0, 0.5)';
  debugLine.style.zIndex = '999';

  page.appendChild(debugLine);

  return page;
}
