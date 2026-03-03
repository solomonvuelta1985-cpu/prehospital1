# User Management System - Documentation

## Overview
Complete admin user management system with create, view, change password, and deactivate functionality.

## Access

### URL
```
http://localhost/123/public/admin/users.php
```

### Requirements
- Must be logged in as **Admin** user
- Non-admin users will be denied access

## Features

### 1. **Dashboard Statistics**
- Total Users count
- Active Users count
- Inactive Users count

### 2. **Create New User**
Click the "Create New User" button to open the modal.

**Required Fields:**
- Full Name
- Username (minimum 3 characters)
- Password (minimum 6 characters)
- Role (User or Admin)
- Status (Active or Inactive)

**Optional Fields:**
- Email

**Validation:**
- Username must be unique
- Email must be unique (if provided)
- Password minimum 6 characters

### 3. **View All Users**
The main table displays:
- User avatar (first letter of name)
- Full name
- Username
- Email
- Role badge (Admin/User)
- Status badge (Active/Inactive)
- Last login date
- Action buttons

### 4. **Search Users**
Use the search box to filter users by:
- Name
- Username
- Email

### 5. **View User Details**
Click the **eye icon** (👁️) to view complete user information:
- Full name
- Username
- Email
- Role
- Status
- Created date
- Last login

### 6. **Change Password**
Click the **key icon** (🔑) to change a user's password:
- Enter new password (minimum 6 characters)
- Confirm password
- Both passwords must match

### 7. **Activate/Deactivate User**
- **Red X icon** (❌) - Deactivate active users
- **Green check icon** (✓) - Activate inactive users

**Important:**
- You cannot deactivate your own account
- Deactivated users cannot login

## File Structure

```
├── public/
│   └── admin/
│       ├── dashboard.php          # Admin dashboard (redirects to users)
│       └── users.php              # Main user management page
├── api/
│   └── admin/
│       ├── create_user.php        # Create new user API
│       ├── change_user_password.php # Change password API
│       ├── toggle_user_status.php  # Activate/deactivate API
│       └── get_user.php           # Get user details API
└── includes/
    └── auth.php                   # Authentication functions
```

## API Endpoints

### 1. Create User
**Endpoint:** `api/admin/create_user.php`
**Method:** POST
**Parameters:**
- csrf_token
- full_name
- username
- email (optional)
- password
- role
- status

### 2. Change Password
**Endpoint:** `api/admin/change_user_password.php`
**Method:** POST
**Parameters:**
- csrf_token
- user_id
- new_password
- confirm_password

### 3. Toggle Status
**Endpoint:** `api/admin/toggle_user_status.php`
**Method:** POST
**Parameters:**
- csrf_token
- user_id
- status (active/inactive)

**Response:** JSON
```json
{
  "success": true,
  "message": "User activated successfully!"
}
```

### 4. Get User Details
**Endpoint:** `api/admin/get_user.php`
**Method:** GET
**Parameters:**
- id (user_id)

**Response:** JSON
```json
{
  "success": true,
  "user": {
    "id": 1,
    "username": "admin",
    "full_name": "Admin User",
    "email": "admin@example.com",
    "role": "admin",
    "status": "active",
    "created_at": "2025-01-01 10:00:00",
    "last_login": "2025-01-15 14:30:00"
  }
}
```

## Security Features

1. **CSRF Protection** - All forms use CSRF tokens
2. **Admin Only Access** - `require_admin()` function
3. **Password Hashing** - Uses PHP `password_hash()`
4. **Input Validation** - Server-side validation
5. **SQL Injection Protection** - Prepared statements
6. **Self-Protection** - Cannot deactivate own account

## Design Features

1. **Modern UI** - Clean, professional design
2. **Gradient Colors** - Purple gradient theme
3. **Responsive** - Works on mobile and desktop
4. **Interactive** - Smooth animations and hover effects
5. **Search** - Real-time client-side search
6. **Status Badges** - Color-coded role and status indicators

## Usage Examples

### Example 1: Create a New User
1. Click "Create New User" button
2. Fill in the form:
   - Full Name: John Doe
   - Username: johndoe
   - Email: john@example.com
   - Password: mypassword123
   - Role: User
   - Status: Active
3. Click "Create User"
4. User appears in the table

### Example 2: Change User Password
1. Find the user in the table
2. Click the key icon (🔑)
3. Enter new password: newpass123
4. Confirm password: newpass123
5. Click "Change Password"
6. Password updated successfully

### Example 3: Deactivate User
1. Find an active user
2. Click the red X icon (❌)
3. Confirm the action
4. User status changes to "Inactive"
5. User can no longer login

## Troubleshooting

### Issue: "Access Denied"
**Solution:** Make sure you're logged in as an admin user

### Issue: "Username already exists"
**Solution:** Choose a different username

### Issue: "Passwords do not match"
**Solution:** Make sure both password fields are identical

### Issue: Cannot deactivate user
**Solution:** You cannot deactivate your own account

## Database Schema

The system uses the existing `users` table:

```sql
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    role ENUM('user', 'admin') DEFAULT 'user',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at DATETIME,
    last_login DATETIME
);
```

## Next Steps

1. **Test the system:**
   - Create a test user
   - Change their password
   - Deactivate and reactivate them

2. **Customize as needed:**
   - Add more user fields
   - Add edit user functionality
   - Add delete user functionality

3. **Security hardening:**
   - Enable SSL/HTTPS
   - Add email verification
   - Add password strength requirements

## Support

For issues or questions:
1. Check the error messages
2. Review the browser console
3. Check PHP error logs
