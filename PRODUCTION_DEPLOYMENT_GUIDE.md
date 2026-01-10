# Production Deployment Guide
## Pre-Hospital Care System - rescue116-link.online

---

## 📋 PRE-DEPLOYMENT CHECKLIST

### 1. LOCAL PREPARATION

#### A. Rename Project Folder
**Current folder name:** `123`
**New folder name:** `prehospital`

**Rename the folder on your local machine:**

```bash
# On Windows Command Prompt (navigate to c:\xampp\htdocs\):
cd c:\xampp\htdocs
rename 123 prehospital
```

**Or using File Explorer:**
1. Navigate to `c:\xampp\htdocs\`
2. Right-click the `123` folder
3. Select "Rename"
4. Type `prehospital` and press Enter

**After renaming:**
- Local development URL: `http://localhost/prehospital/public/login.php`
- All Git history and files remain intact
- You may need to update VSCode workspace if using workspace files

#### B. Files Already Updated for Production
✅ `.htaccess` - Error logging configured for production
✅ `includes/config.php` - Database settings prepared for production
✅ HTTPS redirect enabled
✅ Error display disabled (only logging enabled)

---

## 🔧 CONFIGURATION UPDATES REQUIRED

### 1. Database Configuration
Edit `includes/config.php`:

```php
// Update these lines with your actual database credentials:
define('DB_HOST', 'localhost'); // Usually 'localhost' for cPanel
define('DB_NAME', 'rescue116link_prehospital_db'); // Your actual DB name from cPanel
define('DB_USER', 'rescue116link_dbuser'); // Your DB username from cPanel
define('DB_PASS', 'YOUR_SECURE_PASSWORD_HERE'); // Your DB password
```

**Where to find these:**
- Login to cPanel at your hosting provider
- Go to **MySQL Databases** or **phpMyAdmin**
- Create a new database named: `rescue116link_prehospital_db` (or similar)
- Create a new user with a strong password
- Grant ALL PRIVILEGES to the user on this database

---

### 2. reCAPTCHA Keys
Edit `includes/config.php`:

```php
// Get these from: https://www.google.com/recaptcha/admin
define('RECAPTCHA_SITE_KEY', 'YOUR_SITE_KEY_HERE');
define('RECAPTCHA_SECRET_KEY', 'YOUR_SECRET_KEY_HERE');
```

**Steps to get reCAPTCHA keys:**
1. Visit https://www.google.com/recaptcha/admin
2. Click "+" to register a new site
3. Label: "Rescue 116 Pre-Hospital Care"
4. reCAPTCHA type: **reCAPTCHA v2** → "I'm not a robot" Checkbox
5. Domains: Add `rescue116-link.online` and `www.rescue116-link.online`
6. Copy the Site Key and Secret Key to config.php

---

### 3. Error Log Path
The `.htaccess` file has been updated to:
```apache
php_value error_log "/home/rescue116link/public_html/php_error.log"
```

**Verify the path on your server:**
- The path should match your hosting account structure
- Typical cPanel path: `/home/USERNAME/public_html/`
- Replace `rescue116link` if your cPanel username is different

---

## 📤 FILE UPLOAD & DEPLOYMENT

### Step 1: Upload Files
Using FTP/SFTP client (FileZilla, WinSCP, or cPanel File Manager):

```
Local:                          Remote:
c:\xampp\htdocs\123\    →      /home/rescue116link/public_html/

Upload ALL files and folders:
├── api/
├── includes/
├── public/
├── database_migrations/
├── .htaccess
├── database_schema.sql
└── (all other files)
```

**Important:**
- Upload to your web root: `/home/rescue116link/public_html/`
- Set file permissions: **644** for files, **755** for directories
- Ensure `.htaccess` is uploaded (might be hidden)

---

### Step 2: Database Setup

#### A. Create Database (via cPanel)
1. Login to cPanel
2. Go to **MySQL Databases**
3. Create database: `rescue116link_prehospital_db`
4. Create user: `rescue116link_dbuser`
5. Set a **strong password** (minimum 12 characters, mixed case, numbers, symbols)
6. Add user to database with **ALL PRIVILEGES**

#### B. Import Database Schema
1. Go to **phpMyAdmin** in cPanel
2. Select your database: `rescue116link_prehospital_db`
3. Click **Import** tab
4. Upload and execute in this order:
   ```
   1. database_schema.sql (main structure)
   2. database_security_updates.sql (security enhancements)
   3. database_migration_add_endorsement_attachment.sql (endorsement feature)
   4. All files in database_migrations/ folder (in order)
   ```

#### C. Create Admin User
Run this SQL in phpMyAdmin after importing schema:

```sql
-- Create default admin account
-- Password: Admin@123 (CHANGE THIS IMMEDIATELY AFTER FIRST LOGIN!)
INSERT INTO users (username, password, email, full_name, role, status, created_at)
VALUES (
    'admin',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- Admin@123
    'admin@rescue116-link.online',
    'System Administrator',
    'admin',
    'active',
    NOW()
);
```

---

### Step 3: Directory Permissions

Set proper permissions via FTP or cPanel File Manager:

```bash
# Directories - 755 (rwxr-xr-x)
chmod 755 api
chmod 755 includes
chmod 755 public
chmod 755 uploads

# Files - 644 (rw-r--r--)
chmod 644 includes/config.php
chmod 644 .htaccess

# Uploads directory - 755 (writable by web server)
chmod 755 uploads
```

**Create uploads directory if it doesn't exist:**
```bash
mkdir /home/rescue116link/public_html/uploads
chmod 755 uploads
```

---

### Step 4: Update config.php with Production Credentials

SSH or cPanel File Manager:
```bash
nano /home/rescue116link/public_html/includes/config.php
```

Update:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'rescue116link_prehospital_db'); // Your actual DB name
define('DB_USER', 'rescue116link_dbuser'); // Your actual DB user
define('DB_PASS', 'YourActualSecurePassword123!'); // Your actual password

define('RECAPTCHA_SITE_KEY', '6Lc...YOUR_SITE_KEY');
define('RECAPTCHA_SECRET_KEY', '6Lc...YOUR_SECRET_KEY');
```

---

## 🔒 SECURITY CHECKLIST

### Post-Deployment Security Steps

1. **Change Default Admin Password**
   - Login immediately after deployment
   - Go to Profile/Settings
   - Change from `Admin@123` to a strong unique password

2. **Verify HTTPS is Working**
   - Visit: https://rescue116-link.online
   - Should show padlock icon in browser
   - If not, contact your hosting provider to enable SSL certificate

3. **Test reCAPTCHA**
   - Try logging in - should show reCAPTCHA
   - If not working, verify keys are correct

4. **Check Error Logging**
   - Errors should NOT display on screen (production mode)
   - Check error log: `/home/rescue116link/public_html/php_error.log`

5. **Set Secure File Permissions**
   ```bash
   # Make config.php read-only
   chmod 400 includes/config.php

   # Protect sensitive directories
   chmod 755 uploads
   chmod 755 includes
   ```

6. **Configure PHP Settings (via .htaccess or cPanel)**
   ```apache
   php_flag display_errors Off
   php_flag log_errors On
   php_value upload_max_filesize 10M
   php_value post_max_size 10M
   php_value max_execution_time 300
   php_value memory_limit 256M
   ```

7. **Backup Strategy**
   - Setup automatic database backups in cPanel
   - Frequency: Daily
   - Retention: 7-30 days
   - Store backups offsite

---

## 🌐 DNS & DOMAIN CONFIGURATION

### Ensure Domain Points to Server

1. **DNS Records** (via your domain registrar):
   ```
   Type    Host                Value                       TTL
   ───────────────────────────────────────────────────────────
   A       @                   [Your Server IP]            3600
   A       www                 [Your Server IP]            3600
   CNAME   www                 rescue116-link.online       3600
   ```

2. **SSL Certificate**
   - cPanel usually provides **free SSL** via Let's Encrypt
   - Go to cPanel → SSL/TLS Status
   - Run AutoSSL for `rescue116-link.online`

---

## ✅ POST-DEPLOYMENT TESTING

### Test Checklist:

- [ ] **Homepage loads:** https://rescue116-link.online
- [ ] **HTTPS redirect works:** http://rescue116-link.online → https://
- [ ] **Login page works:** https://rescue116-link.online/public/login.php
- [ ] **Admin login successful** (username: admin, password: Admin@123)
- [ ] **reCAPTCHA displays** on login page
- [ ] **Dashboard accessible** after login
- [ ] **Create a test patient record** - Form A
- [ ] **Upload attachment** - Test file upload functionality
- [ ] **View records** - Check records listing
- [ ] **Generate report** - Test PDF/Excel export
- [ ] **User management works** (Admin panel → Users)
- [ ] **Activity logs recording** (Admin panel → Logs)
- [ ] **Logout works**
- [ ] **Error handling** - No PHP errors displayed on screen
- [ ] **Mobile responsive** - Test on mobile device

---

## 🚨 TROUBLESHOOTING

### Common Issues:

#### 1. **"Database connection failed"**
- Check database credentials in `includes/config.php`
- Verify database exists in cPanel
- Ensure user has privileges on database
- Check database host (usually `localhost`)

#### 2. **"500 Internal Server Error"**
- Check `.htaccess` syntax
- Review `php_error.log` for actual error
- Verify file permissions (755 directories, 644 files)
- Check PHP version (minimum 7.4 required)

#### 3. **"Page not found" or routes not working**
- Ensure `.htaccess` is uploaded
- Check if mod_rewrite is enabled (contact host)
- Verify `RewriteEngine On` in `.htaccess`

#### 4. **reCAPTCHA not showing**
- Verify site key and secret key are correct
- Check domain is registered with Google reCAPTCHA
- Clear browser cache

#### 5. **File uploads failing**
- Check `uploads/` directory exists
- Verify permissions: `chmod 755 uploads`
- Check PHP upload limits in `.htaccess`
- Ensure directory is writable by web server

#### 6. **HTTPS not working**
- Install SSL certificate via cPanel
- Contact hosting support for SSL installation
- Check domain DNS propagation (may take 24-48 hours)

---

## 📞 SUPPORT & MAINTENANCE

### Regular Maintenance Tasks:

**Daily:**
- Monitor error logs: `php_error.log`
- Check activity logs for suspicious activity

**Weekly:**
- Review user accounts for inactive/locked users
- Test backup restoration process
- Check disk space usage

**Monthly:**
- Update admin passwords
- Review and clean old draft records
- Database optimization (via phpMyAdmin)

### Security Updates:
- Keep PHP version updated (contact hosting)
- Monitor for security patches
- Review access logs regularly

---

## 📝 ADDITIONAL NOTES

### Folder Name Consideration:
- Current local folder: `c:\xampp\htdocs\123`
- On production, you can rename to anything
- Common choices: `prehospital`, `rescue116`, `app`, `system`
- The folder name on server doesn't affect the domain URL
- Example: Files in `/home/rescue116link/public_html/` are accessed via `https://rescue116-link.online/`

### Contact Information:
- Domain: https://rescue116-link.online
- Admin Email: admin@rescue116-link.online
- Support: Update this with your actual support contact

---

## ✨ DEPLOYMENT COMPLETE!

Once all steps are completed:
1. **Change default admin password immediately**
2. **Test all critical features**
3. **Enable production monitoring**
4. **Setup automated backups**
5. **Train users on the system**

**Good luck with your production deployment! 🚀**

---

*Last Updated: January 10, 2026*
*Project: Pre-Hospital Care System*
*Domain: rescue116-link.online*
