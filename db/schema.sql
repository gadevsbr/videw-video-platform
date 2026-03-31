-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Tempo de geração: 31/03/2026 às 00:46
-- Versão do servidor: 8.4.7
-- Versão do PHP: 8.3.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `videw`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `actor_user_id` bigint UNSIGNED DEFAULT NULL,
  `action` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_type` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_id` bigint UNSIGNED DEFAULT NULL,
  `summary` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `metadata_json` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `audit_logs_actor_idx` (`actor_user_id`,`created_at`),
  KEY `audit_logs_target_idx` (`target_type`,`target_id`,`created_at`),
  KEY `audit_logs_action_idx` (`action`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `creator_applications`
--

DROP TABLE IF EXISTS `creator_applications`;
CREATE TABLE IF NOT EXISTS `creator_applications` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED NOT NULL,
  `requested_display_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `requested_slug` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `requested_bio` text COLLATE utf8mb4_unicode_ci,
  `status` enum('pending','approved','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `review_notes` text COLLATE utf8mb4_unicode_ci,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `creator_applications_user_idx` (`user_id`,`status`,`created_at`),
  KEY `creator_applications_status_idx` (`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED NOT NULL,
  `token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `password_reset_tokens_hash_unique` (`token_hash`),
  KEY `password_reset_tokens_user_idx` (`user_id`,`expires_at`),
  KEY `password_reset_tokens_expiry_idx` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `site_settings`
--

DROP TABLE IF EXISTS `site_settings`;
CREATE TABLE IF NOT EXISTS `site_settings` (
  `setting_key` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` mediumtext COLLATE utf8mb4_unicode_ci,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `display_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `creator_display_name` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creator_slug` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creator_bio` text COLLATE utf8mb4_unicode_ci,
  `creator_avatar_url` varchar(2048) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creator_avatar_path` varchar(1024) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creator_avatar_storage_provider` enum('local','wasabi','external') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creator_banner_url` varchar(2048) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creator_banner_path` varchar(1024) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creator_banner_storage_provider` enum('local','wasabi','external') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('member','creator','admin') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'member',
  `status` enum('active','suspended') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `birth_date` date NOT NULL,
  `adult_confirmed_at` datetime DEFAULT NULL,
  `account_tier` enum('free','premium') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'free',
  `stripe_customer_id` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stripe_subscription_id` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stripe_subscription_price_id` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stripe_subscription_status` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stripe_current_period_end` datetime DEFAULT NULL,
  `mfa_secret` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mfa_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `mfa_backup_codes_json` text COLLATE utf8mb4_unicode_ci,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_stripe_customer_unique` (`stripe_customer_id`),
  UNIQUE KEY `users_stripe_subscription_unique` (`stripe_subscription_id`),
  UNIQUE KEY `users_creator_slug_unique` (`creator_slug`),
  KEY `users_role_status_idx` (`role`,`status`),
  KEY `users_tier_status_idx` (`account_tier`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `videos`
--

DROP TABLE IF EXISTS `videos`;
CREATE TABLE IF NOT EXISTS `videos` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `synopsis` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `creator_user_id` bigint UNSIGNED DEFAULT NULL,
  `creator_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `access_level` enum('free','subscriber','premium') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'free',
  `duration_minutes` smallint UNSIGNED NOT NULL DEFAULT '0',
  `poster_tone` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `poster_url` varchar(2048) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `poster_path` varchar(1024) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `poster_storage_provider` enum('local','wasabi','external') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `poster_focus_x` tinyint UNSIGNED NOT NULL DEFAULT '50',
  `poster_focus_y` tinyint UNSIGNED NOT NULL DEFAULT '50',
  `video_url` varchar(2048) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_path` varchar(1024) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trailer_url` varchar(2048) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `embed_url` varchar(2048) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mime_type` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `original_source_url` varchar(2048) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_type` enum('upload','external_file','embed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'upload',
  `storage_provider` enum('local','wasabi','external') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'local',
  `is_featured` tinyint(1) NOT NULL DEFAULT '0',
  `moderation_status` enum('draft','approved','flagged') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `moderation_notes` text COLLATE utf8mb4_unicode_ci,
  `published_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `videos_slug_unique` (`slug`),
  KEY `videos_public_idx` (`moderation_status`,`deleted_at`,`published_at`),
  KEY `videos_access_idx` (`access_level`,`deleted_at`),
  KEY `videos_featured_idx` (`is_featured`,`deleted_at`),
  KEY `videos_source_idx` (`source_type`,`storage_provider`),
  KEY `videos_creator_idx` (`creator_user_id`,`moderation_status`,`deleted_at`,`published_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `video_views`
--

DROP TABLE IF EXISTS `video_views`;
CREATE TABLE IF NOT EXISTS `video_views` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `video_id` bigint UNSIGNED NOT NULL,
  `creator_user_id` bigint UNSIGNED NOT NULL,
  `viewer_user_id` bigint UNSIGNED DEFAULT NULL,
  `session_key` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `viewed_on` date NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `video_views_unique_daily` (`video_id`,`session_key`,`viewed_on`),
  KEY `video_views_creator_idx` (`creator_user_id`,`viewed_on`,`created_at`),
  KEY `video_views_video_idx` (`video_id`,`viewed_on`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
