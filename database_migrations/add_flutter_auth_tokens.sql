-- Single-use, short-lived token storage for native Flutter authentication.
CREATE TABLE IF NOT EXISTS `flutter_auth_tokens` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `token_hash` CHAR(64) NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `used_at` DATETIME NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_flutter_token_hash` (`token_hash`),
    INDEX `idx_flutter_token_expiry` (`expires_at`, `used_at`),
    CONSTRAINT `fk_flutter_token_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
