ALTER TABLE `videos`
  ADD COLUMN `poster_focus_x` tinyint UNSIGNED NOT NULL DEFAULT '50' AFTER `poster_storage_provider`,
  ADD COLUMN `poster_focus_y` tinyint UNSIGNED NOT NULL DEFAULT '50' AFTER `poster_focus_x`;
