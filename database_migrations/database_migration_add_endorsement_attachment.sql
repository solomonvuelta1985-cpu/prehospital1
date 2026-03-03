-- Migration: Add endorsement_attachment column to prehospital_forms table
-- Date: 2024-12-19
-- Description: Adds a column to store file paths for endorsement attachments

USE your_database_name_here;

-- Add endorsement_attachment column to prehospital_forms table
ALTER TABLE prehospital_forms
ADD COLUMN endorsement_attachment VARCHAR(500) NULL
AFTER endorsement_datetime;

-- Add index for better query performance (optional but recommended)
CREATE INDEX idx_endorsement_attachment ON prehospital_forms(endorsement_attachment);

-- Add comment to document the column purpose
ALTER TABLE prehospital_forms
MODIFY COLUMN endorsement_attachment VARCHAR(500) NULL COMMENT 'File path to endorsement attachment image (relative to uploads directory)';

-- Optional: Create a separate table for file metadata if you want to track more details
-- Uncomment the following if you want additional file tracking

/*
CREATE TABLE endorsement_attachments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    form_id INT NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    uploaded_by INT NOT NULL,
    FOREIGN KEY (form_id) REFERENCES prehospital_forms(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_form_id (form_id),
    INDEX idx_uploaded_at (uploaded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
*/

COMMIT;
