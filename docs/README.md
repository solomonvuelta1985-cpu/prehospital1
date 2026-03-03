# 🏥 Pre-Hospital Care Form - PHP Conversion System

A secure, production-ready PHP application for managing emergency medical services documentation.

## 📋 Overview

This system converts the TONYANG.html static form into a fully functional PHP application with:
- ✅ **100% Design Preservation** - Original Bootstrap 5 design maintained
- ✅ **Enterprise Security** - CSRF protection, PDO prepared statements, input sanitization
- ✅ **Interactive Features** - Body diagram injury mapping, vehicle selection
- ✅ **Complete CRUD** - Create, Read, Update, Delete operations
- ✅ **Role-Based Access** - Admin and user roles
- ✅ **Activity Logging** - Full audit trail

## 🚀 Quick Start

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- mod_rewrite enabled (Apache)

### Installation

1. **Clone/Download Files**
```bash
# Place all files in your web root
/var/www/html/prehospital_care/
```

2. **Create Database**
```bash
mysql -u root -p < database_schema.sql
```

3. **Configure Database**
Edit `includes/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'prehospital_care');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
```

4. **Set Permissions**
```bash
mkdir uploads
chmod 755 uploads
chown www-data:www-data uploads
```

5. **Access System**
```
http://localhost/prehospital_care/public/login.php
```

**Default Login:**
- Username: `admin`
- Password: `admin123`

⚠️ **IMPORTANT:** Change default password immediately!

## 📁 File Structure

```
project_root/
├── includes/
│   ├── config.php          # Database configuration
│   ├── functions.php       # Helper functions
│   └── auth.php           # Authentication
├── public/
│   ├── TONYANG.php        # Main form (PHP version)
│   ├── login.php          # Login page
│   └── logout.php         # Logout handler
├── api/
│   └── TONYANG_save.php   # Form submission handler
├── admin/
│   └── dashboard.php      # Admin dashboard
├── uploads/               # File uploads directory
├── database_schema.sql    # Database structure
├── IMPLEMENTATION_GUIDE.md # Detailed documentation
└── README.md             # This file
```

## 🔐 Security Features

### Implemented Protections
- ✅ **SQL Injection** - PDO prepared statements
- ✅ **XSS** - Output escaping with htmlspecialchars()
- ✅ **CSRF** - Token-based protection
- ✅ **Session Fixation** - Session regeneration on login
- ✅ **Rate Limiting** - Prevents brute force attacks
- ✅ **Input Validation** - Server-side validation
- ✅ **Authentication** - Required for all operations
- ✅ **Activity Logging** - Complete audit trail

### Security Headers
```php
X-Frame-Options: DENY
X-Content-Type-Options: nosniff
X-XSS-Protection: 1; mode=block
Content-Security-Policy: default-src 'self'
```

## 📊 Database Schema

### Main Tables
- **users** - User accounts and authentication
- **prehospital_forms** - Form data (80+ fields)
- **injuries** - Body diagram injury markers
- **activity_logs** - Audit trail
- **vehicles** - Vehicle management

### Key Features
- JSON fields for complex data (arrays)
- Foreign key constraints
- Optimized indexes
- Audit timestamps

## 🎯 Features

### Form Management
- 7-section multi-step form
- Progress tracking
- Tab navigation
- Auto-save capability
- Print functionality

### Interactive Body Diagram
- Click to mark injuries
- 6 injury types (laceration, fracture, burn, contusion, abrasion, other)
- Front and back views
- Notes for each injury
- Export injury data

### Vehicle Selection
- 12 Ambulances (V1-V12)
- 2 Fire Truck types (Penetrator, Tanker)
- Modal selection interface
- Plate number tracking

### Admin Dashboard
- Statistics overview
- Recent forms list
- User management
- Activity monitoring

## 📖 Usage

### Creating a Form
1. Login to system
2. Navigate to TONYANG.php
3. Fill out 7 sections:
   - Basic Information
   - Patient Details
   - Emergency Type
   - Vital Signs
   - Assessment
   - Team Information
   - Completion
4. Mark injuries on body diagram (optional)
5. Submit form

### Viewing Forms
1. Login as admin
2. Access dashboard
3. View statistics and recent forms
4. Click actions to view/edit/delete

## 🔧 Configuration

### Database Settings
`includes/config.php`
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'prehospital_care');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### Upload Settings
```php
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB
```

### Session Settings
```php
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Strict');
```

## 🧪 Testing

### Test Credentials
- Admin: `admin` / `admin123`

### Test Checklist
- [ ] Login functionality
- [ ] CSRF protection
- [ ] Form submission
- [ ] Injury marking
- [ ] Vehicle selection
- [ ] Data validation
- [ ] Flash messages
- [ ] Activity logging
- [ ] Mobile responsiveness
- [ ] Print functionality

## 📝 API Endpoints

### POST /api/TONYANG_save.php
Save new form

**Required Fields:**
- csrf_token
- form_date
- patient_name
- date_of_birth
- age
- gender

**Response:**
```json
{
  "success": true,
  "message": "Form saved successfully",
  "form_number": "PHC-20250115-A1B2C3D4",
  "form_id": 123
}
```

## 🛠️ Maintenance

### Backup Database
```bash
mysqldump -u root -p prehospital_care > backup_$(date +%Y%m%d).sql
```

### View Logs
```bash
tail -f /var/log/apache2/error.log
```

### Monitor Activity
```sql
SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 100;
```

## 🚨 Troubleshooting

### Database Connection Error
- Check credentials in config.php
- Verify MySQL service is running
- Check database exists

### Permission Denied
```bash
chmod 755 uploads/
chown www-data:www-data uploads/
```

### Session Issues
- Check session.save_path is writable
- Verify session cookies enabled
- Clear browser cookies

## 📚 Documentation

- **IMPLEMENTATION_GUIDE.md** - Comprehensive technical documentation
- **database_schema.sql** - Database structure with comments
- Inline code comments throughout

## 🔄 Updates

### Version 1.0.0 (January 2025)
- Initial release
- Complete PHP conversion
- Security implementation
- Admin dashboard
- Activity logging

## 👥 Support

For issues or questions:
1. Check IMPLEMENTATION_GUIDE.md
2. Review inline code comments
3. Check activity_logs table
4. Review PHP error logs

## ⚖️ License

This system is provided as-is for emergency medical services documentation.

## 🎉 Credits

- **Bootstrap 5** - UI Framework
- **Bootstrap Icons** - Icon library
- **PHP** - Backend language
- **MySQL** - Database system

---

**System Version:** 1.0.0  
**Last Updated:** January 2025  
**Status:** Production Ready ✅

## 🔒 Security Notice

⚠️ **IMPORTANT SECURITY STEPS:**

1. Change default admin password immediately
2. Enable HTTPS in production
3. Set secure session cookies
4. Configure Content Security Policy
5. Regular security updates
6. Monitor activity logs
7. Implement backup strategy

---

**Ready for Deployment!** 🚀
