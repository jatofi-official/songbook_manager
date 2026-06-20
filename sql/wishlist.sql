-- Wishlist feature tables.
-- Run against the `songbook` database before deploying the wishlist page.
--
-- If you already created these tables from an earlier version of this script
-- (which had a `category_id` column on wishlist_songbooks), drop it instead:
--   ALTER TABLE wishlist_songbooks DROP COLUMN category_id;

CREATE TABLE wishlist_songs (
  id INT NOT NULL AUTO_INCREMENT,
  name VARCHAR(60) NOT NULL,
  artist VARCHAR(60) DEFAULT NULL,
  youtube_link VARCHAR(255) DEFAULT NULL,
  username VARCHAR(50) DEFAULT NULL,
  likes INT NOT NULL DEFAULT 1,
  create_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE wishlist_song_likes (
  id INT NOT NULL AUTO_INCREMENT,
  wishlist_song_id INT NOT NULL,
  username VARCHAR(50) NOT NULL,
  create_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_wsl (wishlist_song_id, username),
  CONSTRAINT fk_wsl_song FOREIGN KEY (wishlist_song_id)
    REFERENCES wishlist_songs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE wishlist_songbooks (
  id INT NOT NULL AUTO_INCREMENT,
  songbook_id INT NOT NULL,
  wishlist_song_id INT DEFAULT NULL,
  song_id INT DEFAULT NULL,
  username VARCHAR(50) DEFAULT NULL,
  likes INT NOT NULL DEFAULT 1,
  create_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_songbook (songbook_id),
  CONSTRAINT fk_wsb_songbook FOREIGN KEY (songbook_id)
    REFERENCES songbooks(id) ON DELETE CASCADE,
  CONSTRAINT fk_wsb_wishlist_song FOREIGN KEY (wishlist_song_id)
    REFERENCES wishlist_songs(id) ON DELETE CASCADE,
  CONSTRAINT fk_wsb_song FOREIGN KEY (song_id)
    REFERENCES songs(id) ON DELETE CASCADE,
  CONSTRAINT chk_wsb_target CHECK (
    (wishlist_song_id IS NOT NULL AND song_id IS NULL) OR
    (wishlist_song_id IS NULL AND song_id IS NOT NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE wishlist_songbook_likes (
  id INT NOT NULL AUTO_INCREMENT,
  wishlist_songbook_id INT NOT NULL,
  username VARCHAR(50) NOT NULL,
  create_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_wsbl (wishlist_songbook_id, username),
  CONSTRAINT fk_wsbl_wsb FOREIGN KEY (wishlist_songbook_id)
    REFERENCES wishlist_songbooks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
