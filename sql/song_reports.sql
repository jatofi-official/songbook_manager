CREATE TABLE IF NOT EXISTS song_reports (
  id INT NOT NULL AUTO_INCREMENT,
  song_id INT NOT NULL,
  reported_by INT NOT NULL,
  report_text TEXT NOT NULL,
  selected_text TEXT DEFAULT NULL,
  status ENUM('pending','resolved') NOT NULL DEFAULT 'pending',
  reported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME DEFAULT NULL,
  resolved_by INT DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_song_reports_song_status (song_id, status),
  CONSTRAINT fk_song_reports_song FOREIGN KEY (song_id) REFERENCES songs(id) ON DELETE CASCADE,
  CONSTRAINT fk_song_reports_user FOREIGN KEY (reported_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE song_reports
  ADD COLUMN IF NOT EXISTS resolved_by INT DEFAULT NULL;
