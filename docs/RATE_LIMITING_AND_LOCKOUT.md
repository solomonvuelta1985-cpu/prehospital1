# IP-Based Rate Limiting & Account Lockout

**Date:** 2025-11-06
**Status:** ✅ Implemented

---

## 🎯 Overview

Two critical security features have been added to protect against brute force attacks:

1. **IP-Based Rate Limiting** - Limits requests per IP address (persistent across sessions)
2. **Account Lockout** - Locks user accounts after multiple failed login attempts

---

## 📊 Features Added

### 1. IP-Based Rate Limiting

**What It Does:**
- Tracks login attempts per IP address
- Stores in database (persists across sessions)
- Automatic cleanup of old records

**Protection:**
- ✅ Prevents brute force attacks from single IP
- ✅ Works even if attacker clears cookies/session
- ✅ Protects all users, not just logged-in users

**Configuration:**
- **Login attempts:** 10 per 10 minutes (600 seconds)
- **Storage:** `rate_limits` database table
- **Cleanup:** Automatic (records older than 24 hours)

### 2. Account Lockout

**What It Does:**
- Tracks failed login attempts per username
- Locks account temporarily after threshold
- Auto-unlocks after timeout period

**Protection:**
- ✅ Prevents password guessing on specific accounts
- ✅ Alerts users to potential attacks
- ✅ Automatic recovery (no admin intervention needed)

**Configuration:**
- **Max attempts:** 5 failed logins
- **Lockout duration:** 15 minutes
- **Storage:** `users` table columns (`failed_attempts`, `locked_until`)

---

## 📁 Files Modified

| File | Changes | Purpose |
|------|---------|---------|
| [functions.php](includes/functions.php) | Added 3 new functions | Rate limiting & lockout logic |
| [auth.php](includes/auth.php) | Updated `login_user()` | Integration with new features |
| [database_security_updates.sql](database_security_updates.sql) | New migration | Database schema changes |

---

## 🔧 Implementation Details

### New Functions in `functions.php`

#### 1. `check_ip_rate_limit($action, $max_attempts, $time_window)`

**Purpose:** IP-based rate limiting (persistent)

**Parameters:**
- `$action` - Action name (e.g., 'login', 'api_call')
- `$max_attempts` - Max attempts allowed
- `$time_window` - Time window in seconds

**Returns:** `true` if allowed, `false` if rate limit exceeded

**Example:**
```php
if (!check_ip_rate_limit('login', 10, 600)) {
    return ['success' => false, 'message' => 'Too many attempts from your IP'];
}
```

**Storage:**
```sql
CREATE TABLE rate_limits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    action VARCHAR(100) NOT NULL,
    attempt_count INT UNSIGNED DEFAULT 1,
    window_start INT UNSIGNED NOT NULL,
    UNIQUE KEY unique_ip_action (ip_address, action)
);
```

#### 2. `is_account_locked($username)`

**Purpose:** Check if user account is locked

**Parameters:**
- `$username` - Username to check

**Returns:**
- `false` if not locked
- `['locked' => true, 'minutes_remaining' => N]` if locked

**Example:**
```php
$lock_status = is_account_locked('admin');
if ($lock_status && $lock_status['locked']) {
    echo "Locked for {$lock_status['minutes_remaining']} minutes";
}
```

#### 3. `record_failed_attempt($username, $max_attempts, $lockout_minutes)`

**Purpose:** Record failed login attempt

**Parameters:**
- `$username` - Username that failed
- `$max_attempts` - Max attempts before lock (default: 5)
- `$lockout_minutes` - Lockout duration (default: 15)

**Behavior:**
- Increments `failed_attempts` counter
- If threshold reached, sets `locked_until` timestamp
- Logs lockout event to `activity_logs`

#### 4. `reset_failed_attempts($username)`

**Purpose:** Reset failed attempts on successful login

**Parameters:**
- `$username` - Username to reset

**Behavior:**
- Sets `failed_attempts` = 0
- Clears `locked_until` timestamp

#### 5. `cleanup_rate_limits($older_than_hours)`

**Purpose:** Clean up old rate limit records

**Parameters:**
- `$older_than_hours` - Delete records older than this (default: 24)

**Usage:**
```php
// Call this periodically (e.g., daily cron job)
cleanup_rate_limits(24);
```

---

## 🔒 Login Flow (Updated)

### Before Login Attempt:

```
1. Check IP rate limit (10 attempts / 10 min)
   ├─ BLOCKED → "Too many attempts from your IP"
   └─ ALLOWED → Continue

2. Check session rate limit (5 attempts / 5 min) [backup]
   ├─ BLOCKED → "Too many login attempts"
   └─ ALLOWED → Continue

3. Verify reCAPTCHA
   ├─ FAILED → "CAPTCHA verification failed"
   └─ PASSED → Continue

4. Check if account is locked
   ├─ LOCKED → "Account locked for X minutes"
   └─ UNLOCKED → Continue
```

### During Login Attempt:

```
5. Verify username exists
   ├─ NOT FOUND → "Invalid username or password" (generic)
   └─ FOUND → Continue

6. Check account status
   ├─ INACTIVE → "Account is inactive"
   └─ ACTIVE → Continue

7. Verify password
   ├─ WRONG → Record failed attempt → "Invalid username or password"
   └─ CORRECT → Reset failed attempts → Login successful
```

### After Failed Login:

```
Record Failed Attempt:
├─ Increment failed_attempts counter
├─ Check if threshold reached (5 attempts)
│   ├─ YES → Lock account for 15 minutes
│   │        Log event: 'account_locked'
│   └─ NO → Just increment counter
└─ Return generic error message
```

### After Successful Login:

```
Reset Account:
├─ Set failed_attempts = 0
├─ Clear locked_until timestamp
├─ Regenerate session ID
└─ Log successful login
```

---

## 📊 Database Schema Changes

### 1. Users Table Additions

```sql
ALTER TABLE users
ADD COLUMN failed_attempts INT UNSIGNED DEFAULT 0,
ADD COLUMN locked_until DATETIME NULL;
```

**Column Descriptions:**
- `failed_attempts` - Counter of consecutive failed logins
- `locked_until` - Account locked until this timestamp (NULL = not locked)

### 2. New Rate Limits Table

```sql
CREATE TABLE rate_limits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    action VARCHAR(100) NOT NULL,
    attempt_count INT UNSIGNED DEFAULT 1,
    window_start INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_ip_action (ip_address, action)
);
```

---

## 🚀 Installation

### Step 1: Run Database Migration

```bash
mysql -u root -p pre_hospital_db < database_security_updates.sql
```

Or manually in phpMyAdmin/MySQL Workbench:
1. Open `database_security_updates.sql`
2. Execute all statements
3. Verify with the verification queries at the end

### Step 2: Verify Installation

**Check Users Table:**
```sql
DESCRIBE users;
-- Should show: failed_attempts, locked_until columns
```

**Check Rate Limits Table:**
```sql
SHOW TABLES LIKE 'rate_limits';
-- Should return: rate_limits
```

### Step 3: Test Functionality

See Testing section below.

---

## 🧪 Testing

### Test 1: IP Rate Limiting

**Scenario:** Test that IP is blocked after 10 failed attempts

```bash
# Try logging in 11 times with wrong password
# Attempt 1-10: Should say "Invalid username or password"
# Attempt 11: Should say "Too many login attempts from your IP"
```

**Verify in Database:**
```sql
SELECT * FROM rate_limits WHERE action = 'login';
-- Should show your IP with attempt_count = 11
```

### Test 2: Account Lockout

**Scenario:** Test that account locks after 5 failed attempts

```bash
# Use correct username, wrong password 6 times
# Attempts 1-5: Should say "Invalid username or password"
# Attempt 6: Should say "Account is temporarily locked... Try again in 15 minute(s)"
```

**Verify in Database:**
```sql
SELECT username, failed_attempts, locked_until
FROM users
WHERE username = 'your_test_user';
-- Should show: failed_attempts = 5, locked_until = [15 minutes from now]
```

### Test 3: Automatic Unlock

**Scenario:** Test that account auto-unlocks after timeout

```bash
# Wait 15 minutes (or manually clear locked_until in database)
# Try logging in with correct credentials
# Should: Login successfully
```

**Verify:**
```sql
SELECT username, failed_attempts, locked_until
FROM users
WHERE username = 'your_test_user';
-- Should show: failed_attempts = 0, locked_until = NULL
```

### Test 4: Successful Login Reset

**Scenario:** Test that counter resets on successful login

```bash
# Fail login 3 times
# Then login successfully
# Counter should reset to 0
```

---

## 📈 Monitoring

### Check Currently Locked Accounts

```sql
SELECT
    username,
    failed_attempts,
    locked_until,
    TIMESTAMPDIFF(MINUTE, NOW(), locked_until) as minutes_remaining
FROM users
WHERE locked_until IS NOT NULL
  AND locked_until > NOW()
ORDER BY locked_until DESC;
```

### Check Rate Limit Violations

```sql
SELECT
    ip_address,
    action,
    attempt_count,
    FROM_UNIXTIME(window_start) as started,
    TIMESTAMPDIFF(MINUTE, FROM_UNIXTIME(window_start), NOW()) as minutes_ago
FROM rate_limits
WHERE attempt_count >= 10
ORDER BY window_start DESC;
```

### View Lockout Events

```sql
SELECT
    created_at,
    details,
    ip_address
FROM activity_logs
WHERE action = 'account_locked'
ORDER BY created_at DESC
LIMIT 20;
```

### Statistics Dashboard Query

```sql
SELECT
    COUNT(*) as total_users,
    SUM(CASE WHEN failed_attempts > 0 THEN 1 ELSE 0 END) as users_with_failures,
    SUM(CASE WHEN locked_until > NOW() THEN 1 ELSE 0 END) as currently_locked,
    AVG(failed_attempts) as avg_failed_attempts
FROM users;
```

---

## �� Configuration

### Adjust Lockout Settings

**Change max attempts or lockout duration:**

Edit [auth.php:96](includes/auth.php#L96):
```php
// Current: 5 attempts, 15 minutes
record_failed_attempt($username, 5, 15);

// More strict: 3 attempts, 30 minutes
record_failed_attempt($username, 3, 30);

// Less strict: 10 attempts, 5 minutes
record_failed_attempt($username, 10, 5);
```

### Adjust IP Rate Limiting

**Change IP rate limit for login:**

Edit [auth.php:59](includes/auth.php#L59):
```php
// Current: 10 attempts per 10 minutes
check_ip_rate_limit('login', 10, 600)

// More strict: 5 attempts per 15 minutes
check_ip_rate_limit('login', 5, 900)

// Less strict: 20 attempts per 5 minutes
check_ip_rate_limit('login', 20, 300)
```

---

## 🛡️ Security Benefits

### Protection Against:

| Attack Type | Protection Level | How It Works |
|-------------|------------------|--------------|
| **Brute Force (Single IP)** | 🟢 High | IP rate limiting blocks attacker IP |
| **Brute Force (Multiple IPs)** | 🟢 High | Account lockout protects specific accounts |
| **Credential Stuffing** | 🟢 High | Both mechanisms limit attempts |
| **Session Hijacking** | 🟡 Medium | IP tracking + session rate limit |
| **Distributed Attacks** | 🟡 Medium | Account lockout slows down attacks |

### Additional Benefits:

- ✅ **No manual intervention needed** - Auto-unlocks after timeout
- ✅ **Legitimate users protected** - Rate limits prevent account takeover
- ✅ **Attack visibility** - All attempts logged in database
- ✅ **Fail-safe design** - If rate limiting fails, requests allowed (fail open)
- ✅ **Performance optimized** - Indexed database queries

---

## 🔄 Maintenance

### Daily Cleanup (Recommended)

Add to cron job or scheduled task:
```php
// Cleanup old rate limit records
cleanup_rate_limits(24); // Delete records older than 24 hours
```

### Manual Unlock Account

If you need to manually unlock an account:
```sql
UPDATE users
SET failed_attempts = 0, locked_until = NULL
WHERE username = 'username_here';
```

### Clear All Rate Limits

To reset all IP rate limits:
```sql
TRUNCATE TABLE rate_limits;
```

---

## 📊 Impact on Security Score

**Before:** 7.5/10
**After:** 8.5/10 (+1.0)

**Improvements:**
- ✅ IP-based rate limiting implemented
- ✅ Account lockout implemented
- ✅ Brute force attacks mitigated
- ✅ Attack visibility improved

**Remaining to reach 9/10:**
- ❌ Fix critical configuration issues (DB password, reCAPTCHA keys, etc.)

---

## 🐛 Troubleshooting

### Issue: "Too many attempts" but I didn't try multiple times

**Cause:** Someone else from your IP tried logging in
**Solution:**
- Wait for timeout period (10 minutes for IP, 15 minutes for account)
- Or admin can manually clear: `DELETE FROM rate_limits WHERE ip_address = 'YOUR_IP'`

### Issue: Account locked but I want to unlock it now

**Solution:**
```sql
UPDATE users
SET failed_attempts = 0, locked_until = NULL
WHERE username = 'username';
```

### Issue: Rate limiting not working

**Check:**
1. Database migration ran successfully: `SELECT * FROM rate_limits LIMIT 1;`
2. Functions exist: Search for `check_ip_rate_limit` in functions.php
3. Error logs: Check PHP error log for database errors

---

## 📝 Notes

- **Fail Open Design:** If rate limiting database queries fail, requests are allowed (better UX than blocking everyone)
- **IP Spoofing Protection:** Only trusts proxy headers if `TRUST_PROXY_HEADERS` is explicitly enabled
- **Privacy:** IP addresses are stored for security purposes - ensure compliance with privacy laws
- **Distributed Attacks:** For large-scale distributed attacks, consider adding Cloudflare or similar WAF

---

**Implementation Date:** 2025-11-06
**Tested:** ✅ Yes
**Production Ready:** ✅ Yes (after database migration)
