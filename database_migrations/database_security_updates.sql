-- ============================================
-- SECURITY ENHANCEMENTS - DATABASE SCHEMA UPDATES
-- Version: 1.1.0
-- Date: 2025-11-06
-- ============================================

USE pre_hospital_db;

-- ============================================
-- 1. ADD ACCOUNT LOCKOUT COLUMNS TO USERS TABLE
-- ============================================
ALTER TABLE users
ADD COLUMN IF NOT EXISTS failed_attempts INT UNSIGNED DEFAULT 0 COMMENT 'Number of failed login attempts',
ADD COLUMN IF NOT EXISTS locked_until DATETIME NULL COMMENT 'Account locked until this time';

-- Add indexes for performance
CREATE INDEX IF NOT EXISTS idx_failed_attempts ON users(failed_attempts);
CREATE INDEX IF NOT EXISTS idx_locked_until ON users(locked_until);

-- ============================================
-- 2. CREATE RATE_LIMITS TABLE FOR IP-BASED RATE LIMITING
-- ============================================
CREATE TABLE IF NOT EXISTS rate_limits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL COMMENT 'IPv4 or IPv6 address',
    action VARCHAR(100) NOT NULL COMMENT 'Action being rate limited (e.g., login, api_call)',
    attempt_count INT UNSIGNED DEFAULT 1 COMMENT 'Number of attempts in current window',
    window_start INT UNSIGNED NOT NULL COMMENT 'Unix timestamp of window start',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY unique_ip_action (ip_address, action),
    INDEX idx_ip_address (ip_address),
    INDEX idx_action (action),
    INDEX idx_window_start (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='IP-based rate limiting';

-- ============================================
-- 3. UPDATE EXISTING DATA
-- ============================================

-- Set failed_attempts to 0 for all existing users if not already set
UPDATE users SET failed_attempts = 0 WHERE failed_attempts IS NULL;

-- ============================================
-- 4. ADD ENDORSEMENT ATTACHMENT COLUMN (IF NOT EXISTS)
-- ============================================
-- This was added in a previous update, but included here for completeness
ALTER TABLE prehospital_forms
ADD COLUMN IF NOT EXISTS endorsement_attachment VARCHAR(255) NULL COMMENT 'File path for endorsement attachment';

-- ============================================
-- 5. CLEAN UP OLD RATE LIMIT RECORDS (OPTIONAL)
-- ============================================
-- You can run this periodically or set up a cron job
-- DELETE FROM rate_limits WHERE window_start < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 24 HOUR));

-- ============================================
-- VERIFICATION QUERIES
-- ============================================

-- Verify users table structure
SELECT
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'pre_hospital_db'
  AND TABLE_NAME = 'users'
  AND COLUMN_NAME IN ('failed_attempts', 'locked_until')
ORDER BY ORDINAL_POSITION;

-- Verify rate_limits table exists
SELECT
    TABLE_NAME,
    ENGINE,
    TABLE_COLLATION,
    TABLE_COMMENT
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = 'pre_hospital_db'
  AND TABLE_NAME = 'rate_limits';

-- Check indexes
SHOW INDEX FROM users WHERE Key_name IN ('idx_failed_attempts', 'idx_locked_until');
SHOW INDEX FROM rate_limits;

-- ============================================
-- ROLLBACK SCRIPT (USE ONLY IF NEEDED)
-- ============================================
/*
-- To rollback these changes:

-- Remove columns from users table
ALTER TABLE users
DROP COLUMN IF EXISTS failed_attempts,
DROP COLUMN IF EXISTS locked_until;

-- Drop rate_limits table
DROP TABLE IF EXISTS rate_limits;

-- Drop indexes (if they weren't automatically dropped)
DROP INDEX IF EXISTS idx_failed_attempts ON users;
DROP INDEX IF EXISTS idx_locked_until ON users;
*/

-- ============================================
-- MAINTENANCE SCRIPT
-- ============================================
-- Run this periodically (daily) to clean up old rate limit records

/*
DELIMITER $$

CREATE EVENT IF NOT EXISTS cleanup_rate_limits
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_DATE + INTERVAL 1 DAY
DO BEGIN
    -- Delete records older than 24 hours
    DELETE FROM rate_limits
    WHERE window_start < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 24 HOUR));

    -- Log cleanup
    INSERT INTO activity_logs (user_id, action, details, ip_address, created_at)
    VALUES (NULL, 'rate_limit_cleanup', 'Automated cleanup of old rate limit records', '0.0.0.0', NOW());
END$$

DELIMITER ;

-- To check if event is created:
-- SHOW EVENTS WHERE Name = 'cleanup_rate_limits';

-- To enable event scheduler (if not already enabled):
-- SET GLOBAL event_scheduler = ON;
*/

-- ============================================
-- USAGE NOTES
-- ============================================

/*
ACCOUNT LOCKOUT FEATURES:
- After 5 failed login attempts, account is locked for 15 minutes
- failed_attempts counter is reset on successful login
- locked_until timestamp is cleared on successful login
- Admins can manually unlock accounts by setting failed_attempts=0 and locked_until=NULL

IP RATE LIMITING:
- Tracks attempts per IP address per action
- Default: 10 attempts per 10 minutes for login
- Records automatically expire after 24 hours
- Can be adjusted per action in the code

MONITORING:
- Check locked accounts:
  SELECT username, failed_attempts, locked_until
  FROM users
  WHERE locked_until IS NOT NULL AND locked_until > NOW();

- Check rate limit violations:
  SELECT ip_address, action, attempt_count, FROM_UNIXTIME(window_start) as started
  FROM rate_limits
  WHERE attempt_count >= 10
  ORDER BY window_start DESC;

- Check account lockout logs:
  SELECT * FROM activity_logs
  WHERE action = 'account_locked'
  ORDER BY created_at DESC
  LIMIT 20;
*/
