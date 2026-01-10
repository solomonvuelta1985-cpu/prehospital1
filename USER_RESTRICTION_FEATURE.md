# User Restriction Feature

## Overview
Added the ability for admins to restrict/unrestrict users from logging in to the system.

## Changes Made

### 1. Database Changes
**File**: `database_updates/add_user_restriction.sql`

Added `is_restricted` column to the `users` table:
- `is_restricted` TINYINT(1) DEFAULT 0
- 0 = User can login (default)
- 1 = User is restricted from logging in

**IMPORTANT**: Run this SQL file in phpMyAdmin on **BOTH** localhost and production databases!

```sql
ALTER TABLE `users`
ADD COLUMN `is_restricted` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'User restricted from login (1 = restricted, 0 = not restricted)'
AFTER `status`;

ALTER TABLE `users`
ADD INDEX `idx_is_restricted` (`is_restricted`);
```

### 2. Admin Users Page
**File**: `public/admin/users.php`

**Changes**:
- Added "Restricted" column to the users table
- Added Restrict/Unrestrict buttons in the Actions column
- Shows restriction status with badges:
  - **Restricted**: Black badge with ban icon
  - **Allowed**: Green checkmark
- Added JavaScript function `toggleRestriction()` to handle restriction changes

**New Buttons**:
- 🔒 **Lock button** (gray): Click to restrict a user
- 🔓 **Unlock button** (green): Click to unrestrict a user

### 3. API Endpoint
**File**: `api/admin/toggle_user_restriction.php` (NEW)

**Features**:
- Restricts/unrestricts users
- Prevents admins from restricting themselves
- Logs all restriction changes
- Returns success/error messages

**Security**:
- Requires admin authentication
- CSRF token validation
- Activity logging

### 4. Login System
**File**: `includes/auth.php`

**Changes**:
- Updated `login_user()` function to check `is_restricted` status
- Restricted users see: "Your account has been restricted. Please contact the administrator."
- Restriction check happens AFTER status check but BEFORE password verification

## How to Use

### For Admins:

1. **Restrict a User**:
   - Go to Admin → User Management
   - Find the user you want to restrict
   - Click the 🔒 (Lock) button
   - Confirm the action
   - User will immediately be unable to login

2. **Unrestrict a User**:
   - Go to Admin → User Management
   - Find the restricted user (shows "Restricted" badge)
   - Click the 🔓 (Unlock) button
   - Confirm the action
   - User can now login again

### For Restricted Users:

When a restricted user tries to login, they will see:
> "Your account has been restricted. Please contact the administrator."

## Installation Steps

1. **Run the SQL on localhost**:
   ```
   - Open phpMyAdmin
   - Select database: pre_hospital_db
   - Go to SQL tab
   - Paste contents of database_updates/add_user_restriction.sql
   - Click Go
   ```

2. **Run the SQL on production**:
   ```
   - Login to cPanel → phpMyAdmin
   - Select database: btrahnqi_pre_hospital_db
   - Go to SQL tab
   - Paste contents of database_updates/add_user_restriction.sql
   - Click Go
   ```

3. **Upload files to production**:
   ```
   - public/admin/users.php (modified)
   - api/admin/toggle_user_restriction.php (new)
   - includes/auth.php (modified)
   ```

## Difference Between Status and Restriction

| Feature | Status (Active/Inactive) | Restriction |
|---------|-------------------------|-------------|
| Purpose | Enable/Disable accounts | Temporarily block login |
| Effect | Inactive = Cannot login | Restricted = Cannot login |
| Use Case | Permanent deactivation | Temporary suspension |
| Message | "Account is inactive" | "Account has been restricted" |

**Best Practice**:
- Use **Inactive** for permanently disabled accounts
- Use **Restricted** for temporary suspensions or disciplinary actions

## Security Notes

- Only admins can restrict/unrestrict users
- Admins cannot restrict themselves
- All restriction changes are logged in activity_logs
- Restriction is checked during login before password verification
- Uses CSRF protection

## Testing

1. **Test Restriction**:
   - Login as admin
   - Restrict a test user account
   - Logout
   - Try to login as the restricted user
   - Should see restriction message

2. **Test Unrestriction**:
   - Login as admin
   - Unrestrict the test user
   - Logout
   - Try to login as the user
   - Should work normally

## Files Modified/Created

**Modified**:
- `public/admin/users.php`
- `includes/auth.php`

**Created**:
- `api/admin/toggle_user_restriction.php`
- `database_updates/add_user_restriction.sql`
- `USER_RESTRICTION_FEATURE.md` (this file)
