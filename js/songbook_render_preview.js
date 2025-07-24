function renderA4Preview(orderedSongs) {
  const container = document.getElementById('a4-preview-container');
  container.innerHTML = '';

  // Create hidden temp container for measuring
  const tempContainer = document.createElement('div');
  tempContainer.style.position = 'absolute';
  tempContainer.style.visibility = 'hidden';
  tempContainer.style.width = '794px';
  tempContainer.style.fontFamily = 'Fira Mono, monospace';
  tempContainer.style.fontSize = '14px';
  tempContainer.style.lineHeight = '22px';
  tempContainer.style.whiteSpace = 'pre-wrap';
  tempContainer.style.padding = '32px';
  document.body.appendChild(tempContainer);

  const maxHeight = 1000;
  let currentPage = createPage();
  container.appendChild(currentPage);
  let currentHeight = 0;
  let totalPages = 1;

  orderedSongs.forEach((song, index) => {
    if (song.right_content && song.part === 'right') return;

    let songDiv;

    if (song.right_content) {
      // Two-column layout
      songDiv = document.createElement('div');
      songDiv.className = 'song-block split-song d-flex mb-4';
      songDiv.style.border = '1px dashed red';
      songDiv.style.backgroundColor = '#fdfdfd';

      const leftCol = document.createElement('div');
      leftCol.className = 'left-column flex-grow-1 pe-3';
      const header = document.createElement('div');
      header.innerHTML = `<strong>${song.title}</strong> <em>${song.artist}</em>`;
      header.style.marginBottom = '10px';
      const leftPre = document.createElement('pre');
      leftPre.textContent = song.left_content;
      leftPre.style.margin = 0;
      leftCol.appendChild(header);
      leftCol.appendChild(leftPre);

      const rightCol = document.createElement('div');
      rightCol.className = 'right-column flex-grow-1 ps-3';
      const rightPre = document.createElement('pre');
      rightPre.textContent = song.right_content;
      rightPre.style.margin = 0;
      rightCol.appendChild(rightPre);

      songDiv.appendChild(leftCol);
      songDiv.appendChild(rightCol);
    } else {
      // Single-column layout
      songDiv = document.createElement('div');
      songDiv.className = 'song-block mb-4';
      songDiv.style.border = '1px dashed red';
      songDiv.style.backgroundColor = '#fdfdfd';

      const header = document.createElement('div');
      header.innerHTML = `<strong>${song.title}</strong> <em>${song.artist}</em>`;
      header.style.marginBottom = '10px';

      const pre = document.createElement('pre');
      pre.textContent = song.left_content;
      pre.style.margin = 0;

      songDiv.appendChild(header);
      songDiv.appendChild(pre);
    }

    // Measure height
    tempContainer.appendChild(songDiv);
    const blockHeight = songDiv.getBoundingClientRect().height;
    tempContainer.removeChild(songDiv);

    console.log(`Song ${index}: "${song.title}" height = ${blockHeight}px (used so far = ${currentHeight}px)`);

    if (currentHeight + blockHeight > maxHeight) {
      console.log(`---- New Page ----`);
      currentPage = createPage();
      container.appendChild(currentPage);
      currentHeight = 0;
      totalPages++;
    }

    // Add debug label to side
    const debugLabel = document.createElement('div');
    debugLabel.style.position = 'absolute';
    debugLabel.style.right = '-80px';
    debugLabel.style.fontSize = '12px';
    debugLabel.style.color = '#007bff';
    debugLabel.textContent = `${blockHeight.toFixed(0)}px`;

    const wrapper = document.createElement('div');
    wrapper.style.position = 'relative';
    wrapper.appendChild(songDiv);
    wrapper.appendChild(debugLabel);

    currentPage.appendChild(wrapper);
    currentHeight += blockHeight;
  });

  document.body.removeChild(tempContainer);

  console.log(`✅ Total pages used: ${totalPages}`);
}



// Helper: create A4 page div
function createPage() {
  const page = document.createElement('div');
  page.className = 'a4-page p-4';  // padding inside the page
  page.style.minHeight = '1123px'; // A4 height in pixels at 96dpi approx
  page.style.maxWidth = '794px'; // A4 width in pixels approx
  page.style.border = '1px solid #aaa';
  page.style.margin = '16px auto';
  page.style.backgroundColor = 'white';
  page.style.boxSizing = 'border-box';
  return page;
}
