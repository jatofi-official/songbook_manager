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

// Attempt to split long songs into two columns (at empty lines)
function splitSongIfNeeded(song) {
    const lines = song.content?.split('\n') || [];
    const maxLinesPerColumn = 25;
    const maxLineLength = 40; // Approximate half-page width in chars (adjust as needed)

    // Find longest line excluding trailing spaces
    let longestLineLength = 0;
    for (const line of lines) {
        const trimmedLine = line.replace(/\s+$/, ''); // remove trailing spaces only
        if (trimmedLine.length > longestLineLength) {
            longestLineLength = trimmedLine.length;
        }
    }

    if (lines.length <= maxLinesPerColumn || longestLineLength > maxLineLength) {
        // Either fits in one column or lines too long to split
        return [song];
    }

    // Try to find a good split point after first maxLinesPerColumn lines
    const splitIndices = [];
    for (let i = maxLinesPerColumn; i < lines.length; i++) {
        if (lines[i].trim() === '') {
            splitIndices.push(i);
        }
    }

    if (splitIndices.length > 0) {
        const splitAt = splitIndices[0]; // First safe break point
        const part1 = {
            ...song,
            content: lines.slice(0, splitAt).join('\n'),
            isSplit: true,
            part: 1
        };
        const part2 = {
            ...song,
            content: lines.slice(splitAt).join('\n'),
            isSplit: true,
            part: 2
        };
        return [part1, part2];
    }

    // No good place to split
    return [song];
}


// Main function to order songs
function optimizeSongOrder(songs) {
    const grouped = groupSongsByArtist(songs);
    const ordered = [];

    grouped.forEach(group => {
        group.forEach(song => {
            const blocks = splitSongIfNeeded(song);
            blocks.forEach(block => ordered.push(block));
        });
    });

    return ordered;
}
