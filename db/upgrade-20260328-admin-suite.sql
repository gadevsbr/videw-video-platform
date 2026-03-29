DELIMITER $$

DROP PROCEDURE IF EXISTS `upgrade_20260328_admin_suite`$$

CREATE PROCEDURE `upgrade_20260328_admin_suite`()
BEGIN
  DECLARE CONTINUE HANDLER FOR 1060 BEGIN END;

  ALTER TABLE `users` ADD COLUMN `status` ENUM('active', 'suspended') NOT NULL DEFAULT 'active' AFTER `role`;
  ALTER TABLE `users` ADD COLUMN `last_login_at` DATETIME NULL AFTER `adult_confirmed_at`;

  ALTER TABLE `videos` ADD COLUMN `moderation_notes` TEXT NULL AFTER `moderation_status`;
  ALTER TABLE `videos` ADD COLUMN `deleted_at` DATETIME NULL AFTER `published_at`;

  CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `actor_user_id` BIGINT UNSIGNED NULL,
    `action` VARCHAR(120) NOT NULL,
    `target_type` VARCHAR(80) NOT NULL,
    `target_id` BIGINT UNSIGNED NULL,
    `summary` VARCHAR(255) NOT NULL,
    `metadata_json` LONGTEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `audit_logs_actor_idx` (`actor_user_id`, `created_at`),
    KEY `audit_logs_target_idx` (`target_type`, `target_id`, `created_at`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
END$$

CALL `upgrade_20260328_admin_suite`()$$

DROP PROCEDURE IF EXISTS `upgrade_20260328_admin_suite`$$

DELIMITER ;
