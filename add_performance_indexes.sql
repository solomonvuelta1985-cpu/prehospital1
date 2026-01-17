-- ============================================
-- PERFORMANCE OPTIMIZATION INDEXES
-- Add these indexes to significantly improve query performance
-- ============================================

USE pre_hospital_db;

-- Add composite index for dashboard and sidebar queries
-- This index optimizes queries that filter by both created_by and status
ALTER TABLE prehospital_forms
ADD INDEX idx_created_by_status (created_by, status);

-- Add composite index for date-based queries with user filtering
-- This index optimizes queries that filter by created_by and order by created_at
ALTER TABLE prehospital_forms
ADD INDEX idx_created_by_created_at (created_by, created_at DESC);

-- Add composite index for form_date queries with user filtering
-- This index optimizes queries for weekly/monthly reports
ALTER TABLE prehospital_forms
ADD INDEX idx_created_by_form_date (created_by, form_date);

-- Add composite index for status and form_date
-- This index optimizes queries that filter by status and date ranges
ALTER TABLE prehospital_forms
ADD INDEX idx_status_form_date (status, form_date);

-- Create rate_limits table (referenced in functions.php but missing from schema)
CREATE TABLE IF NOT EXISTS rate_limits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(100) NOT NULL,
    action VARCHAR(50) NOT NULL,
    attempt_count INT UNSIGNED DEFAULT 1,
    first_attempt DATETIME NOT NULL,
    last_attempt DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_identifier_action (identifier, action),
    INDEX idx_last_attempt (last_attempt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- VERIFICATION QUERY
-- Run this to verify the indexes were created successfully
-- ============================================
-- SHOW INDEX FROM prehospital_forms;
