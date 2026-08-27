-- Per-user song display font (song.php), alongside the existing colour theme
-- preference. 'monospace' keeps the original space-aligned <pre> rendering;
-- any other value switches to the proportional-font renderer in
-- js/chord_font_layout.js, which recalculates chord positions by measured
-- text width instead of relying on literal space padding.
ALTER TABLE user_settings
    ADD COLUMN IF NOT EXISTS song_font VARCHAR(20) NOT NULL DEFAULT 'monospace' AFTER theme;

UPDATE user_settings
SET song_font = 'monospace'
WHERE song_font NOT IN ('monospace', 'calibri') OR song_font IS NULL;
