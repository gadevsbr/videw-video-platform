DELIMITER $$

DROP PROCEDURE IF EXISTS `upgrade_20260328_embed_wasabi`$$

CREATE PROCEDURE `upgrade_20260328_embed_wasabi`()
BEGIN
  DECLARE CONTINUE HANDLER FOR 1060 BEGIN END;

  ALTER TABLE `videos` ADD COLUMN `poster_path` VARCHAR(255) NULL AFTER `poster_url`;
  ALTER TABLE `videos` ADD COLUMN `file_path` VARCHAR(255) NULL AFTER `video_url`;
  ALTER TABLE `videos` ADD COLUMN `embed_url` VARCHAR(255) NULL AFTER `trailer_url`;
  ALTER TABLE `videos` ADD COLUMN `mime_type` VARCHAR(120) NULL AFTER `embed_url`;
  ALTER TABLE `videos` ADD COLUMN `original_source_url` VARCHAR(255) NULL AFTER `mime_type`;
  ALTER TABLE `videos` ADD COLUMN `source_type` ENUM('upload', 'external_file', 'embed') NOT NULL DEFAULT 'upload' AFTER `original_source_url`;
  ALTER TABLE `videos` ADD COLUMN `storage_provider` ENUM('local', 'wasabi', 'external') NOT NULL DEFAULT 'local' AFTER `source_type`;

  CREATE TABLE IF NOT EXISTS `site_settings` (
    `setting_key` VARCHAR(120) NOT NULL,
    `setting_value` TEXT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`setting_key`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

END$$

CALL `upgrade_20260328_embed_wasabi`()$$

DROP PROCEDURE IF EXISTS `upgrade_20260328_embed_wasabi`$$

DELIMITER ;
