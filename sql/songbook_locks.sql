CREATE TABLE `songbook_locks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `songbook_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `locked_by` int(11) NOT NULL,
  `locked_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `unlocked_by` int(11) DEFAULT NULL,
  `unlocked_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `songbook_id` (`songbook_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
