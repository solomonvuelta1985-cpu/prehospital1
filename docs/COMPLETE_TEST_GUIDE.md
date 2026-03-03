# Complete Test Guide - User Restriction Feature

## ✅ Setup Checklist

### Step 1: Database Setup
1. Open phpMyAdmin
2. Select database: `pre_hospital_db`
3. Click "SQL" tab
4. Run this SQL:

```sql
-- Add is_restricted column
ALTER TABLE `users`
ADD COLUMN `is_restricted` TINYINT(1) NOT NULL DEFAULT 0
COMMENT 'User restricted from login (1 = restricted, 0 = not restricted)'
AFTER `status`;

-- Add index
ALTER TABLE `users`
ADD INDEX `idx_is_restricted` (`is_restricted`);

-- Reset all users for testing
UPDATE users SET is_restricted = 0, failed_attempts = 0, locked_until = NULL;
```

5. Verify column was added:
```sql
DESCRIBE users;
```

### Step 2: Verify Files Are Updated
Check that these files exist and are modified:
- ✅ `public/login.php` (form disables when restricted)
- ✅ `includes/auth.php` (checks restriction before password)
- ✅ `includes/functions.php` (auto-restricts after 5 attempts)
- ✅ `public/admin/users.php` (shows restricted users)
- ✅ `api/admin/toggle_user_restriction.php` (unrestrict endpoint)

## 🧪 Test 1: Auto-Restriction After 5 Failed Attempts

### What You'll See:

**Attempt 1-4**: Normal error message
```
❌ Invalid username or password
```

**Attempt 5**: Account gets restricted
```
❌ Your account has been restricted. Please contact the administrator.
```

**After Attempt 5**: Form becomes disabled
- Username field: DISABLED (grayed out, shows the username)
- Password field: DISABLED (grayed out)
- reCAPTCHA: HIDDEN
- Login button: DISABLED (shows "Account Restricted")
- Alert box appears with restriction message

### Steps to Test:

1. **Create a Test User** (if needed):
   ```sql
   INSERT INTO users (username, password, email, full_name, role, status)
   VALUES ('testuser', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'test@test.com', 'Test User', 'user', 'active');
   ```
   Password: `password`

2. **Logout** (if logged in)

3. **Go to Login Page**: `http://localhost/prehospital/public/login.php`

4. **Enter Credentials**:
   - Username: `testuser`
   - Password: `wrongpassword` (WRONG!)
   - Complete reCAPTCHA
   - Click "Sign In"

5. **Repeat 4 More Times** (Total 5 attempts)

6. **After 5th Attempt, You Should See**:
   ```
   ┌─────────────────────────────────────────┐
   │ 🚫 Account Restricted - Contact Admin  │
   ├─────────────────────────────────────────┤
   │                                         │
   │ Username: [testuser] (DISABLED/GRAYED)  │
   │ Password: [........] (DISABLED/GRAYED)  │
   │                                         │
   │ ⚠️ Account Restricted                   │
   │ Your account has been restricted due to │
   │ multiple failed login attempts.         │
   │ Please contact the system administrator.│
   │                                         │
   │ [🔒 Account Restricted] (DISABLED BTN)  │
   └─────────────────────────────────────────┘
   ```

7. **Try Refreshing Page**: Form stays disabled, shows same username

8. **Try Entering Correct Password**: Still disabled, can't login

## 🧪 Test 2: Check Database

After restriction, verify in database:

```sql
SELECT username, status, is_restricted, failed_attempts, locked_until
FROM users
WHERE username = 'testuser';
```

**Expected Result**:
```
username   | status | is_restricted | failed_attempts | locked_until
-----------+--------+---------------+-----------------+-------------
testuser   | active | 1             | 5               | 2026-01-10...
```

## 🧪 Test 3: Admin Can See Restricted User

1. **Login as Admin**
   - Username: `admin`
   - Password: `admin123` (or your admin password)

2. **Go to User Management**

3. **Check Stats Card**:
   ```
   ┌──────────────────┐
   │  🚫              │
   │  1               │
   │  Restricted Users│
   └──────────────────┘
   ```

4. **Find Test User in Table**:
   ```
   User       | Status | Restricted      | Actions
   -----------+--------+-----------------+---------
   Test User  | ACTIVE | 🚫 RESTRICTED  | 👁️ ✏️ 🔑 🔓
   ```

5. **Verify Unlock Button**: Should see green 🔓 button (not gray 🔒)

## 🧪 Test 4: Admin Unrestricts User

1. **Click 🔓 Unlock Button** on the restricted user

2. **Confirm Dialog**:
   ```
   Are you sure you want to unrestrict this user?
   They will be able to login again.
   ```

3. **Click OK**

4. **See Success Message**:
   ```
   ✅ User 'Test User' has been unrestricted and can login again
   ```

5. **Verify in Table**:
   - "Restricted" column now shows: ✅ Allowed
   - Button changed from 🔓 to 🔒

6. **Check Stats Card**: Count decreased to 0

## 🧪 Test 5: User Can Login After Unrestriction

1. **Logout from Admin**

2. **Go to Login Page**

3. **Enter Test User Credentials**:
   - Username: `testuser`
   - Password: `password` (CORRECT)
   - Complete reCAPTCHA
   - Click "Sign In"

4. **Expected Result**: ✅ Login Successful! Redirects to dashboard

5. **Verify Database**:
```sql
SELECT username, is_restricted, failed_attempts, locked_until
FROM users
WHERE username = 'testuser';
```

**Expected**:
```
username   | is_restricted | failed_attempts | locked_until
-----------+---------------+-----------------+-------------
testuser   | 0             | 0               | NULL
```

## 🧪 Test 6: Manual Restriction by Admin

1. **Login as Admin**

2. **Go to User Management**

3. **Find a User** (not yourself!)

4. **Click 🔒 Lock Button** (gray)

5. **Confirm**:
   ```
   Are you sure you want to restrict this user?
   They will not be able to login.
   ```

6. **Click OK**

7. **User is Restricted**: Badge shows "RESTRICTED", button is now 🔓

8. **Logout and Try to Login as That User**:
   - Should see disabled form immediately
   - Message: "Your account has been restricted..."

## 🧪 Test 7: Activity Logs

1. **Login as Admin**

2. **Go to Activity Logs** (if available)

3. **Look for These Entries**:
   ```
   account_restricted | Account RESTRICTED for user: testuser after 5 failed login attempts
   restricted_login_attempt | Restricted user 'testuser' attempted to login
   user_restriction_changed | User testuser was unrestricted
   ```

## 📸 Visual Checklist

### Normal Login Form:
- [ ] Username field: Enabled, white background
- [ ] Password field: Enabled, white background
- [ ] reCAPTCHA: Visible
- [ ] Login button: Blue, "Sign In" text

### Restricted Login Form:
- [ ] Header shows: "🚫 Account Restricted - Contact Administrator"
- [ ] Username field: Disabled, gray background, shows username
- [ ] Password field: Disabled, gray background
- [ ] reCAPTCHA: Hidden/Not visible
- [ ] Red alert box with warning message
- [ ] Login button: Disabled, gray, "🔒 Account Restricted"

### Admin Dashboard:
- [ ] Stats card shows count of restricted users
- [ ] Restricted users have black "RESTRICTED" badge
- [ ] Lock/Unlock buttons work correctly
- [ ] Success messages appear

## ⚠️ Common Issues & Solutions

### Issue 1: Column doesn't exist
**Error**: `Unknown column 'is_restricted'`

**Solution**: Run the ALTER TABLE SQL again

### Issue 2: Form doesn't disable
**Possible Causes**:
- Old cached version of login.php
- Browser cache

**Solution**:
- Clear browser cache (Ctrl+Shift+Delete)
- Hard refresh (Ctrl+F5)
- Check if login.php was actually updated

### Issue 3: Can't unrestrict users
**Possible Causes**:
- API file missing
- Wrong file permissions

**Solution**:
- Verify `api/admin/toggle_user_restriction.php` exists
- Check file permissions

### Issue 4: No restriction after 5 attempts
**Possible Causes**:
- `functions.php` not updated
- Database column missing

**Solution**:
- Verify `record_failed_attempt()` sets `is_restricted = 1`
- Check database column exists

## 🔄 Reset Everything for Re-testing

```sql
-- Reset all users
UPDATE users
SET is_restricted = 0, failed_attempts = 0, locked_until = NULL;

-- Verify
SELECT username, is_restricted, failed_attempts FROM users;
```

## ✅ Success Criteria

All these should work:
- ✅ 5 wrong passwords → User gets restricted
- ✅ Restricted user sees disabled login form
- ✅ Admin can see restricted users count
- ✅ Admin can unrestrict users
- ✅ Unrestricted user can login
- ✅ Failed attempts reset after unrestriction
- ✅ Manual restriction works
- ✅ Activity logs record all actions

## 📝 Production Deployment

Before deploying to production:

1. **Backup production database**
2. **Run SQL on production**:
   ```sql
   ALTER TABLE `users`
   ADD COLUMN `is_restricted` TINYINT(1) NOT NULL DEFAULT 0
   AFTER `status`;
   ```
3. **Upload files**:
   - `public/login.php`
   - `includes/auth.php`
   - `includes/functions.php`
   - `public/admin/users.php`
   - `api/admin/toggle_user_restriction.php`
4. **Test with a test account first**
5. **Monitor activity logs for issues**

## 🆘 Emergency: Unrestrict All Users

If something goes wrong:

```sql
-- EMERGENCY: Unrestrict everyone
UPDATE users SET is_restricted = 0, failed_attempts = 0, locked_until = NULL;
```

Then investigate the issue before re-enabling the feature.
