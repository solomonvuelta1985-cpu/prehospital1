# Database Migrations

This folder contains SQL migration scripts to update the database schema.

## How to Run Migrations

### Method 1: Using phpMyAdmin (Recommended for XAMPP)

1. Open phpMyAdmin in your browser: `http://localhost/phpmyadmin`
2. Select the `pre_hospital_db` database from the left sidebar
3. Click on the "SQL" tab at the top
4. Copy the contents of the migration file (e.g., `add_patient_documentation.sql`)
5. Paste it into the SQL text area
6. Click "Go" to execute the migration

### Method 2: Using MySQL Command Line

```bash
mysql -u root -p pre_hospital_db < add_patient_documentation.sql
```

## Available Migrations

### add_patient_documentation.sql
**Date:** 2026-01-09
**Purpose:** Adds support for patient documentation image uploads

**What it does:**
- Adds `patient_documentation` VARCHAR(500) column to `prehospital_forms` table
- Column stores the relative file path to uploaded patient images
- Images are organized in `public/uploads/patient_docs/YYYY-MM-DD/` folders

**File structure:**
- Files are saved as: `patient_YYYYMMDDHHMMSS_[unique_id].[extension]`
- Example: `patient_20260109143025_a3f2e8c9d1b4f6e7.jpg`
- Organized by date in subdirectories for easy management

**Features:**
- Max file size: 5MB
- Allowed formats: JPG, PNG, GIF, WebP
- Full security validation (MIME type, file extension, image type)
- Camera capture support with preview
- File upload with preview

## Rollback Instructions

To revert the `add_patient_documentation` migration:

```sql
ALTER TABLE `prehospital_forms` DROP COLUMN `patient_documentation`;
```

**Warning:** This will permanently delete all patient documentation file paths from the database. The actual files in `public/uploads/patient_docs/` will not be deleted automatically.

## Notes

- Always backup your database before running migrations
- Run migrations in order (by date)
- Test migrations on a development database first
- Keep track of which migrations have been applied
