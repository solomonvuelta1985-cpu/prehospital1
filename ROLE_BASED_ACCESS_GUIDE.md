# Role-Based Access Control (RBAC) Guide

## Overview
This system implements role-based access control to manage which pages users can access based on their assigned role (admin or user).

## Features Added

### 1. User Management Updates
- ✅ **Edit User Button**: Added to users.php for updating user details
- ✅ **Edit User Modal**: Full form to update name, username, email, role, and status
- ✅ **Update API Endpoint**: `/api/admin/update_user.php` handles user updates
- ✅ **Change Password**: Already existing, accessible via the key icon

### 2. Role-Based Page Access Control
New functions added to `includes/auth.php`:

#### `get_role_permissions()`
Defines which pages each role can access. Modify this function to add or remove page permissions.

```php
function get_role_permissions() {
    return [
        'admin' => [
            // Admin pages
            'admin/dashboard.php',
            'admin/users.php',
            'admin/settings.php',
            'admin/reports.php',
            'admin/logs.php',

            // User pages (admin has access to all user pages too)
            'index.php',
            'dashboard.php',
            'profile.php',
            'form_a.php',
            'form_b.php',
            'my_records.php',
            'view_record.php',
            'edit_record.php'
        ],
        'user' => [
            // User pages only
            'index.php',
            'dashboard.php',
            'profile.php',
            'form_a.php',
            'form_b.php',
            'my_records.php',
            'view_record.php',
            'edit_record.php'
        ]
    ];
}
```

#### `has_page_permission($page_path)`
Checks if the current logged-in user has permission to access a specific page.

**Example:**
```php
if (has_page_permission('admin/users.php')) {
    // User can access this page
}
```

#### `require_page_permission($page_path)`
Enforces permission check - redirects user if they don't have access.

**Example:**
```php
// At the top of a protected page
require_page_permission('admin/users.php');
```

#### `check_current_page_permission()`
Auto-detects the current page and checks permission. Simplest way to protect a page.

**Example:**
```php
// At the top of any protected page
check_current_page_permission();
```

## How to Use

### Method 1: Auto-Protection (Recommended)
Add this line at the top of any page you want to protect (after including auth.php):

```php
define('APP_ACCESS', true);
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Auto-check permission for current page
check_current_page_permission();
```

The system will automatically detect the page name and check if the user has permission.

### Method 2: Manual Page Specification
If you need more control, specify the page path explicitly:

```php
define('APP_ACCESS', true);
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Manually specify the page to check
require_page_permission('admin/users.php');
```

### Method 3: Conditional Access
Use `has_page_permission()` for conditional logic:

```php
if (has_page_permission('admin/reports.php')) {
    // Show admin-only content
    echo '<a href="/admin/reports.php">View Reports</a>';
}
```

## Adding New Pages to Permissions

### Step 1: Edit `includes/auth.php`
Find the `get_role_permissions()` function and add your new page to the appropriate role array:

```php
function get_role_permissions() {
    return [
        'admin' => [
            // ... existing pages ...
            'admin/new_admin_page.php',  // Add new admin page here
        ],
        'user' => [
            // ... existing pages ...
            'new_user_page.php',  // Add new user page here
        ]
    ];
}
```

### Step 2: Protect the New Page
Add the permission check at the top of your new page:

```php
<?php
define('APP_ACCESS', true);
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Protect this page
check_current_page_permission();

// Your page content here...
?>
```

## User Management Features

### Editing a User
1. Go to **Admin Panel** → **Users**
2. Click the **blue pencil icon** next to the user you want to edit
3. Update the fields:
   - Full Name
   - Username
   - Email
   - Role (admin/user)
   - Status (active/inactive)
4. Click **Update User**

### Changing a User's Password
1. Go to **Admin Panel** → **Users**
2. Click the **yellow key icon** next to the user
3. Enter new password (must meet requirements)
4. Confirm password
5. Click **Change Password**

### Password Requirements
- At least 8 characters
- Must contain:
  - Uppercase letter (A-Z)
  - Lowercase letter (a-z)
  - Number (0-9)
  - Special character (!@#$%^&*)

## Role Descriptions

### Admin Role
- Full access to all pages
- Can access admin panel (`/admin/*`)
- Can manage users
- Can view all records
- Can access all user features

### User Role
- Limited access to user pages only
- Cannot access admin panel
- Can only manage their own records
- Can submit forms
- Can view their own data

## Security Features

✅ **CSRF Protection**: All forms use CSRF tokens
✅ **Input Validation**: All inputs are validated and sanitized
✅ **Email Uniqueness**: Prevents duplicate emails
✅ **Username Uniqueness**: Prevents duplicate usernames
✅ **Session Security**: Session regeneration on login
✅ **Access Control**: Role-based permissions enforced

## Troubleshooting

### "Access denied" error
- Check if the page is added to `get_role_permissions()` for your role
- Verify your user account has the correct role assigned
- Clear browser cache and try again

### User can access unauthorized pages
- Ensure `check_current_page_permission()` is called at the top of the page
- Verify the page path matches exactly in `get_role_permissions()`
- Check that session is active (user is logged in)

### Changes not taking effect
- Clear PHP opcache if enabled
- Clear browser cache
- Check that auth.php is included before the permission check
- Verify the page path format (use forward slashes, relative to public/)

## Example Implementation

Here's a complete example of a protected admin page:

```php
<?php
/**
 * Admin Reports Page
 * Only accessible by admin users
 */

define('APP_ACCESS', true);
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

// Require admin authentication
require_login();
require_admin();

// Additional permission check (optional but recommended)
check_current_page_permission();

// Page content here
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Reports</title>
</head>
<body>
    <h1>Admin Reports</h1>
    <!-- Your content here -->
</body>
</html>
```

## Summary

1. ✅ Added **Edit User** functionality with full modal form
2. ✅ Created **update_user.php** API endpoint
3. ✅ Implemented **Role-Based Access Control** system
4. ✅ Added permission functions to **auth.php**
5. ✅ Created simple methods to protect pages
6. ✅ Password change functionality already existed

**To protect any page**: Just add `check_current_page_permission();` after including auth.php!

**To add a new page**: Add it to `get_role_permissions()` in auth.php!
