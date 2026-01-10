# User Restriction Feature - AUTO RESTRICTION ON FAILED LOGINS

## Overview
**Enhanced Security**: Users are **automatically restricted** after multiple failed login attempts. Only admins can unrestrict them to regain access.

## Key Features
1. ⚠️ **Automatic Restriction**: Users are automatically restricted after **5 failed login attempts**
2. 🔐 **Admin-Only Unrestriction**: Only admins can unrestrict users
3. ♻️ **Auto-Reset**: When admin unrestricts, failed attempts counter is automatically reset to 0
4. 📊 **Dashboard Visibility**: Admins see count of restricted users in the stats dashboard
5. 📝 **Activity Logging**: All restrictions are logged with the reason

## How It Works

### For Regular Users:

**Scenario: User Forgets Password**
1. User tries to login with wrong password (Attempt 1/5)
2. User tries again with wrong password (Attempt 2/5)
3. User tries again with wrong password (Attempt 3/5)
4. User tries again with wrong password (Attempt 4/5)
5. User tries again with wrong password (Attempt 5/5)
   - ❌ **Account is now RESTRICTED**
   - 🚫 User sees: "Your account has been restricted. Please contact the administrator."
   - 🔒 User cannot login even with correct password
6. User must contact admin to unrestrict the account

### For Administrators:

**Dashboard View**:
- Stats card shows: "X Restricted Users"
- Restricted users have a black "RESTRICTED" badge in the user table

**To Unrestrict a User**:
1. Login as admin
2. Go to **User Management**
3. Find the restricted user (shows black "RESTRICTED" badge)
4. Click the 🔓 **Unlock** button
5. Confirm the action
6. ✅ User is unrestricted and can login again
7. ✅ Failed attempts counter is reset to 0
8. ✅ Temporary lock is removed

## Changes Made

### 1. Database (Same as before)
**File**: `database_updates/add_user_restriction.sql`

```sql
ALTER TABLE `users`
ADD COLUMN `is_restricted` TINYINT(1) NOT NULL DEFAULT 0
COMMENT 'User restricted from login (1 = restricted, 0 = not restricted)'
AFTER `status`;

ALTER TABLE `users`
ADD INDEX `idx_is_restricted` (`is_restricted`);
```

### 2. Failed Login Handler
**File**: `includes/functions.php` - `record_failed_attempt()`

**NEW BEHAVIOR**:
- After 5 failed attempts: User is **RESTRICTED** (not just temporarily locked)
- Sets `is_restricted = 1` in database
- Logs activity: "Account RESTRICTED for user: [username] after 5 failed login attempts. Admin action required."

**Before** (Old):
```php
// Just temporarily locked for 15 minutes
SET failed_attempts = ?, locked_until = ?
```

**After** (New):
```php
// PERMANENTLY restricted until admin unrestricts
SET failed_attempts = ?, locked_until = ?, is_restricted = 1
```

### 3. Unrestrict Function Enhanced
**File**: `api/admin/toggle_user_restriction.php`

**NEW BEHAVIOR** when unrestricting:
- Removes restriction (`is_restricted = 0`)
- Resets failed attempts counter (`failed_attempts = 0`)
- Clears temporary lock (`locked_until = NULL`)

```php
// Unrestricting user - also reset failed attempts and unlock
UPDATE users
SET is_restricted = 0, failed_attempts = 0, locked_until = NULL
WHERE id = ?
```

### 4. Admin Dashboard Updated
**File**: `public/admin/users.php`

**Changed**:
- Stats card now shows "Restricted Users" instead of "Inactive Users"
- Icon changed from ❌ to 🚫 (ban icon)
- Counts only users with `is_restricted = 1`

## User Experience Examples

### Example 1: Legitimate User Forgot Password
```
User: "I forgot my password, let me try a few times..."
[After 5 wrong attempts]
System: "Your account has been restricted. Please contact the administrator."
User: [Calls admin]
Admin: [Logs in, goes to User Management, clicks Unlock button]
System: "User 'John Doe' has been unrestricted and can login again"
User: [Can now login successfully with correct password]
```

### Example 2: Potential Brute Force Attack
```
Attacker: [Tries multiple passwords rapidly]
[After 5 attempts within seconds]
System: Account RESTRICTED
Activity Log: "Account RESTRICTED for user: target_user after 5 failed login attempts. Admin action required."
Admin: [Sees notification, investigates]
Admin: [Sees suspicious activity, keeps account restricted]
Admin: [Contacts real user to verify and reset password]
Admin: [Unrestricts account only after verification]
```

### Example 3: Typo Mistakes
```
User: [Types wrong password 3 times]
User: [Realizes caps lock was on]
User: [Types correct password]
System: "Login successful"
Failed attempts: Reset to 0
No restriction occurred (under 5 attempts)
```

## Security Benefits

1. **Brute Force Protection**: Attackers can't keep guessing passwords
2. **Alert System**: Admins are notified via activity logs
3. **Manual Review**: Admin can investigate before unrestricting
4. **Audit Trail**: All restrictions and unrestrictions are logged
5. **No Auto-Unlock**: Unlike temporary locks, restrictions require admin intervention

## Admin Dashboard Statistics

**Before**:
- Total Users
- Active Users
- Inactive Users

**After**:
- Total Users
- Active Users
- **Restricted Users** ← NEW

## Messages Shown to Users

| Scenario | Message |
|----------|---------|
| Account Inactive | "Account is inactive. Contact administrator." |
| Account Restricted (Manual) | "Your account has been restricted. Please contact the administrator." |
| Account Restricted (Auto) | "Your account has been restricted. Please contact the administrator." |
| Wrong Password (< 5 attempts) | "Invalid username or password" |

**Note**: Same message for manual and automatic restriction to avoid revealing security details.

## Installation (Updated)

### 1. Run SQL on BOTH databases (localhost + production)
```sql
ALTER TABLE `users`
ADD COLUMN `is_restricted` TINYINT(1) NOT NULL DEFAULT 0
AFTER `status`;
```

### 2. Upload Updated Files
**Modified Files**:
- `public/admin/users.php` (dashboard stats changed)
- `includes/functions.php` (auto-restriction on failed attempts)
- `includes/auth.php` (restriction check during login)
- `api/admin/toggle_user_restriction.php` (resets failed attempts when unrestricting)

**New Files**:
- `api/admin/toggle_user_restriction.php`
- `database_updates/add_user_restriction.sql`

## Testing Checklist

- [ ] Run SQL on localhost database
- [ ] Run SQL on production database
- [ ] Upload all modified files to production
- [ ] Test: Try 5 wrong passwords → Should get restricted
- [ ] Test: Check admin dashboard → Should see "1 Restricted User"
- [ ] Test: Click Unlock button → Should unrestrict
- [ ] Test: Login with correct password → Should work
- [ ] Check activity_logs table → Should see restriction/unrestriction entries

## Configuration

Current settings (can be changed in code):
- **Max Failed Attempts**: 5 (in `login_user()` function)
- **Temporary Lock Duration**: 15 minutes (kept for backwards compatibility)
- **Restriction**: Permanent until admin unrestricts

To change max attempts, edit this line in `includes/auth.php`:
```php
record_failed_attempt($username, 5, 15);  // Change 5 to your desired limit
```

## FAQ

**Q: What's the difference between "Inactive" and "Restricted"?**
A:
- **Inactive**: Admin manually deactivated the account (permanent)
- **Restricted**: Automatic security block due to failed logins OR manual admin restriction

**Q: Can a restricted user login with the correct password?**
A: No. Even with the correct password, restricted users cannot login until admin unrestricts them.

**Q: What happens to the temporary lock when unrestricting?**
A: It's automatically removed. The user can login immediately after unrestriction.

**Q: Can admins restrict themselves?**
A: No. The system prevents admins from restricting their own accounts.

**Q: Are failed attempts reset on successful login?**
A: Yes. If a user logs in successfully before reaching 5 attempts, the counter resets to 0.

## Activity Log Entries

The system creates these log entries:
- `account_restricted`: When auto-restricted after failed attempts
- `user_restriction_changed`: When admin manually restricts/unrestricts
- `user_login`: Successful login (resets failed attempts)

## Support

If users are getting restricted frequently:
1. Check if they're using the correct username
2. Verify they're not typing password with caps lock on
3. Consider password reset instead of unrestriction
4. Check for potential brute force attacks in activity logs
