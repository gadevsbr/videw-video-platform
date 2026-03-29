DELIMITER $$

DROP PROCEDURE IF EXISTS `upgrade_20260329_stripe_premium`$$

CREATE PROCEDURE `upgrade_20260329_stripe_premium`()
BEGIN
  DECLARE CONTINUE HANDLER FOR 1060 BEGIN END;
  DECLARE CONTINUE HANDLER FOR 1061 BEGIN END;

  ALTER TABLE `users` ADD COLUMN `account_tier` ENUM('free', 'premium') NOT NULL DEFAULT 'free' AFTER `status`;
  ALTER TABLE `users` ADD COLUMN `stripe_customer_id` VARCHAR(64) NULL AFTER `account_tier`;
  ALTER TABLE `users` ADD COLUMN `stripe_subscription_id` VARCHAR(64) NULL AFTER `stripe_customer_id`;
  ALTER TABLE `users` ADD COLUMN `stripe_subscription_price_id` VARCHAR(64) NULL AFTER `stripe_subscription_id`;
  ALTER TABLE `users` ADD COLUMN `stripe_subscription_status` VARCHAR(64) NULL AFTER `stripe_subscription_price_id`;
  ALTER TABLE `users` ADD COLUMN `stripe_current_period_end` DATETIME NULL AFTER `stripe_subscription_status`;
  ALTER TABLE `users` ADD UNIQUE KEY `users_stripe_customer_unique` (`stripe_customer_id`);
  ALTER TABLE `users` ADD UNIQUE KEY `users_stripe_subscription_unique` (`stripe_subscription_id`);

  UPDATE `videos` SET `access_level` = 'premium' WHERE `access_level` = 'subscriber';
  ALTER TABLE `videos` MODIFY `access_level` ENUM('free', 'premium') NOT NULL DEFAULT 'free';
END$$

CALL `upgrade_20260329_stripe_premium`()$$

DROP PROCEDURE IF EXISTS `upgrade_20260329_stripe_premium`$$

DELIMITER ;
