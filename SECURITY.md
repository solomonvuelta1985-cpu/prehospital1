# Security Configuration

## Overview

This application implements **conditional security** that adapts based on the client type:
- **Desktop Browsers**: Full security protection with strict policies
- **Mobile App Webviews**: Relaxed policies for compatibility while maintaining core security

## Security Features by Client Type

### Desktop Browsers (Full Protection)

| Feature | Setting | Purpose |
|---------|---------|---------|
| X-Frame-Options | SAMEORIGIN | Prevents clickjacking attacks |
| Session SameSite | Strict | Prevents CSRF via third-party cookies |
| CSRF Tokens | Required | Validates form submissions |
| reCAPTCHA | Required | Prevents automated attacks |
| X-Content-Type-Options | nosniff | Prevents MIME sniffing |
| X-XSS-Protection | 1; mode=block | XSS attack protection |
| Referrer-Policy | strict-origin-when-cross-origin | Controls referrer information |

### Mobile App Webviews (Compatibility Mode)

| Feature | Setting | Purpose |
|---------|---------|---------|
| X-Frame-Options | Not set | Allows embedding in mobile apps |
| Session SameSite | Lax | Better cookie compatibility |
| CSRF Tokens | Bypassed | Avoids session dependency issues |
| reCAPTCHA | Bypassed | Mobile webviews often can't render it |
| X-Content-Type-Options | nosniff | Maintained |
| X-XSS-Protection | 1; mode=block | Maintained |
| Referrer-Policy | strict-origin-when-cross-origin | Maintained |

## Security Features Still Active (All Clients)

Even in mobile compatibility mode, these protections remain active:

1. **Password Hashing**: All passwords use PHP's `password_hash()` with bcrypt
2. **SQL Injection Protection**: PDO prepared statements for all database queries
3. **XSS Protection**: All output is escaped with `htmlspecialchars()`
4. **Rate Limiting**: 10 login attempts per 10 minutes per IP address
5. **Account Lockout**: 5 failed attempts triggers permanent restriction
6. **IP Logging**: All login attempts and activities are logged
7. **HTTPS Enforcement**: Automatic redirect to HTTPS in production
8. **Secure Cookies**: HTTPOnly and Secure flags on production
9. **Input Sanitization**: All user input is sanitized
10. **Session Security**: Session regeneration on login/privilege escalation

## Mobile Webview Detection

The system detects mobile webviews using multiple methods:

### Automatic Detection (User Agent)
Checks for these patterns in the User-Agent header:
- `wv` (Android WebView)
- `WebView`
- `Replit`
- `Flutter`
- `ReactNative`
- `Cordova`
- `Ionic`

### Manual Detection Methods

#### Method 1: URL Parameter (Testing)
```
https://rescue116-link.online/prehospital/public/login.php?mobile_app=1
```

#### Method 2: Custom HTTP Header (Production)
```javascript
headers: {
  'X-Mobile-App': 'true'
}
```

## Files Modified for Security

### 1. `.htaccess`
- **Location**: Root directory
- **Purpose**: Security headers for static files
- **X-Frame-Options**: SAMEORIGIN (fallback for non-PHP files)

### 2. `includes/config.php`
- **Location**: Includes directory
- **Purpose**: Conditional security headers and session configuration
- **Lines 66-105**: Mobile detection and conditional security
- **Function**: `is_mobile_webview()` - Detects mobile app webviews

### 3. `public/login.php`
- **Location**: Public directory
- **Purpose**: Login form with conditional CSRF and reCAPTCHA
- **Lines 25, 34**: Skip CSRF and reCAPTCHA for mobile webviews
- **Lines 327-331**: Mobile app mode indicator badge

## Security Recommendations

### For Production Deployment

1. **Monitor Logs**: Regularly check `/home/rescue116link/public_html/php_error.log`
2. **Review Failed Logins**: Check for unusual patterns in activity logs
3. **Update Dependencies**: Keep PHP and server software up to date
4. **Strong Passwords**: Enforce password complexity requirements
5. **Regular Backups**: Maintain encrypted database backups

### For Mobile App Development

1. **Use Custom Header**: Add `X-Mobile-App: true` header for explicit mobile detection
2. **HTTPS Only**: Always use HTTPS URLs in production
3. **Validate Certificates**: Enable SSL certificate validation in webview
4. **Secure Storage**: Don't cache credentials in the mobile app
5. **User Agent**: Consider setting a custom user agent for your app

## Security Trade-offs

### Why These Compromises?

Mobile webviews have fundamental limitations:
- **Cookie Restrictions**: Many webviews block or limit cookies
- **Session Storage**: Sessions rely on cookies, which may not persist
- **Third-party Context**: Webviews are considered "third-party" contexts
- **reCAPTCHA Issues**: Google's reCAPTCHA often fails in webviews

### Risk Mitigation

Despite relaxed settings for mobile, security is maintained through:
- Rate limiting prevents brute force attacks
- Account lockout after 5 failed attempts
- All requests are logged with IP addresses
- Password hashing protects credentials
- Input sanitization prevents injection attacks

## Testing Security

### Test CSRF Protection (Desktop)
1. Open login page in Chrome/Firefox
2. Try submitting form without token → Should fail
3. Check browser console for CSRF token presence

### Test Mobile Mode
1. Add `?mobile_app=1` to URL
2. Verify blue "Mobile App Mode" badge appears
3. Verify reCAPTCHA is hidden
4. Login should work without CSRF errors

### Test Session Diagnostics
Visit: `https://rescue116-link.online/prehospital/public/session_test.php`
- Shows session status
- Displays cookie configuration
- Tests session persistence

## Incident Response

If security issues are detected:

1. **Check Logs**: Review `php_error.log` for suspicious activity
2. **Restrict Accounts**: Use admin panel to restrict compromised accounts
3. **Reset Passwords**: Force password reset for affected users
4. **Review Access**: Check `activity_logs` table for unauthorized access
5. **Update Config**: Adjust rate limits or lockout thresholds if needed

## Contact

For security concerns or questions:
- Review code in `includes/config.php` and `includes/auth.php`
- Check activity logs in database table `activity_logs`
- Monitor error logs at `/home/rescue116link/public_html/php_error.log`

---

**Last Updated**: 2026-01-15
**Version**: 1.1.0
**Security Level**: Adaptive (Desktop: High | Mobile: Medium)
