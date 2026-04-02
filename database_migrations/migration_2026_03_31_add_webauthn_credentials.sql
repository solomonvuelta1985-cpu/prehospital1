-- Migration: Add WebAuthn credentials table for biometric authentication
-- Date: 2026-03-31

USE pre_hospital_db;

CREATE TABLE IF NOT EXISTS webauthn_credentials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    credential_id VARBINARY(512) NOT NULL,
    public_key TEXT NOT NULL,
    credential_name VARCHAR(100) DEFAULT NULL,
    sign_count INT UNSIGNED DEFAULT 0,
    transports JSON DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_used_at DATETIME DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_credential (credential_id(255)),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
