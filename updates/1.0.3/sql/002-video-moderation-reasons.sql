SET @has_moderation_reason := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'videos'
    AND COLUMN_NAME = 'moderation_reason'
);
SET @sql := IF(
  @has_moderation_reason = 0,
  'ALTER TABLE `videos` ADD COLUMN `moderation_reason` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `moderation_status`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_moderation_reason_idx := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'videos'
    AND INDEX_NAME = 'videos_moderation_reason_idx'
);
SET @sql := IF(
  @has_moderation_reason_idx = 0,
  'ALTER TABLE `videos` ADD KEY `videos_moderation_reason_idx` (`moderation_status`,`moderation_reason`,`deleted_at`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO `schema_migrations` (`version`, `filename`, `notes`)
VALUES ('1.0.3', '002-video-moderation-reasons.sql', 'Add structured moderation reasons for admin filtering and review history.');
