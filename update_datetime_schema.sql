-- Update date/time columns to allow NULL values
-- This ensures the custom dropdown components work correctly when fields are empty

-- Date fields that should allow NULL
ALTER TABLE prehospital_forms MODIFY COLUMN form_date DATE NULL;
ALTER TABLE prehospital_forms MODIFY COLUMN date_of_birth DATE NULL;
ALTER TABLE prehospital_forms MODIFY COLUMN ob_lmp DATE NULL;
ALTER TABLE prehospital_forms MODIFY COLUMN ob_edc DATE NULL;

-- Time fields already allow NULL, but verify they're correct
-- These are already nullable in the schema, but included for completeness
ALTER TABLE prehospital_forms MODIFY COLUMN departure_time TIME NULL;
ALTER TABLE prehospital_forms MODIFY COLUMN arrival_time TIME NULL;
ALTER TABLE prehospital_forms MODIFY COLUMN arrival_scene_time TIME NULL;
ALTER TABLE prehospital_forms MODIFY COLUMN departure_scene_time TIME NULL;
ALTER TABLE prehospital_forms MODIFY COLUMN arrival_hospital_time TIME NULL;
ALTER TABLE prehospital_forms MODIFY COLUMN departure_hospital_time TIME NULL;
ALTER TABLE prehospital_forms MODIFY COLUMN arrival_station_time TIME NULL;
ALTER TABLE prehospital_forms MODIFY COLUMN incident_time TIME NULL;
ALTER TABLE prehospital_forms MODIFY COLUMN call_arrival_time TIME NULL;
ALTER TABLE prehospital_forms MODIFY COLUMN initial_time TIME NULL;
ALTER TABLE prehospital_forms MODIFY COLUMN followup_time TIME NULL;
ALTER TABLE prehospital_forms MODIFY COLUMN ob_delivery_time TIME NULL;

-- Datetime fields
ALTER TABLE prehospital_forms MODIFY COLUMN endorsement_datetime DATETIME NULL;
