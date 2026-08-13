-- Account lockout/restriction fields used by authentication and administration.
ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `is_restricted` TINYINT(1) NOT NULL DEFAULT 0
        COMMENT 'Prevents login while retaining the account';

ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `failed_attempts` INT UNSIGNED NOT NULL DEFAULT 0;

ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `locked_until` DATETIME NULL DEFAULT NULL;

CREATE INDEX IF NOT EXISTS `idx_users_restricted` ON `users` (`is_restricted`);
