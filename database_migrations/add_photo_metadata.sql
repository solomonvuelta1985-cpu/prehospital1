-- Migration: Add photo GPS metadata columns to prehospital_forms table
-- Created: 2026-03-03
-- Purpose: Store GPS coordinates, address, and datetime for patient documentation photos

-- Add photo metadata columns after patient_documentation
ALTER TABLE `prehospital_forms`
ADD COLUMN `photo_latitude` DECIMAL(10,8) NULL DEFAULT NULL
COMMENT 'GPS latitude of photo capture location'
AFTER `patient_documentation`,
ADD COLUMN `photo_longitude` DECIMAL(11,8) NULL DEFAULT NULL
COMMENT 'GPS longitude of photo capture location'
AFTER `photo_latitude`,
ADD COLUMN `photo_address` TEXT NULL DEFAULT NULL
COMMENT 'Reverse-geocoded address of photo capture location'
AFTER `photo_longitude`,
ADD COLUMN `photo_datetime` DATETIME NULL DEFAULT NULL
COMMENT 'Date and time when photo was captured'
AFTER `photo_address`;

-- Verify the columns were added
SELECT 'photo_latitude, photo_longitude, photo_address, photo_datetime columns added successfully' AS status;
