-- Forward-only and safe to run repeatedly on MariaDB 10.5+.
ALTER TABLE songs
  ADD COLUMN IF NOT EXISTS chord_source ENUM('unknown','original','borrowed') NOT NULL DEFAULT 'unknown',
  ADD COLUMN IF NOT EXISTS original_artist VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS translator VARCHAR(255) DEFAULT NULL;

ALTER TABLE songs_history
  ADD COLUMN IF NOT EXISTS chord_source ENUM('unknown','original','borrowed') NOT NULL DEFAULT 'unknown',
  ADD COLUMN IF NOT EXISTS original_artist VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS translator VARCHAR(255) DEFAULT NULL;
