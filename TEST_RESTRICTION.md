# Testing User Restriction Feature

## ⚠️ IMPORTANT: Run This SQL First!

Before testing, you MUST run this SQL in your database:

```sql
-- Check if column exists first
SHOW COLUMNS FROM users LIKE 'is_restricted';

-- If it doesn't exist, add it:
ALTER TABLE `users`
ADD COLUMN `is_restricted` TINYINT(1) NOT NULL DEFAULT 0
COMMENT 'User restricted from login (1 = restricted, 0 = not restricted)'
AFTER `status`;

-- Add index
ALTER TABLE `users`
ADD INDEX `idx_is_restricted` (`is_restricted`);
```

## Testing Steps

### Test 1: Manual Restriction

1. **Login as admin**
2. Go to **User Management**
3. Find a test user (NOT your admin account!)
4. Click the 🔒 **Lock** button (gray button)
5. Confirm the action
6. **Logout**
7. Try to login as the test user with **CORRECT password**
8. **Expected Result**: Should see message:
   ```
   "Your account has been restricted. Please contact the administrator."
   ```

### Test 2: Auto-Restriction (5 Failed Attempts)

1. **Logout** (if logged in)
2. Go to login page
3. Enter username: `test_user` (or any existing username)
4. Enter **WRONG** password 5 times
5. **Expected Result**: After 5th attempt, should see message:
   ```
   "Your account has been restricted. Please contact the administrator."
   ```
6. Try again with **CORRECT** password
7. **Expected Result**: Still restricted, same message

### Test 3: Admin Unrestriction

1. **Login as admin**
2. Go to **User Management**
3. Find the restricted user (black "RESTRICTED" badge)
4. Click the 🔓 **Unlock** button (green button)
5. Confirm the action
6. **Expected Result**: Success message appears
7. **Logout**
8. Login as the test user with correct password
9. **Expected Result**: Login successful!

### Test 4: Dashboard Counter

1. **Login as admin**
2. Go to **User Management**
3. Look at the third stats card
4. **Expected Result**: Should show count of restricted users
   ```
   ┌──────────────────┐
   │  🚫              │
   │  1               │
   │  Restricted Users│
   └──────────────────┘
   ```

## Troubleshooting

### Problem: Nothing happens when trying wrong password

**Possible Causes**:
1. Database column `is_restricted` doesn't exist
2. JavaScript error in browser console
3. Session/cache issue

**Solutions**:
1. Run the SQL to add the column
2. Press F12, check Console tab for errors
3. Clear browser cache, try again

### Problem: "Your account has been restricted" shows immediately

**Cause**: User is already restricted in database

**Solution**:
```sql
-- Check restriction status
SELECT username, is_restricted, failed_attempts FROM users WHERE username = 'test_user';

-- Manually unrestrict if needed
UPDATE users SET is_restricted = 0, failed_attempts = 0, locked_until = NULL WHERE username = 'test_user';
```

### Problem: Column doesn't exist error

**Error Message**: `Unknown column 'is_restricted' in 'field list'`

**Solution**: Run this SQL:
```sql
ALTER TABLE `users`
ADD COLUMN `is_restricted` TINYINT(1) NOT NULL DEFAULT 0
AFTER `status`;
```

### Problem: Can't see Unlock button

**Cause**: User is not actually restricted

**Solution**: Check database:
```sql
SELECT username, status, is_restricted, failed_attempts FROM users;
```

## Verify Database Setup

Run this to check everything is correct:

```sql
-- Check table structure
DESCRIBE users;

-- Check for restricted users
SELECT username, status, is_restricted, failed_attempts, locked_until
FROM users
WHERE is_restricted = 1;

-- Check all users
SELECT id, username, status, is_restricted, failed_attempts
FROM users
ORDER BY id;
```

## Expected Database Structure

The `users` table should have these columns:
- `id`
- `username`
- `password`
- `email`
- `full_name`
- `role`
- `status`
- **`is_restricted`** ← Must exist!
- `last_login`
- `created_at`
- `updated_at`
- `failed_attempts`
- `locked_until`

## Quick Fix Script

If nothing is working, run this complete fix:

```sql
-- 1. Make sure column exists
ALTER TABLE `users`
ADD COLUMN IF NOT EXISTS `is_restricted` TINYINT(1) NOT NULL DEFAULT 0
AFTER `status`;

-- 2. Reset all restrictions for testing
UPDATE users SET is_restricted = 0, failed_attempts = 0, locked_until = NULL;

-- 3. Verify
SELECT username, is_restricted, failed_attempts FROM users;
```

## Testing Checklist

- [ ] SQL column added to database
- [ ] Admin can see "Restricted Users" stat card
- [ ] Manual restriction works (Lock button)
- [ ] Manual unrestriction works (Unlock button)
- [ ] Auto-restriction works (5 wrong passwords)
- [ ] Restriction message shows when trying to login
- [ ] Unrestricted user can login successfully
- [ ] Activity logs show restriction events
- [ ] Files uploaded to production

## What Should Happen

### Timeline of Events:

```
1. User tries wrong password (1/5)
   → failed_attempts = 1

2. User tries wrong password (2/5)
   → failed_attempts = 2

3. User tries wrong password (3/5)
   → failed_attempts = 3

4. User tries wrong password (4/5)
   → failed_attempts = 4

5. User tries wrong password (5/5)
   → failed_attempts = 5
   → is_restricted = 1
   → Message: "Your account has been restricted..."

6. User tries correct password
   → Still sees: "Your account has been restricted..."

7. Admin unrestricts user
   → is_restricted = 0
   → failed_attempts = 0
   → locked_until = NULL

8. User tries correct password
   → Login successful!
```

## Browser Console Logs

Open browser console (F12) when testing. You should see:

**On Restriction**:
```
Response data: {success: false, message: "Your account has been restricted..."}
```

**On Unrestriction** (admin side):
```
Response data: {success: true, message: "User 'John Doe' has been unrestricted..."}
```

## Need Help?

If it's still not working:

1. **Check Error Logs**:
   - Browser Console (F12)
   - PHP Error Log
   - Activity Logs in admin panel

2. **Share with developer**:
   - Screenshot of error message
   - Browser console output
   - SQL query result from: `SELECT * FROM users WHERE username = 'test_user'`

3. **Emergency Reset**:
```sql
-- Unrestrict ALL users
UPDATE users SET is_restricted = 0, failed_attempts = 0, locked_until = NULL;
```
