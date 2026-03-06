# GPS Photo Metadata & Overlay Feature

**Date Added:** 2026-03-03
**Feature:** Automatic GPS geotagging and overlay stamping on patient documentation photos

---

## Overview

When a patient photo is captured (via camera) or uploaded (via file), the system automatically:

1. Requests the user's GPS location via the browser Geolocation API
2. Reverse-geocodes the coordinates into a human-readable address using OpenStreetMap Nominatim
3. Stamps a GPS info overlay directly onto the photo image (like the "GPS Map Camera" app)
4. Stores the raw GPS metadata (lat, lng, address, datetime) in the database

The overlay displays:
- **Location name** (City, Province, Country)
- **Full street address** (Road, Barangay, City, Province, Postal Code, Country)
- **GPS coordinates** (Lat/Long to 6 decimal places)
- **Date & time with timezone** (e.g., Tuesday, 03/03/2026 06:07 AM GMT+08:00)

---

## Database Schema

### Migration File

`database_migrations/add_photo_metadata.sql`

### New Columns (added to `prehospital_forms` table)

| Column | Type | Description |
|--------|------|-------------|
| `photo_latitude` | `DECIMAL(10,8)` | GPS latitude (-90 to 90) |
| `photo_longitude` | `DECIMAL(11,8)` | GPS longitude (-180 to 180) |
| `photo_address` | `TEXT` | Reverse-geocoded full address string |
| `photo_datetime` | `DATETIME` | Date and time when photo was captured |

All columns are `NULL DEFAULT NULL` and are placed after the `patient_documentation` column.

### Running the Migration

```sql
SOURCE c:/xampp/htdocs/prehospital/database_migrations/add_photo_metadata.sql;
```

Or execute directly in phpMyAdmin:

```sql
ALTER TABLE `prehospital_forms`
ADD COLUMN `photo_latitude` DECIMAL(10,8) NULL DEFAULT NULL AFTER `patient_documentation`,
ADD COLUMN `photo_longitude` DECIMAL(11,8) NULL DEFAULT NULL AFTER `photo_latitude`,
ADD COLUMN `photo_address` TEXT NULL DEFAULT NULL AFTER `photo_longitude`,
ADD COLUMN `photo_datetime` DATETIME NULL DEFAULT NULL AFTER `photo_address`;
```

---

## Files Modified

### Frontend

| File | Changes |
|------|---------|
| `public/js/prehospital-form.js` | Added GPS overlay functions, modified camera capture and file upload handlers |
| `public/css/prehospital-form.css` | Added GPS status indicator styles |
| `public/prehospital_form.php` | Added hidden GPS fields and status indicator |
| `public/edit_record.php` | Added hidden GPS fields pre-populated from DB |

### Backend

| File | Changes |
|------|---------|
| `api/save_prehospital_form.php` | Reads/validates GPS POST data, includes in INSERT/UPDATE queries |
| `api/update_record.php` | Reads/validates GPS POST data, includes in UPDATE query |

### Database

| File | Description |
|------|-------------|
| `database_migrations/add_photo_metadata.sql` | Adds 4 new columns to `prehospital_forms` |

---

## JavaScript Functions

All GPS-related functions are in `public/js/prehospital-form.js` under the section **"GPS PHOTO METADATA & OVERLAY FUNCTIONS"**.

### Core Functions

#### `getGPSLocation()`
- **Returns:** `Promise<{latitude, longitude} | null>`
- Uses `navigator.geolocation.getCurrentPosition()` with high accuracy
- Timeout: 10 seconds
- Returns `null` if GPS is unavailable or permission is denied

#### `reverseGeocode(lat, lng)`
- **Returns:** `Promise<{locationName, fullAddress, country} | null>`
- Calls OpenStreetMap Nominatim API: `https://nominatim.openstreetmap.org/reverse`
- Parses address components: road, suburb, city/town/municipality, state/province, postcode, country
- Free service, no API key required
- Rate limited to 1 request per second (handled naturally by single-use per photo)

#### `captureAndStampGPS(originalBlob)`
- **Returns:** `Promise<{blob, hasGPS}>`
- Orchestrator function that chains: GPS capture -> reverse geocoding -> overlay stamping
- If GPS unavailable, still stamps date/time with "Location unavailable" text
- Populates hidden form fields with metadata

#### `stampGPSOverlay(imageBlob, metadata)`
- **Returns:** `Promise<Blob>` (stamped JPEG image)
- Uses HTML5 Canvas API to draw overlay onto the image
- Overlay design:
  - Semi-transparent black bar at bottom of image (`rgba(0, 0, 0, 0.6)`)
  - Blue left accent bar
  - White text with shadow for readability
  - Font size scales with image width (`width / 50`, minimum 14px)
  - Long addresses are word-wrapped automatically

#### `formatGPSDateTime(date)`
- **Returns:** `string`
- Format: `"Tuesday, 03/03/2026 06:07 AM GMT+08:00"`
- Includes full day name, date, 12-hour time with AM/PM, and timezone offset

### Helper Functions

#### `populateMetadataFields(lat, lng, address, datetime)`
Sets hidden form field values:
- `#photoLatitude` - raw latitude
- `#photoLongitude` - raw longitude
- `#photoAddress` - full address string
- `#photoDatetime` - MySQL DATETIME format (`YYYY-MM-DD HH:MM:SS`)

#### `clearPhotoMetadata()`
Clears all hidden GPS fields and hides the status indicator. Called when a photo is removed.

#### `updateGPSStatus(state)`
Updates the visual GPS status indicator below the photo preview.

| State | Display |
|-------|---------|
| `'fetching'` | Blue spinner + "Getting location..." |
| `'success'` | Green check + "Location captured" |
| `'unavailable'` | Red icon + "Location unavailable" |
| `'hidden'` | Hidden (no display) |

---

## Modified Existing Functions

### `capturePatientPhoto()`
**Before:** Captured canvas -> created file -> set preview
**After:** Captured canvas -> closed camera -> showed GPS status -> called `captureAndStampGPS()` -> created file with stamped image -> set preview -> updated GPS status

### `validatePatientFileUpload(input)`
**Before:** Validated file -> read as DataURL -> showed preview
**After:** Validated file -> showed GPS status -> read as DataURL -> converted to blob -> called `captureAndStampGPS()` -> replaced file input with stamped image -> showed preview -> updated GPS status

### `removePatientAttachment()`
**Before:** Cleared file input and preview
**After:** Same + calls `clearPhotoMetadata()` to clear GPS fields and status

---

## Hidden Form Fields

Added to both `prehospital_form.php` (create) and `edit_record.php` (edit):

```html
<input type="hidden" name="photo_latitude" id="photoLatitude">
<input type="hidden" name="photo_longitude" id="photoLongitude">
<input type="hidden" name="photo_address" id="photoAddress">
<input type="hidden" name="photo_datetime" id="photoDatetime">
```

In the edit form, these are pre-populated with existing database values:
```php
value="<?= htmlspecialchars($record['photo_latitude'] ?? '') ?>"
```

---

## Backend Validation

Both `save_prehospital_form.php` and `update_record.php` perform:

```php
// Read GPS POST data
$photo_latitude = !empty($_POST['photo_latitude']) ? floatval($_POST['photo_latitude']) : null;
$photo_longitude = !empty($_POST['photo_longitude']) ? floatval($_POST['photo_longitude']) : null;
$photo_address = !empty($_POST['photo_address']) ? sanitize($_POST['photo_address']) : null;
$photo_datetime = !empty($_POST['photo_datetime']) ? sanitize($_POST['photo_datetime'], false) : null;

// Validate coordinate ranges
if ($photo_latitude !== null && ($photo_latitude < -90 || $photo_latitude > 90)) {
    $photo_latitude = null;
}
if ($photo_longitude !== null && ($photo_longitude < -180 || $photo_longitude > 180)) {
    $photo_longitude = null;
}
```

---

## CSS Styles

Added to `public/css/prehospital-form.css`:

### `.gps-status-indicator`
Base container with flex layout, padding, border-radius.

### State variants:
- `.gps-status-indicator.fetching` - Blue background/border, spinning icon animation
- `.gps-status-indicator.success` - Green background/border
- `.gps-status-indicator.unavailable` - Red background/border

### `@keyframes spin`
Rotation animation for the fetching spinner icon.

---

## Overlay Visual Design

```
+----------------------------------------------+
|                                              |
|              [Patient Photo]                 |
|                                              |
|----------------------------------------------|
| |  Baggao, Cagayan, Philippines              |
| |  Alcala-Baggao Road, San Jose, Baggao,     |
| |  Cagayan 3506, Philippines                 |
| |  Lat 17.889145, Long 121.870761           |
| |  Tuesday, 03/03/2026 06:07 AM GMT+08:00   |
+----------------------------------------------+
```

- Background: `rgba(0, 0, 0, 0.6)` semi-transparent black
- Text: White with drop shadow
- Left accent bar: `#3b82f6` (blue)
- First line (location name): Bold, 10% larger font
- Font size: Proportional to image width (min 14px)
- Address lines auto-wrap at word boundaries

---

## User Flow

### Creating a New Record

1. User navigates to Patient Documentation section
2. User clicks "Open Camera" or "Upload File"
3. **Camera path:** User captures photo -> camera closes -> "Getting location..." appears
4. **Upload path:** User selects file -> "Getting location..." appears
5. Browser prompts for GPS permission (if not already granted)
6. GPS coordinates obtained -> Nominatim API called for address
7. GPS overlay stamped onto photo via Canvas API
8. Stamped photo replaces original in file input
9. Preview shows stamped image
10. Status shows "Location captured" (green) or "Location unavailable" (red)
11. Hidden fields populated with lat, lng, address, datetime
12. On form submit, all metadata saved to database

### Editing an Existing Record

1. Edit form loads with existing `photo_latitude`, `photo_longitude`, `photo_address`, `photo_datetime` values in hidden fields
2. If user uploads a new photo, GPS metadata is re-captured and overlay is re-stamped
3. Existing metadata is preserved if no new photo is uploaded

### GPS Permission Denied

- Photo is still saved (without GPS overlay for location)
- Date/time overlay is still stamped with "Location unavailable" as location text
- Status shows "Location unavailable" (red indicator)
- Hidden fields remain empty for lat/lng/address
- Form submission proceeds normally with NULL GPS values

---

## External Dependencies

### OpenStreetMap Nominatim API
- **URL:** `https://nominatim.openstreetmap.org/reverse`
- **Cost:** Free
- **API Key:** Not required
- **Rate Limit:** 1 request per second (acceptable for our use case)
- **Coverage:** Worldwide, good coverage in the Philippines
- **Terms:** Must include attribution if displaying map data (we only use for address text)
- **Fallback:** If API fails, photo is stamped without address; only coordinates are shown

### Browser APIs Used
- `navigator.geolocation.getCurrentPosition()` - GPS location
- `HTMLCanvasElement` / `CanvasRenderingContext2D` - Image overlay stamping
- `Blob` / `File` / `DataTransfer` - File manipulation
- `fetch()` - Nominatim API calls and blob conversion

---

## Troubleshooting

### GPS Not Working
- Ensure the site is served over HTTPS (required for Geolocation API)
- Check browser permissions: Settings > Privacy > Location
- On mobile, ensure device GPS/location services are enabled
- localhost is exempt from HTTPS requirement for development

### Overlay Not Appearing on Photo
- Check browser console for Canvas errors
- Ensure the image loads correctly before stamping
- Verify the image blob is valid (not empty/corrupted)

### Address Shows as Empty
- Nominatim API may be temporarily down or rate-limited
- Check browser console for fetch errors
- Coordinates still appear even if address lookup fails

### Database Columns Missing
- Run the migration: `database_migrations/add_photo_metadata.sql`
- Verify columns exist: `DESCRIBE prehospital_forms;`
- Look for `photo_latitude`, `photo_longitude`, `photo_address`, `photo_datetime`

### Form Submission Fails After Adding Feature
- Ensure the migration has been run (column count mismatch will cause SQL errors)
- Check PHP error logs for parameter count mismatches in prepared statements
- Verify hidden fields exist in the HTML form
