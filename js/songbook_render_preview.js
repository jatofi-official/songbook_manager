function renderA4Preview(orderedSongs) {
  const container = document.getElementById('a4-preview-container');
  container.innerHTML = '';

  let currentPage = createPage();
  container.appendChild(currentPage);

  let currentHeight = 0;
  const maxHeight = 1000; // max height per page (adjust as needed)

  orderedSongs.forEach(song => {
    let songDiv;

    if (song.right_content) {
      // Split song: two columns side-by-side
      
      songDiv = document.createElement('div');
      songDiv.className = 'song-block split-song d-flex mb-4'; 
      // 'd-flex' for horizontal flexbox; 'mb-4' for margin bottom
      
      // Left column with title, artist, and left_content
      const leftCol = document.createElement('div');
      leftCol.className = 'left-column flex-grow-1 pe-3'; // padding end for spacing
      const header = document.createElement('div');
      header.innerHTML = `<strong>${song.title}</strong> <em>${song.artist}</em>`;
      header.style.marginBottom = '10px';
      const leftPre = document.createElement('pre');
      leftPre.textContent = song.left_content;
      leftPre.style.margin = 0;

      leftCol.appendChild(header);
      leftCol.appendChild(leftPre);

      // Right column with right_content only (no title/artist)
      const rightCol = document.createElement('div');
      rightCol.className = 'right-column flex-grow-1 ps-3'; // padding start for spacing
      const rightPre = document.createElement('pre');
      rightPre.textContent = song.right_content;
      rightPre.style.margin = 0;

      rightCol.appendChild(rightPre);

      songDiv.appendChild(leftCol);
      songDiv.appendChild(rightCol);

    } else {
      // Single column song
      songDiv = document.createElement('div');
      songDiv.className = 'song-block mb-4';

      const header = document.createElement('div');
      header.innerHTML = `<strong>${song.title}</strong> <em>${song.artist}</em>`;
      header.style.marginBottom = '10px';

      const content = document.createElement('pre');
      content.textContent = song.left_content;
      content.style.margin = 0;

      songDiv.appendChild(header);
      songDiv.appendChild(content);
    }

    currentPage.appendChild(songDiv);

    // Check if current page height exceeded maxHeight
    if (currentPage.scrollHeight > maxHeight) {
      // Remove last songDiv from current page
      currentPage.removeChild(songDiv);

      // Create new page and add songDiv there
      currentPage = createPage();
      container.appendChild(currentPage);
      currentPage.appendChild(songDiv);
    }
  });
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
