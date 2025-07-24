function renderA4Preview(orderedSongs) {
    const container = document.getElementById('a4-preview-container');
    container.innerHTML = '';

    let currentPage = createPage();
    let currentHeight = 0;
    const maxHeight = 1000; // Slightly less than full page to allow buffer

    for (let i = 0; i < orderedSongs.length; i++) {
        const song = orderedSongs[i];

        if (song.isSplit && song.part === 1) {
            // Find matching part 2
            const nextSong = orderedSongs[i + 1];
            if (nextSong && nextSong.isSplit && nextSong.part === 2 && nextSong.id === song.id) {
                // Create two-column container for split song
                const twoColDiv = document.createElement('div');
                twoColDiv.className = 'song-block mb-4';
                twoColDiv.style.display = 'flex';
                twoColDiv.style.justifyContent = 'space-between';

                // Left column
                const leftCol = document.createElement('div');
                leftCol.style.flex = '1';
                leftCol.style.marginRight = '1em';

                const leftHeader = document.createElement('div');
                leftHeader.innerHTML = `<strong>${song.title}</strong> <em>${song.artist}</em>`;
                leftHeader.style.marginBottom = '10px';
                leftCol.appendChild(leftHeader);

                const leftContent = document.createElement('pre');
                leftContent.textContent = song.content;
                leftContent.style.margin = 0;
                leftContent.style.whiteSpace = 'pre-wrap';
                leftContent.style.fontFamily = "'Fira Mono', monospace";
                leftCol.appendChild(leftContent);

                // Right column — no title or artist
                const rightCol = document.createElement('div');
                rightCol.style.flex = '1';

                const rightContent = document.createElement('pre');
                rightContent.textContent = nextSong.content;
                rightContent.style.margin = 0;
                rightContent.style.whiteSpace = 'pre-wrap';
                rightContent.style.fontFamily = "'Fira Mono', monospace";
                rightCol.appendChild(rightContent);

                twoColDiv.appendChild(leftCol);
                twoColDiv.appendChild(rightCol);


                currentPage.appendChild(twoColDiv);
                container.appendChild(currentPage);

                // Check height and paginate if needed
                if (currentPage.scrollHeight > maxHeight) {
                    currentPage.removeChild(twoColDiv);
                    currentPage = createPage();
                    container.appendChild(currentPage);
                    currentPage.appendChild(twoColDiv);
                }

                // Skip next song (part 2)
                i++;
                continue;
            }
        }

        // Normal single song rendering
        const songDiv = document.createElement('div');
        songDiv.className = 'song-block mb-4';

        const header = document.createElement('div');
        header.innerHTML = `<strong>${song.title}</strong> <em>${song.artist}</em>`;
        header.style.marginBottom = '10px';

        const content = document.createElement('pre');
        content.textContent = song.content;
        content.style.margin = 0;
        content.style.whiteSpace = 'pre-wrap';
        content.style.fontFamily = "'Fira Mono', monospace";

        songDiv.appendChild(header);
        songDiv.appendChild(content);

        currentPage.appendChild(songDiv);
        container.appendChild(currentPage);

        // Check height and paginate if needed
        if (currentPage.scrollHeight > maxHeight) {
            currentPage.removeChild(songDiv);
            currentPage = createPage();
            container.appendChild(currentPage);
            currentPage.appendChild(songDiv);
        }
    }
}

function createPage() {
    const page = document.createElement('div');
    page.className = 'a4-page';
    // Optional: add style to simulate A4 page size (e.g., width, height, border)
    page.style.width = '794px'; // A4 width at 96dpi approx.
    page.style.minHeight = '1123px'; // A4 height at 96dpi approx.
    page.style.margin = '1em auto';
    page.style.padding = '1em';
    page.style.boxSizing = 'border-box';
    page.style.border = '1px solid #ccc';
    page.style.background = 'white';
    return page;
}
