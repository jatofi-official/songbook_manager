CREATE TABLE `songbook_render_presets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `songbook_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `settings` text NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `songbook_id` (`songbook_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
