-- Combined migration for merging `codex/main-reader-tools` into `main`.
-- Paste this whole file into the phpMyAdmin SQL tab and run it once.
--
-- ALREADY DONE (do not re-run): sql/songs_history.sql was applied manually,
-- so `songs_history` already has strumming_pattern/chord_source/
-- original_artist/translator. It is intentionally left out of this file.
--
-- NOT included: sql/songs_history_backfill.sql. It only inserts a baseline
-- history row for songs that have zero history rows yet. Since the backfill
-- already ran when song history first shipped, every song already has a row,
-- so re-running it is a guaranteed no-op on production; the new columns on
-- old rows simply default to NULL/'unknown', which is correct.


-- ============================================================
-- 1. sql/song_metadata.sql (songs table only — songs_history done manually)
-- New song fields: strumming pattern, chord source, original artist, translator.
-- ============================================================
ALTER TABLE songs
  ADD COLUMN IF NOT EXISTS strumming_pattern VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS chord_source ENUM('unknown','original','borrowed') NOT NULL DEFAULT 'unknown',
  ADD COLUMN IF NOT EXISTS original_artist VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS translator VARCHAR(255) DEFAULT NULL;


-- ============================================================
-- 2. sql/user_theme.sql
-- Three-state colour theme (light/dark/system).
-- The original file positions this "AFTER dark_mode", but no `dark_mode`
-- column exists anywhere else in the codebase/history, so that clause is
-- dropped here to avoid an "Unknown column 'dark_mode'" error.
-- ============================================================
ALTER TABLE user_settings
    ADD COLUMN IF NOT EXISTS theme VARCHAR(10) NOT NULL DEFAULT 'light';

UPDATE user_settings
SET theme = 'light'
WHERE theme NOT IN ('light', 'dark', 'system') OR theme IS NULL;


-- ============================================================
-- 3. sql/xp_events.sql
-- New XP ledger table backing the leaderboard, plus a one-time backfill of
-- legacy XP (song adds, edits, surveys, manual special_xp) into ledger rows.
-- The backfill uses INSERT IGNORE against a UNIQUE source_key, so it is
-- also safe to re-run.
-- ============================================================
CREATE TABLE IF NOT EXISTS xp_events (
  id BIGINT NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  amount INT NOT NULL,
  event_type VARCHAR(40) NOT NULL,
  source_key VARCHAR(191) NOT NULL,
  reference_id BIGINT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_xp_events_source (source_key),
  KEY idx_xp_events_user_date (user_id, created_at),
  CONSTRAINT fk_xp_events_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Existing songs receive their current add-song XP. Deleted songs are absent,
-- matching the legacy rule that deletion removes XP for adding that song.
INSERT IGNORE INTO xp_events (user_id, amount, event_type, source_key, reference_id, created_at)
SELECT u.id, 50, 'song_add', CONCAT('legacy-song:', s.id), s.id, COALESCE(s.create_date, NOW())
FROM songs s JOIN users u ON u.username = s.owner;

INSERT IGNORE INTO xp_events (user_id, amount, event_type, source_key, reference_id, created_at)
SELECT u.id, 10, 'song_edit', CONCAT('legacy-edit:', a.id), NULL, a.time
FROM activity_log a JOIN users u ON u.username = a.username
WHERE a.type = 'edit';

INSERT IGNORE INTO xp_events (user_id, amount, event_type, source_key, reference_id, created_at)
SELECT u.id, 250, 'survey', CONCAT('legacy-survey:', a.id), NULL, a.time
FROM activity_log a JOIN users u ON u.username = a.username
WHERE a.type = 'Anketa';

-- No real "earned on" date exists for this lump sum, so it's backdated well
-- outside any week/month window rather than stamped with NOW() (which would
-- make it look like it was earned today, inflating time-windowed leaderboards).
INSERT IGNORE INTO xp_events (user_id, amount, event_type, source_key, reference_id, created_at)
SELECT ux.user_id, ux.special_xp, 'legacy_special', CONCAT('legacy-special:', ux.user_id), ux.user_id, DATE_SUB(NOW(), INTERVAL 10 YEAR)
FROM user_xp ux WHERE COALESCE(ux.special_xp, 0) <> 0;
