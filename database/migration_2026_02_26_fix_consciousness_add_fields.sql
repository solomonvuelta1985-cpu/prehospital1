-- Migration: Fix Consciousness Saving + Add Multi-Select Helmet + Growth Status + Age Unit
-- Date: 2026-02-26
-- Description:
--   1. Ensure consciousness columns are VARCHAR(255) to support JSON arrays
--   2. Convert helmet column from ENUM to VARCHAR(255) for multi-select support
--   3. Add age_unit column (years/months) for infant age handling
--   4. Add growth_status column (infant/child/adult/senior)

USE pre_hospital_db;

-- 1. Fix consciousness column types (ensure VARCHAR for JSON storage)
ALTER TABLE prehospital_forms
MODIFY COLUMN initial_consciousness VARCHAR(255) NULL,
MODIFY COLUMN followup_consciousness VARCHAR(255) NULL;

-- 2. Convert helmet from ENUM to VARCHAR for multi-select
ALTER TABLE prehospital_forms
MODIFY COLUMN initial_helmet VARCHAR(255) NULL;

-- 3. Add age_unit column for months/years
ALTER TABLE prehospital_forms
ADD COLUMN age_unit ENUM('years', 'months') DEFAULT 'years' NOT NULL AFTER age;

-- 4. Add growth_status column
ALTER TABLE prehospital_forms
ADD COLUMN growth_status ENUM('infant', 'child', 'adult', 'senior') NULL AFTER age_unit;

-- Verification queries (run these to verify migration success)
-- DESCRIBE prehospital_forms;
-- SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
-- WHERE TABLE_NAME = 'prehospital_forms'
-- AND COLUMN_NAME IN ('initial_consciousness', 'followup_consciousness', 'initial_helmet', 'age_unit', 'growth_status');
