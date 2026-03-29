DELIMITER $$

DROP PROCEDURE IF EXISTS `upgrade_20260328_backlog_features`$$

CREATE PROCEDURE `upgrade_20260328_backlog_features`()
BEGIN
  DECLARE CONTINUE HANDLER FOR 1060 BEGIN END;

  ALTER TABLE `users` ADD COLUMN `mfa_secret` VARCHAR(120) NULL AFTER `status`;
  ALTER TABLE `users` ADD COLUMN `mfa_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `mfa_secret`;
  ALTER TABLE `users` ADD COLUMN `mfa_backup_codes_json` LONGTEXT NULL AFTER `mfa_enabled`;

  ALTER TABLE `videos` ADD COLUMN `poster_storage_provider` ENUM('local', 'wasabi', 'external') NULL AFTER `poster_path`;

  CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `token_hash` CHAR(64) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `used_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `password_reset_tokens_hash_unique` (`token_hash`),
    KEY `password_reset_tokens_user_idx` (`user_id`, `expires_at`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
END$$

CALL `upgrade_20260328_backlog_features`()$$

DROP PROCEDURE IF EXISTS `upgrade_20260328_backlog_features`$$

DELIMITER ;
