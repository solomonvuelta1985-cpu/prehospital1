# Admin Guide: Managing Restricted Users

## What is Account Restriction?

When a user enters the wrong password **5 times**, their account is **automatically restricted** for security. They cannot login until you unrestrict them.

## How to Tell if a User is Restricted

### 1. Dashboard Stats
On the User Management page, look at the stats cards at the top:
```
┌──────────────────────┐
│  🚫                  │
│  3                   │
│  Restricted Users    │
└──────────────────────┘
```

### 2. User Table
Restricted users have:
- A black **"RESTRICTED"** badge in the "Restricted" column
- A green 🔓 **Unlock** button instead of a gray 🔒 Lock button

```
┌─────────────┬──────────────┬─────────────┬──────────┐
│ User        │ Status       │ Restricted  │ Actions  │
├─────────────┼──────────────┼─────────────┼──────────┤
│ John Doe    │ ACTIVE       │ 🚫 RESTRICTED│ 🔓       │
│ Jane Smith  │ ACTIVE       │ ✓ Allowed   │ 🔒       │
└─────────────┴──────────────┴─────────────┴──────────┘
```

## How to Unrestrict a User

### Step-by-Step:

1. **Login** to admin panel
2. Go to **User Management** (in sidebar)
3. **Find** the restricted user (look for black "RESTRICTED" badge)
4. **Click** the 🔓 green **Unlock** button
5. **Confirm** when asked:
   ```
   "Are you sure you want to unrestrict this user?
   They will be able to login again."
   ```
6. Click **OK**
7. ✅ Done! The user can now login

## What Happens When You Unrestrict

The system automatically:
- ✅ Removes the restriction
- ✅ Resets failed login attempts to 0
- ✅ Removes any temporary locks
- ✅ Logs the action in activity logs

The user can **immediately** login with their password.

## Common Scenarios

### Scenario 1: User Forgot Password
**Problem**: User tried to login 5 times with wrong password

**Solution**:
1. Unrestrict the user (🔓 button)
2. Help them reset their password
3. Tell them they can now login

### Scenario 2: Caps Lock Mistakes
**Problem**: User typed password with caps lock on 5 times

**Solution**:
1. Unrestrict the user
2. Tell them to check caps lock before typing
3. They can login immediately

### Scenario 3: Suspicious Activity
**Problem**: Account shows 5 failed attempts in 30 seconds

**⚠️ WARNING**: This might be a hacking attempt!

**Solution**:
1. **DON'T unrestrict immediately**
2. **Contact** the real user
3. **Verify** it was them trying to login
4. If it wasn't them:
   - Change their password
   - Investigate the IP in activity logs
   - Only then unrestrict
5. If it was them:
   - Unrestrict
   - Help them remember password

## How to Manually Restrict a User

If you need to restrict a user yourself (not auto-restricted):

1. Go to **User Management**
2. Find the user
3. Click the 🔒 gray **Lock** button
4. Confirm the action
5. User is now restricted

**Use Cases**:
- Disciplinary action
- Suspicious account activity
- Security investigation
- Temporary suspension

## Tips for Admins

✅ **DO**:
- Check activity logs before unrestricting suspicious accounts
- Contact users before unrestricting if suspicious
- Document reasons for manual restrictions
- Reset passwords when unrestricting compromised accounts

❌ **DON'T**:
- Unrestrict without verification if suspicious activity
- Ignore multiple auto-restrictions on same account
- Forget to tell users their accounts were unrestricted

## Quick Reference

| Action | Button | Color |
|--------|--------|-------|
| Unrestrict User | 🔓 Unlock | Green |
| Restrict User | 🔒 Lock | Gray |
| View Details | 👁️ Eye | Blue |
| Edit User | ✏️ Pencil | Blue |
| Change Password | 🔑 Key | Yellow |

## Checking Activity Logs

To see restriction activity:
1. Go to **Activity Logs** (in sidebar)
2. Look for entries like:
   - "Account RESTRICTED for user: john_doe after 5 failed login attempts"
   - "User john_doe was unrestricted"

## System Messages to Users

When restricted users try to login, they see:
```
"Your account has been restricted.
Please contact the administrator."
```

They should then contact you (the admin) to unrestrict them.

## Troubleshooting

**Problem**: Can't find the Unlock button
- **Solution**: User might not be restricted. Check the "Restricted" column.

**Problem**: User still can't login after unrestricting
- **Solution**:
  1. Refresh the user management page
  2. Check if "Status" is "Active" (not Inactive)
  3. Try unrestricting again
  4. Check if password is correct

**Problem**: Too many users getting restricted
- **Solution**:
  1. Users might be forgetting passwords
  2. Consider password reset training
  3. Check for brute force attacks in logs

## Security Best Practices

1. **Review** restricted users daily
2. **Investigate** suspicious patterns (same user restricted multiple times)
3. **Document** why you manually restrict/unrestrict
4. **Monitor** activity logs regularly
5. **Don't** share your admin account

## Need Help?

If you're unsure about unrestricting a user:
1. Check the activity logs for suspicious activity
2. Contact the user to verify
3. When in doubt, investigate before unrestricting
4. Document your decision

---

**Remember**: Account restriction is a **security feature**. Always verify before unrestricting suspicious accounts!
