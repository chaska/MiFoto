-- 0.2.0: hourly upload limit per person.
CREATE TABLE IF NOT EXISTS `#__mifoto_limits` (
  `user_id` int unsigned NOT NULL,
  `window_start` datetime NOT NULL COMMENT 'start of the current hour window (UTC)',
  `uploads` smallint unsigned NOT NULL DEFAULT 0 COMMENT 'upload attempts in this window',
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
