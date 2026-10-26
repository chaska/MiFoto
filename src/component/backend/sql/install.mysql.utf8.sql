CREATE TABLE IF NOT EXISTS `#__mifoto_photos` (
  `user_id` int unsigned NOT NULL,
  `pkey` char(16) NOT NULL COMMENT 'public random key; the Joomla user id never leaves the server',
  `token` char(24) NOT NULL COMMENT 'changes on every upload',
  `width` smallint unsigned NOT NULL DEFAULT 0,
  `height` smallint unsigned NOT NULL DEFAULT 0,
  `bytes` int unsigned NOT NULL DEFAULT 0,
  `state` tinyint NOT NULL DEFAULT 1 COMMENT '1 visible, 0 hidden by a moderator',
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `idx_pkey` (`pkey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__mifoto_limits` (
  `user_id` int unsigned NOT NULL,
  `window_start` datetime NOT NULL COMMENT 'start of the current hour window (UTC)',
  `uploads` smallint unsigned NOT NULL DEFAULT 0 COMMENT 'upload attempts in this window',
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
