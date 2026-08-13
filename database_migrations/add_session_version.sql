-- Revoke active sessions when an account is disabled, restricted, or its role changes.
-- Run once on the application database before enabling production traffic.
ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `session_version` INT UNSIGNED NOT NULL DEFAULT 1
        COMMENT 'Increment to invalidate all active sessions for this user';
