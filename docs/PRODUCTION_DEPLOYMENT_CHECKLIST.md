# Pre-Hospital Care System: Production Deployment Checklist

This application contains patient-identifying and medical information. Treat the database, uploads, logs, `.env`, SQL dumps, and backups as confidential records.

## 1. Make a rollback point

Before every deployment, create a Git commit and a database backup. Never use `git add .` blindly: confirm that `.env`, logs, SQL dumps, archives, and private uploads are not staged.

The current legacy checkout contains tracked deployment artifacts that must be removed from version control before publishing the repository: `composer.phar`, `vendor.zip`, `database_migrations/BACKUP DB.sql`, `database_migrations/btrahnqi_pre_hospital_db (1).sql`, and `public/pre_hospital_db (1).sql`. Keep migration scripts that contain schema changes, but do not commit database dumps.

```powershell
git rm --cached composer.phar vendor.zip "database_migrations/BACKUP DB.sql" "database_migrations/btrahnqi_pre_hospital_db (1).sql" "public/pre_hospital_db (1).sql"
```

```powershell
git status --short --ignored
git diff --name-only
git add <reviewed-files>
git diff --cached --name-only
git commit -m "Checkpoint before production deployment"
git tag production-before-YYYYMMDD
```

Keep the previous application directory and database backup until the new release passes the smoke tests. A rollback restores both code and database state; restoring only code can leave migrations incompatible.

## 2. Prepare the server

- Use a supported PHP release with `pdo_mysql`, `openssl`, `fileinfo`, `mbstring`, and `json` enabled.
- Use MySQL/MariaDB with a dedicated database and a dedicated application user. Do not use `root` in production.
- Enable Apache `mod_rewrite`, `mod_headers`, and `mod_ssl`; disable directory listing.
- Point the virtual host at the application directory only while the current URL structure is retained. The root `.htaccess` denies sensitive directories and deployment artifacts. A later refactor can move the public document root to `public/`; do not change the document root without testing the existing `/api` and `/public` URL paths.
- Restrict filesystem permissions: the web server needs write access only to `uploads/` and the configured PHP log location. It must not write source code, `.env`, SQL, or `.git`.
- Enforce HTTPS and redirect HTTP at the web server. Keep localhost HTTP for offline XAMPP development.

## 3. Install the release

Copy the reviewed commit to the server, then install the locked dependencies without development packages:

```bash
composer validate --strict
composer install --no-dev --prefer-dist --optimize-autoloader
composer audit --locked
```

Do not upload `composer.phar`, `vendor.zip`, SQL dumps, test files, or backup archives into a public directory. The repository `.gitignore` prevents common accidental additions, but still inspect the staged file list.

## 4. Configure secrets

Copy `.env.example` to `.env` and set unique production values:

```dotenv
APP_ENV=production
APP_URL=https://your-real-domain.example/prehospital
DB_HOST=127.0.0.1
DB_NAME=pre_hospital_db
DB_USER=prehospital_app
DB_PASS=<long-random-database-password>
APP_ENCRYPTION_KEY=<at-least-32-random-bytes>
FLUTTER_APP_KEY=<unique-random-secret-or-leave-empty>
WEBAUTHN_RP_ID=your-real-domain.example
WEBAUTHN_ORIGIN=https://your-real-domain.example
TRUST_PROXY_HEADERS=false
OFFLINE_MODE=false
```

Generate secrets with a local trusted PHP installation, then store them in a secret manager or protected server file. Never commit them. If `FLUTTER_APP_KEY` is empty, Flutter authentication is intentionally disabled rather than protected by a default key.

## 5. Apply database migrations

Back up the database first. Apply each migration once, in this order, using an administrative database account:

1. `database_migrations/add_account_security_fields.sql`
2. `database_migrations/add_encryption_support.sql`
3. `database_migrations/add_refusal_waiver.sql`
4. `database_migrations/add_session_version.sql`
5. `database_migrations/add_flutter_auth_tokens.sql`

The refusal-waiver migration stores only the file path and status. The session migration allows an administrator to revoke active sessions. Confirm the new columns exist before allowing users to sign in.

For a new database, import the schema and then create the first administrator from the server console:

```bash
php scripts/create_admin.php
```

There is deliberately no default `admin/admin123` account in the schema. Delete or disable any old default account and require a password change for accounts created from temporary credentials.

## 6. Upload and privacy checks

- Keep `uploads/` outside the web root when possible. If it remains inside the project, direct access to patient documents is blocked and files must be served through `api/serve_file.php`.
- Verify that a private image URL returns `403` or `404` without a logged-in session and that a logged-in user cannot access another user's record attachment.
- Keep PHP error logs out of the web root or deny them with the included rules. Logs may contain patient identifiers and secrets.
- Do not place database exports, `.env`, `.git`, `vendor.zip`, or `composer.phar` in a public directory.

## 7. Application checks

Run these checks after deployment:

- Login, logout, password change, and forced password-change flow.
- Create, edit, view, export, and delete a test record with two separate users; verify ownership restrictions.
- Upload and view a patient image, endorsement, and refusal waiver through the authenticated UI.
- Disable/restrict a test account and confirm an existing session is rejected on its next request.
- Confirm non-admin users cannot filter reports by another user.
- Confirm GET requests to autosave and archive endpoints are rejected.
- Confirm `/.env`, `/.git/HEAD`, `/database_migrations/`, `/vendor/`, `/composer.phar`, and SQL/ZIP files are not downloadable.
- Run `php -l` on application PHP files and review the PHP log for new warnings.

## 8. Backups, maintenance, and monitoring

- Use encrypted, access-controlled daily database backups and test restoration regularly.
- Keep at least one backup offline or in a separate account. Do not expose backup files through Apache.
- Run archival only from a protected CLI/cron job:

```bash
php scripts/archive_old_records.php
```

- Run database backups through a protected CLI process, not a public URL.
- Rotate application, database, WebAuthn, and Flutter secrets after suspected exposure.
- Monitor failed logins, account restrictions, export activity, file downloads, and error rates.
- Define a breach response: disable accounts, preserve logs, rotate keys, revoke sessions, restore only from verified backups, and document the incident.

## 9. Offline localhost/XAMPP operation

The server-side hardening works without internet access on `http://localhost`; HTTPS is intentionally not forced for localhost. Database access, sessions, CSRF, role checks, private uploads, and local backups continue to work offline.

Bootstrap, Bootstrap Icons, Notiflix, Chart.js, and the application fonts are now downloaded and served locally from `public/vendor/`, so those core interface features continue to work without internet access. Mapping geocoding through Nominatim and any reCAPTCHA integration remain external by design; either provide controlled connectivity for them or disable/replace them through an explicitly reviewed offline configuration.

## 10. Security findings to resolve before production

The local ZAP review identified the following items. Do not treat the application as production-ready until each item is resolved or formally risk-accepted:

- **Patient data in browser storage:** This risk has been remediated in the current release. The legacy `prehospital_autosave_draft` key is cleared and the clinical form uses authenticated server-side drafts through `api/autosave_draft.php`. Confirm that no patient or medical fields are written to browser storage after deployment, and clear existing browser storage when migrating.
- **Third-party frontend assets:** Core Bootstrap, Bootstrap Icons, Notiflix, Chart.js, and font assets are self-hosted under `public/vendor/`. Keep the copies reviewed and version-pinned. Confirm that no new external script or stylesheet is introduced; if a future integration must remain on a CDN, add verified SRI `integrity` and `crossorigin` attributes and document the dependency.
- **Content Security Policy:** The application has a CSP and nonce-protected scripts, but still permits `style-src 'unsafe-inline'` because of inline styles and attributes. Refactor inline CSS and handlers into local files or replace them with reviewed nonces/hashes before tightening the policy. Re-test the login, dashboard, and clinical form after every CSP change.
- **HTTPS and HSTS:** Local XAMPP intentionally runs over HTTP. Production must use valid HTTPS, redirect HTTP to HTTPS, and return HSTS only after HTTPS is confirmed on every required hostname.
- **Server fingerprinting:** Back up the Apache configuration, then configure `ServerTokens Prod` and `ServerSignature Off`, restart Apache, and confirm that responses no longer disclose detailed Apache/PHP versions.
- **MIME sniffing protection:** Enable Apache `mod_headers` and return `X-Content-Type-Options: nosniff`. Confirm the header is present on HTML, JavaScript, CSS, and file-download responses.
- **Cache control:** Confirm authenticated patient pages and private downloads are not stored in shared or browser caches. Re-test records, drafts, reports, and served attachments.
- **Comments and timestamps:** Remove comments that reveal internal implementation details, credentials, paths, or operational information. Treat timestamp disclosure as a low-priority hardening item unless it exposes sensitive operational data.
- **ZAP scope:** Separate local findings from browser telemetry and third-party hosts such as Google, Google Fonts, jsDelivr, and Chrome update services. The application report should use a context limited to `http(s)://localhost/prehospital/` and `http(s)://127.0.0.1/prehospital/`; review third-party CDN risks separately.

## 11. Final hardening and retest sequence

Before production release:

1. Create a database backup and commit checkpoint.
2. Back up the production Apache configuration before changing headers.
3. Apply Apache fingerprinting and `X-Content-Type-Options` hardening.
4. Restart Apache and verify that XAMPP/localhost still works.
5. Enable HTTPS and HSTS only on the production HTTPS virtual host. Never force HSTS for the HTTP localhost workflow.
6. Run the authenticated ZAP scan using a context limited to the application's localhost/production hostnames.
7. Confirm there are no High or Critical findings; resolve or formally risk-accept remaining Low/Informational findings.
8. Export the final ZAP report and attach it to the deployment record.
