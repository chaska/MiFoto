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

CREATE TABLE IF NOT EXISTS `#__mifoto_consents` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `version` smallint unsigned NOT NULL DEFAULT 1 COMMENT 'version of the accepted text',
  `token` char(24) NOT NULL DEFAULT '' COMMENT 'the photo this acceptance was given for',
  `accepted` datetime NOT NULL COMMENT 'UTC',
  `withdrawn` datetime NULL DEFAULT NULL COMMENT 'UTC; set when the person removes the photo',
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__mifoto_reports` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `owner_id` int unsigned NOT NULL COMMENT 'the person in the reported photo',
  `reporter_id` int unsigned NOT NULL,
  `reason` varchar(16) NOT NULL,
  `comment` varchar(500) NOT NULL DEFAULT '',
  `created` datetime NOT NULL,
  `status` tinyint NOT NULL DEFAULT 0 COMMENT '0 open, 1 photo hidden, 2 photo removed, 3 dismissed',
  `handled_by` int unsigned NOT NULL DEFAULT 0,
  `handled` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`, `created`),
  KEY `idx_owner` (`owner_id`),
  KEY `idx_reporter` (`reporter_id`, `created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
