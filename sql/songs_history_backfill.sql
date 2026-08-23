-- Run once after creating songs_history (songs_history.sql) to seed a
-- baseline snapshot for every song that already exists in `songs`.
-- Safe to re-run: songs that already have a history row are skipped.
--
-- activity_log has no song_id column, so the author of the most recent
-- matching 'add'/'edit' activity_log entry (matched on title/artist text,
-- since that's how add_activity() records it) is used as changed_by.
-- Falls back to the song's current owner when no match is found.

INSERT INTO songs_history (
    song_id, action, changed_by,
    category, title, artist, capo, capo_alt, owner, is_public,
    create_date, modified_date, tags, instruments, content,
    is_fixed, is_nsfw, youtube_link, views,
    chord_source, original_artist, translator
)
SELECT
    s.id,
    'initial',
    COALESCE(
        (SELECT a.username
         FROM activity_log a
         WHERE a.type IN ('add', 'edit')
           AND a.description LIKE CONCAT('%', s.title, '%')
           AND (s.artist IS NULL OR a.description LIKE CONCAT('%', s.artist, '%'))
         ORDER BY a.time DESC
         LIMIT 1),
        s.owner
    ),
    s.category, s.title, s.artist, s.capo, s.capo_alt, s.owner, s.is_public,
    s.create_date, s.modified_date, s.tags, s.instruments, s.content,
    s.is_fixed, s.is_nsfw, s.youtube_link, s.views,
    s.chord_source, s.original_artist, s.translator
FROM songs s
WHERE NOT EXISTS (
    SELECT 1 FROM songs_history h WHERE h.song_id = s.id
);
