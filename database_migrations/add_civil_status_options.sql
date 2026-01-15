-- Migration: Add additional civil status options
-- Date: 2026-01-15
-- Description: Adds 'widowed', 'divorced', and 'separated' options to civil_status ENUM field

-- Modify the civil_status column to include new options
ALTER TABLE prehospital_forms
MODIFY COLUMN civil_status ENUM('single', 'married', 'widowed', 'divorced', 'separated') NULL;

-- Verification query (optional - run this to verify the change)
-- SHOW COLUMNS FROM prehospital_forms LIKE 'civil_status';
