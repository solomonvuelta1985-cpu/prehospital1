-- ============================================
-- DATABASE MIGRATION: Add incident_category column to prehospital_forms
-- Date: 2026-06-24
-- Description: Structured "Consolidated Run" incident category (Vehicular Accident,
--              Mauling, Fall, Gunshot, etc.) captured on the form going forward.
--              Existing rows stay NULL and are derived via classify_incident_category()
--              from the free-text emergency_*_details fields at report time.
-- ============================================

USE pre_hospital_db;

ALTER TABLE prehospital_forms
ADD COLUMN incident_category VARCHAR(50) NULL AFTER emergency_general_details,
ADD INDEX idx_incident_category (incident_category);

SELECT 'Migration completed: incident_category column added to prehospital_forms' AS status;
