CREATE TABLE `songbook_lock_snapshots` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lock_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `layout_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lock_category` (`lock_id`,`category_id`),
  KEY `layout_id` (`layout_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
