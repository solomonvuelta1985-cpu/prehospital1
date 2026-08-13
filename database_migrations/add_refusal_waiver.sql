-- Migration: Add refusal-waiver tracking and signed document storage
-- Purpose: Store whether a patient refusal waiver is required and the
--          authenticated path to the uploaded signed paper waiver.

ALTER TABLE `prehospital_forms`
    ADD COLUMN `waiver_required` TINYINT(1) NOT NULL DEFAULT 0
        COMMENT 'Whether the patient refused treatment and/or transport'
        AFTER `waiver_witness_signature`,
    ADD COLUMN `waiver_attachment` VARCHAR(500) NULL DEFAULT NULL
        COMMENT 'Relative path to the signed refusal waiver document'
        AFTER `waiver_required`;

CREATE INDEX `idx_waiver_required` ON `prehospital_forms` (`waiver_required`);
