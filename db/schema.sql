CREATE TABLE IF NOT EXISTS `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `display_name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(180) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('member', 'creator', 'admin') NOT NULL DEFAULT 'member',
  `status` ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
  `account_tier` ENUM('free', 'premium') NOT NULL DEFAULT 'free',
  `stripe_customer_id` VARCHAR(64) NULL,
  `stripe_subscription_id` VARCHAR(64) NULL,
  `stripe_subscription_price_id` VARCHAR(64) NULL,
  `stripe_subscription_status` VARCHAR(64) NULL,
  `stripe_current_period_end` DATETIME NULL,
  `mfa_secret` VARCHAR(120) NULL,
  `mfa_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `mfa_backup_codes_json` LONGTEXT NULL,
  `birth_date` DATE NOT NULL,
  `adult_confirmed_at` DATETIME NULL,
  `last_login_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_stripe_customer_unique` (`stripe_customer_id`),
  UNIQUE KEY `users_stripe_subscription_unique` (`stripe_subscription_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `videos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(180) NOT NULL,
  `title` VARCHAR(180) NOT NULL,
  `synopsis` TEXT NOT NULL,
  `creator_name` VARCHAR(120) NOT NULL,
  `category` VARCHAR(80) NOT NULL,
  `access_level` ENUM('free', 'premium') NOT NULL DEFAULT 'free',
  `duration_minutes` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `poster_tone` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `poster_url` VARCHAR(255) NULL,
  `poster_path` VARCHAR(255) NULL,
  `poster_storage_provider` ENUM('local', 'wasabi', 'external') NULL,
  `video_url` VARCHAR(255) NULL,
  `file_path` VARCHAR(255) NULL,
  `trailer_url` VARCHAR(255) NULL,
  `embed_url` VARCHAR(255) NULL,
  `mime_type` VARCHAR(120) NULL,
  `original_source_url` VARCHAR(255) NULL,
  `source_type` ENUM('upload', 'external_file', 'embed') NOT NULL DEFAULT 'upload',
  `storage_provider` ENUM('local', 'wasabi', 'external') NOT NULL DEFAULT 'local',
  `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
  `moderation_status` ENUM('draft', 'approved', 'flagged') NOT NULL DEFAULT 'draft',
  `moderation_notes` TEXT NULL,
  `published_at` DATETIME NULL,
  `deleted_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `videos_slug_unique` (`slug`),
  KEY `videos_status_idx` (`moderation_status`, `published_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `site_settings` (
  `setting_key` VARCHAR(120) NOT NULL,
  `setting_value` TEXT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
