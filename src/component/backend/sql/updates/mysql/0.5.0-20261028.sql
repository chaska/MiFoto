-- 0.5.0: proof that the person accepted (owner of the photo + data protection) and reports about photos.
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
