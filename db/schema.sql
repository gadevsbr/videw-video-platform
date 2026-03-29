-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Tempo de geração: 29/03/2026 às 15:31
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
  KEY `audit_logs_target_idx` (`target_type`,`target_id`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `actor_user_id`, `action`, `target_type`, `target_id`, `summary`, `metadata_json`, `created_at`) VALUES
(1, 1, 'app.saved', 'settings', NULL, 'Updated general application settings.', '{\"app_name\":\"VIDEW 18+\",\"brand\":\"VIDEW 18+\"}', '2026-03-28 18:27:55'),
(2, 1, 'app.saved', 'settings', NULL, 'Updated general application settings.', '{\"app_name\":\"VIDEW 18+\",\"brand\":\"VIDEW 1 18+\"}', '2026-03-28 18:29:04'),
(3, 1, 'app.saved', 'settings', NULL, 'Updated general application settings.', '{\"app_name\":\"VIDEW 18+\",\"brand\":\"VIDEW 18+\"}', '2026-03-28 18:30:56');

-- --------------------------------------------------------

--
-- Estrutura para tabela `site_settings`
--

DROP TABLE IF EXISTS `site_settings`;
CREATE TABLE IF NOT EXISTS `site_settings` (
  `setting_key` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_unicode_ci,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `site_settings`
--

INSERT INTO `site_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('upload_driver', 'local', '2026-03-28 14:51:01'),
('wasabi_access_key', '', '2026-03-28 14:51:01'),
('wasabi_bucket', '', '2026-03-28 14:51:01'),
('wasabi_endpoint', 'https://s3.wasabisys.com', '2026-03-28 14:51:01'),
('wasabi_multipart_part_size_mb', '16', '2026-03-28 14:51:01'),
('wasabi_multipart_threshold_mb', '64', '2026-03-28 14:51:01'),
('wasabi_path_prefix', 'videw', '2026-03-28 14:51:01'),
('wasabi_private_bucket', '0', '2026-03-28 14:51:01'),
('wasabi_public_base_url', '', '2026-03-28 14:51:01'),
('wasabi_region', 'us-east-1', '2026-03-28 14:51:01'),
('wasabi_secret_key', '', '2026-03-28 14:51:01'),
('wasabi_signed_url_ttl_seconds', '900', '2026-03-28 14:51:01');

-- --------------------------------------------------------

--
-- Estrutura para tabela `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `display_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('member','creator','admin') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'member',
  `status` enum('active','suspended') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `birth_date` date NOT NULL,
  `adult_confirmed_at` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `users`
--

INSERT INTO `users` (`id`, `display_name`, `email`, `password_hash`, `role`, `status`, `birth_date`, `adult_confirmed_at`, `last_login_at`, `created_at`, `updated_at`) VALUES
(1, 'Videw', 'admin@videw.xom', '$2y$10$NEfd0.w0KGNMt3Raa55OvONQ6sUiMalbP/8OxwMhTYvaLo.1N/5NC', 'admin', 'active', '2000-07-27', '2026-03-28 15:31:06', NULL, '2026-03-28 15:31:06', '2026-03-28 15:31:06');

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
  `creator_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `access_level` enum('free','subscriber','premium') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'free',
  `duration_minutes` smallint UNSIGNED NOT NULL DEFAULT '0',
  `poster_tone` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `poster_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `poster_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `video_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trailer_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `embed_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mime_type` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `original_source_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  KEY `videos_status_idx` (`moderation_status`,`published_at`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `videos`
--

INSERT INTO `videos` (`id`, `slug`, `title`, `synopsis`, `creator_name`, `category`, `access_level`, `duration_minutes`, `poster_tone`, `poster_url`, `poster_path`, `video_url`, `file_path`, `trailer_url`, `embed_url`, `mime_type`, `original_source_url`, `source_type`, `storage_provider`, `is_featured`, `moderation_status`, `moderation_notes`, `published_at`, `deleted_at`, `created_at`, `updated_at`) VALUES
(1, 'editorial-after-hours', 'Editorial After Hours', 'Sessão premium com direção intimista, iluminação editorial e publicação liberada somente para maiores de 18 anos.', 'Nadia Vale', 'Estúdio', 'premium', 42, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'upload', 'local', 1, 'approved', NULL, '2026-03-21 19:20:00', NULL, '2026-03-28 14:51:01', '2026-03-28 14:51:01'),
(2, 'private-loft-series', 'Private Loft Series', 'Coleção solo com produção independente, creator verificada e termos de consentimento arquivados.', 'Lia North', 'Solo', 'subscriber', 28, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'upload', 'local', 1, 'approved', NULL, '2026-03-19 22:10:00', NULL, '2026-03-28 14:51:01', '2026-03-28 14:51:01'),
(3, 'couples-archive-madrid', 'Couples Archive / Madrid', 'Produção para assinantes com casal autorizado, revisão documental concluída e publicação sob política 18+.', 'Atelier 24', 'Casais', 'premium', 55, 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'upload', 'local', 1, 'approved', NULL, '2026-03-16 18:40:00', NULL, '2026-03-28 14:51:01', '2026-03-28 14:51:01'),
(4, 'creator-verified-session-08', 'Creator Verified Session 08', 'Entrada gratuita para preview institucional da plataforma com branding limpo e marcação clara de conteúdo adulto.', 'Mika Shore', 'Creator Verified', 'free', 14, 3, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'upload', 'local', 0, 'approved', NULL, '2026-03-15 10:20:00', NULL, '2026-03-28 14:51:01', '2026-03-28 14:51:01'),
(5, 'studio-ledger-vol-2', 'Studio Ledger Vol. 2', 'Catálogo de estúdio com edição prolongada, acesso premium e sinalização obrigatória de maiores de 18 anos.', 'Studio Ledger', 'Estúdio', 'premium', 63, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'upload', 'local', 0, 'approved', NULL, '2026-03-12 21:45:00', NULL, '2026-03-28 14:51:01', '2026-03-28 14:51:01'),
(6, 'late-checkout-notes', 'Late Checkout Notes', 'Sessão discreta para assinantes, sem estética vulgar e com foco em navegação premium.', 'Nadia Vale', 'Solo', 'subscriber', 31, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'upload', 'local', 0, 'approved', NULL, '2026-03-09 20:30:00', NULL, '2026-03-28 14:51:01', '2026-03-28 14:51:01'),
(7, 'consent-ledger-berlin', 'Consent Ledger / Berlin', 'Publicação voltada ao acervo premium com documentação de identidade e consentimento vinculada ao upload.', 'Atelier 24', 'Casais', 'premium', 47, 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'upload', 'local', 0, 'approved', NULL, '2026-03-04 17:05:00', NULL, '2026-03-28 14:51:01', '2026-03-28 14:51:01'),
(8, 'membership-briefing-cut', 'Membership Briefing Cut', 'Preview livre para onboarding, com copy objetiva, age gate e explicação da política de creators verificados.', 'VIDEW Originals', 'Bastidores', 'free', 11, 3, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'upload', 'local', 0, 'approved', NULL, '2026-03-01 09:15:00', NULL, '2026-03-28 14:51:01', '2026-03-28 14:51:01');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
