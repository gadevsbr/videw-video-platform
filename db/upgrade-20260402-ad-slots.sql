SET @sql = IF(
    EXISTS (
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name = 'ads'
    ),
    'SELECT 1',
    'CREATE TABLE `ads` (
        `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
        `slot_key` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        `ad_type` enum(''placeholder'',''image'',''script'',''text'') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''placeholder'',
        `is_active` tinyint(1) NOT NULL DEFAULT ''0'',
        `title` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        `body_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
        `click_url` varchar(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        `image_url` varchar(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        `image_path` varchar(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        `image_storage_provider` enum(''local'',''wasabi'') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        `script_code` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `ads_slot_unique` (`slot_key`),
        KEY `ads_active_idx` (`is_active`,`slot_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
