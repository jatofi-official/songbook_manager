CREATE TABLE `songbook_pdfs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `songbook_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `songbook_id` (`songbook_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
