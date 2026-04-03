ALTER TABLE `ads`
  MODIFY COLUMN `ad_type` enum('placeholder','image','script','text','video','vast') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'placeholder',
  ADD COLUMN `video_url` varchar(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `image_storage_provider`,
  ADD COLUMN `video_path` varchar(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `video_url`,
  ADD COLUMN `video_storage_provider` enum('local','wasabi','external') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `video_path`,
  ADD COLUMN `video_mime_type` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `video_storage_provider`,
  ADD COLUMN `vast_tag_url` varchar(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `video_mime_type`,
  ADD COLUMN `skip_after_seconds` int NOT NULL DEFAULT '5' AFTER `vast_tag_url`;
