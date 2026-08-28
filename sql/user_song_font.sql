-- Per-user song display font (song.php), alongside the existing colour theme
-- preference. 'monospace' keeps the original space-aligned <pre> rendering;
-- any other value switches to the proportional-font renderer in
-- js/chord_font_layout.js, which recalculates chord positions by measured
-- text width instead of relying on literal space padding.
--
-- Plain ADD COLUMN (no IF NOT EXISTS) - that clause needs MySQL 8.0.29+/
-- MariaDB 10.4.7+, which older phpMyAdmin/MySQL installs (like the one that
-- rejected the original version of this file) don't support. If the column
-- already exists, MySQL will error "Duplicate column name 'song_font'" here
-- - that's harmless, it just means this part already ran; skip straight to
-- the UPDATE below.
ALTER TABLE user_settings
    ADD COLUMN song_font VARCHAR(20) NOT NULL DEFAULT 'monospace' AFTER theme;

UPDATE user_settings
SET song_font = 'monospace'
WHERE song_font NOT IN ('monospace', 'calibri', 'arial', 'verdana', 'georgia', 'times') OR song_font IS NULL;
