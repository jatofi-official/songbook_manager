-- Import system schema (Fáza 1). Idempotentné (MariaDB IF NOT EXISTS).

ALTER TABLE user_settings
  ADD COLUMN IF NOT EXISTS import_enabled TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE songs
  ADD COLUMN IF NOT EXISTS source_url VARCHAR(500) DEFAULT NULL;

-- Tretí stav piesne "bez kontroly": importované, viditeľné (is_public=1),
-- ale NEráta sa ako finished (pre profil = unfinished, kým to človek neskontroluje).
ALTER TABLE songs
  ADD COLUMN IF NOT EXISTS is_unchecked TINYINT(1) NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS import_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  song_id INT DEFAULT NULL,
  source_type VARCHAR(20) NOT NULL,
  source_url VARCHAR(500) DEFAULT NULL,
  raw_content LONGTEXT DEFAULT NULL,
  confidence DECIMAL(3,2) DEFAULT 0.00,
  status VARCHAR(20) DEFAULT 'draft',
  created_by INT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  reviewed_at DATETIME DEFAULT NULL,
  songs_count INT DEFAULT 1,
  FOREIGN KEY (song_id) REFERENCES songs(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- AI metriky per import (pre adminov)
ALTER TABLE import_log
  ADD COLUMN IF NOT EXISTS ai_model VARCHAR(100) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS ai_ms INT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS ai_prompt_tokens INT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS ai_completion_tokens INT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS ai_error VARCHAR(255) DEFAULT NULL;

-- Globálne app nastavenia (OpenRouter kľúč, model, ...)
CREATE TABLE IF NOT EXISTS app_settings (
  skey VARCHAR(50) PRIMARY KEY,
  svalue TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO app_settings (skey, svalue) VALUES
  ('openrouter_api_key', ''),
  ('openrouter_model', 'meta-llama/llama-3.3-70b-instruct:free');
