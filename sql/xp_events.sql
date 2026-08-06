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

INSERT IGNORE INTO xp_events (user_id, amount, event_type, source_key, reference_id, created_at)
SELECT ux.user_id, ux.special_xp, 'legacy_special', CONCAT('legacy-special:', ux.user_id), ux.user_id, NOW()
FROM user_xp ux WHERE COALESCE(ux.special_xp, 0) <> 0;
