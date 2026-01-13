-- ============================================
-- DATABASE MIGRATION: Add body_part column to injuries table
-- Date: 2026-01-13
-- Description: Adds specific body part field to store anatomical location names
-- ============================================

USE pre_hospital_db;

-- Add body_part column to injuries table
ALTER TABLE injuries
ADD COLUMN body_part VARCHAR(100) NULL AFTER body_view,
ADD INDEX idx_body_part (body_part);

-- Update existing records to have a default body part based on view
UPDATE injuries
SET body_part = CASE
    WHEN body_view = 'front' THEN 'Front (Unspecified)'
    WHEN body_view = 'back' THEN 'Back (Unspecified)'
    ELSE 'Unspecified'
END
WHERE body_part IS NULL;

-- Migration completed successfully
SELECT 'Migration completed: body_part column added to injuries table' AS status;
