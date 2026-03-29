SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `password_reset_tokens`;
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `videos`;
DROP TABLE IF EXISTS `site_settings`;
DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `display_name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('member', 'creator', 'admin') NOT NULL DEFAULT 'member',
  `status` ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
  `birth_date` DATE NOT NULL,
  `adult_confirmed_at` DATETIME DEFAULT NULL,
  `account_tier` ENUM('free', 'premium') NOT NULL DEFAULT 'free',
  `stripe_customer_id` VARCHAR(120) DEFAULT NULL,
  `stripe_subscription_id` VARCHAR(120) DEFAULT NULL,
  `stripe_subscription_price_id` VARCHAR(120) DEFAULT NULL,
  `stripe_subscription_status` VARCHAR(80) DEFAULT NULL,
  `stripe_current_period_end` DATETIME DEFAULT NULL,
  `mfa_secret` VARCHAR(255) DEFAULT NULL,
  `mfa_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `mfa_backup_codes_json` TEXT DEFAULT NULL,
  `last_login_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_stripe_customer_unique` (`stripe_customer_id`),
  UNIQUE KEY `users_stripe_subscription_unique` (`stripe_subscription_id`),
  KEY `users_role_status_idx` (`role`, `status`),
  KEY `users_tier_status_idx` (`account_tier`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `site_settings` (
  `setting_key` VARCHAR(160) NOT NULL,
  `setting_value` MEDIUMTEXT DEFAULT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `videos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(180) NOT NULL,
  `title` VARCHAR(180) NOT NULL,
  `synopsis` TEXT NOT NULL,
  `creator_name` VARCHAR(120) NOT NULL,
  `category` VARCHAR(80) NOT NULL,
  `access_level` ENUM('free', 'subscriber', 'premium') NOT NULL DEFAULT 'free',
  `duration_minutes` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `poster_tone` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `poster_url` VARCHAR(2048) DEFAULT NULL,
  `poster_path` VARCHAR(1024) DEFAULT NULL,
  `poster_storage_provider` ENUM('local', 'wasabi', 'external') DEFAULT NULL,
  `video_url` VARCHAR(2048) DEFAULT NULL,
  `file_path` VARCHAR(1024) DEFAULT NULL,
  `trailer_url` VARCHAR(2048) DEFAULT NULL,
  `embed_url` VARCHAR(2048) DEFAULT NULL,
  `mime_type` VARCHAR(120) DEFAULT NULL,
  `original_source_url` VARCHAR(2048) DEFAULT NULL,
  `source_type` ENUM('upload', 'external_file', 'embed') NOT NULL DEFAULT 'upload',
  `storage_provider` ENUM('local', 'wasabi', 'external') NOT NULL DEFAULT 'local',
  `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
  `moderation_status` ENUM('draft', 'approved', 'flagged') NOT NULL DEFAULT 'draft',
  `moderation_notes` TEXT DEFAULT NULL,
  `published_at` DATETIME DEFAULT NULL,
  `deleted_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `videos_slug_unique` (`slug`),
  KEY `videos_public_idx` (`moderation_status`, `deleted_at`, `published_at`),
  KEY `videos_access_idx` (`access_level`, `deleted_at`),
  KEY `videos_featured_idx` (`is_featured`, `deleted_at`),
  KEY `videos_source_idx` (`source_type`, `storage_provider`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `password_reset_tokens` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `password_reset_tokens_hash_unique` (`token_hash`),
  KEY `password_reset_tokens_user_idx` (`user_id`, `expires_at`),
  KEY `password_reset_tokens_expiry_idx` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `audit_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `actor_user_id` BIGINT UNSIGNED DEFAULT NULL,
  `action` VARCHAR(120) NOT NULL,
  `target_type` VARCHAR(80) NOT NULL,
  `target_id` BIGINT UNSIGNED DEFAULT NULL,
  `summary` VARCHAR(255) NOT NULL,
  `metadata_json` LONGTEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `audit_logs_actor_idx` (`actor_user_id`, `created_at`),
  KEY `audit_logs_target_idx` (`target_type`, `target_id`, `created_at`),
  KEY `audit_logs_action_idx` (`action`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
