# Custom DateTime Dropdown Implementation - Complete Guide

## Overview
Replaced Flatpickr date/time pickers with custom dropdown components (Month/Day/Year and Hour/Minute/AM-PM) for better mobile UX.

---

## FILES MODIFIED

### Frontend Files

#### 1. `public/prehospital_form.php`
- ✅ Removed Flatpickr CDN links (CSS and JS)
- ✅ Added `custom-datetime.js` script
- ✅ Commented out Flatpickr initialization code
- **Lines modified**: 101, 1630, 1736-1737, 1754-1957

#### 2. `public/edit_record.php`
- ✅ Removed Flatpickr CDN links (CSS and JS)
- ✅ Added `custom-datetime.js` script with cache busting
- ✅ Commented out Flatpickr initialization code (time, date, datetime-local)
- **Lines modified**: 101, 1735-1737, 1789-1984

#### 3. `public/js/custom-datetime.js` (NEW FILE)
- ✅ Replaces `input[type="date"]` with Month/Day/Year dropdowns
- ✅ Replaces `input[type="time"]` with Hour/Minute/AM-PM dropdowns
- ✅ Replaces `input[type="datetime-local"]` with combined dropdowns
- ✅ Hidden inputs store values in proper format (YYYY-MM-DD, HH:MM, YYYY-MM-DDTHH:MM)
- ✅ Loads 500ms after page load to avoid blocking skeleton removal
- ✅ Triggers change events for autosave functionality

---

### Backend API Files

#### 1. `api/autosave_draft.php`
- ✅ Added `clean_date_value()` function to convert empty/invalid dates to NULL
- ✅ Added `clean_time_value()` function to convert empty/invalid times to NULL
- ✅ Handles datetime-local format (YYYY-MM-DDTHH:MM) conversion
- ✅ Applied to: form_date, date_of_birth, ob_lmp, ob_edc, all time fields, endorsement_datetime
- **Lines modified**: 55-61, 72, 90, 157, 159

#### 2. `api/get_draft.php`
- ✅ Cleans up time fields (prevents 00:00:00 from being sent to frontend)
- ✅ Cleans up date fields (prevents 0000-00-00 from being sent to frontend)
- ✅ Cleans up datetime fields (prevents 0000-00-00 00:00:00 from being sent to frontend)
- **Lines modified**: 40-83

#### 3. `api/get_record.php`
- ✅ Cleans up all date/time fields when loading records
- ✅ Fixed column names: ob_delivery_time, ob_lmp, ob_edc
- ✅ Added form_date to cleanup
- **Lines modified**: 49-74

#### 4. `api/save_prehospital_form.php`
- ✅ Already handles datetime-local format (YYYY-MM-DDTHH:MM → YYYY-MM-DD HH:MM:SS)
- ✅ Strips seconds from time inputs (HH:MM:SS → HH:MM)
- ✅ Sanitizes with uppercase disabled for date/time fields
- **Lines**: 116-140, 185-218, 313-342
- **Status**: No changes needed ✅

#### 5. `api/update_record.php`
- ✅ Already handles datetime-local format conversion
- ✅ Strips seconds from time inputs
- ✅ Sanitizes with uppercase disabled for date/time fields
- **Lines**: 50-70, 106-118, 281-308
- **Status**: No changes needed ✅

---

### Configuration Files

#### 1. `includes/version.php`
- ✅ Added `if (!defined('APP_VERSION'))` check to prevent duplicate constant warning
- **Lines modified**: 9-11

---

## DATABASE SCHEMA REQUIREMENTS

### Critical: Date/Time Columns Must Allow NULL

Run this SQL script to ensure all date/time fields can accept NULL values:

```sql
-- Date fields that should allow NULL
ALTER TABLE prehospital_forms MODIFY COLUMN form_date DATE NULL;
ALTER TABLE prehospital_forms MODIFY COLUMN date_of_birth DATE NULL;
ALTER TABLE prehospital_forms MODIFY COLUMN ob_lmp DATE NULL;
ALTER TABLE prehospital_forms MODIFY COLUMN ob_edc DATE NULL;

-- Time fields (verify they allow NULL)
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
```

**SQL File**: See `update_datetime_schema.sql`

---

## HOW IT WORKS

### Data Flow

1. **User Interaction**:
   - User selects Month/Day/Year from dropdowns
   - User selects Hour/Minute/AM-PM from dropdowns

2. **Frontend Processing**:
   - Dropdowns update hidden `<input>` with proper format
   - Date: `YYYY-MM-DD` (e.g., `2024-01-15`)
   - Time: `HH:MM` in 24-hour format (e.g., `14:30`)
   - DateTime: `YYYY-MM-DDTHH:MM` (e.g., `2024-01-15T14:30`)
   - Change event triggered for autosave

3. **Backend Processing** (autosave_draft.php):
   - Receives form data
   - `clean_time_value()` converts "T" format to MySQL datetime
   - `clean_date_value()` converts empty strings to NULL
   - Saves to database

4. **Backend Retrieval** (get_draft.php / get_record.php):
   - Loads from database
   - Cleans up `00:00:00` → empty string
   - Cleans up `0000-00-00` → empty string
   - Sends clean data to frontend

5. **Frontend Display**:
   - custom-datetime.js reads existing values
   - Parses into components and pre-selects dropdowns
   - User sees their previous selections

---

## TESTING CHECKLIST

### ✅ Test Cases to Verify

1. **New Form Entry**:
   - [ ] Date fields show Month/Day/Year dropdowns
   - [ ] Time fields show Hour/Minute/AM-PM dropdowns
   - [ ] DateTime fields show combined dropdowns
   - [ ] Can submit form with all fields filled
   - [ ] Can submit form with empty optional date/time fields

2. **Draft Autosave**:
   - [ ] Selecting dropdown triggers autosave
   - [ ] Draft ID returned correctly
   - [ ] Timestamp updates in UI
   - [ ] No "00:00:00" or "0000-00-00" saved to database

3. **Draft Resume**:
   - [ ] Loading draft populates dropdowns correctly
   - [ ] Empty fields remain empty (no 00:00:00 or 0000-00-00)
   - [ ] Can continue editing and save

4. **Record Edit**:
   - [ ] Edit page shows dropdowns instead of pickers
   - [ ] Existing values populate correctly
   - [ ] Can update and save changes
   - [ ] No validation errors on save

5. **Database Integrity**:
   - [ ] Run `update_datetime_schema.sql`
   - [ ] Verify all date/time columns allow NULL
   - [ ] Check no constraint violations on save

6. **Browser Compatibility**:
   - [ ] Chrome/Edge (desktop)
   - [ ] Firefox (desktop)
   - [ ] Safari (mobile)
   - [ ] Chrome (mobile)

---

## FILES NOT MODIFIED (But Checked)

- `public/records.php` - Has date filters only (keep native inputs)
- `public/reports.php` - Has date filters only (keep native inputs)
- `api/delete_record.php` - No date/time handling needed
- `api/export_records.php` - No date/time handling needed

---

## TROUBLESHOOTING

### Issue: Skeleton loading stuck
**Solution**: custom-datetime.js uses `window.addEventListener('load')` with 500ms delay

### Issue: Form validation error "Invalid date format"
**Solution**: Check that hidden input is getting the correct YYYY-MM-DD format

### Issue: "Column cannot be null" database error
**Solution**: Run `update_datetime_schema.sql` to allow NULL values

### Issue: Time showing as "00:00:00" or "12:00 AM" on load
**Solution**: Check that get_draft.php or get_record.php has cleanup logic for that field

### Issue: Autosave not triggered
**Solution**: Verify that updateDateValue() / updateTimeValue() dispatches change event

---

## ROLLBACK PROCEDURE

If you need to revert to Flatpickr:

1. Restore Flatpickr CDN links in prehospital_form.php and edit_record.php
2. Remove custom-datetime.js script includes
3. Uncomment Flatpickr initialization code
4. Keep the backend API changes (they work with both approaches)

---

## MAINTENANCE

### When adding new date/time fields:

1. Add field to database with NULL constraint
2. Add field to cleanup arrays in:
   - `api/get_draft.php`
   - `api/get_record.php`
3. Add field to autosave_draft.php with `clean_date_value()` or `clean_time_value()`
4. Add field to save_prehospital_form.php and update_record.php
5. No changes needed to custom-datetime.js (it finds all inputs automatically)

---

## VERSION

Current implementation version: **1.0.1**

Last updated: 2026-01-17
