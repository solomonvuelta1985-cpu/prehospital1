-- Migration: Add patient_documentation column to prehospital_forms table
-- Created: 2026-01-09
-- Purpose: Add support for patient documentation image uploads

-- Add patient_documentation column after other_belongings
ALTER TABLE `prehospital_forms`
ADD COLUMN `patient_documentation` VARCHAR(500) NULL DEFAULT NULL
COMMENT 'File path to patient documentation image (relative to uploads directory)'
AFTER `other_belongings`;

-- Verify the column was added
SELECT 'patient_documentation column added successfully' AS status;
