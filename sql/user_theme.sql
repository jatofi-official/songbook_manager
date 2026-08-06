-- Three-state colour theme. Existing users intentionally remain on light.
ALTER TABLE user_settings
    ADD COLUMN IF NOT EXISTS theme VARCHAR(10) NOT NULL DEFAULT 'light' AFTER dark_mode;

UPDATE user_settings
SET theme = 'light'
WHERE theme NOT IN ('light', 'dark', 'system') OR theme IS NULL;
