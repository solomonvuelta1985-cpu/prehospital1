# Folder Rename Instructions
## Renaming `123` to `prehospital`

---

## ✅ Files Already Prepared

The following files have been updated to work with the new folder name `prehospital`:

1. ✅ `.htaccess` - Error log path updated to `C:/xampp/htdocs/prehospital/php_error.log`
2. ✅ `PRODUCTION_DEPLOYMENT_GUIDE.md` - Instructions updated with new folder name

---

## 📁 HOW TO RENAME THE FOLDER

### ⚠️ IMPORTANT: Close these applications first!
- Close VSCode (or any IDE with the project open)
- Stop Apache in XAMPP Control Panel
- Stop MySQL in XAMPP Control Panel (optional, but recommended)
- Close any File Explorer windows showing the `123` folder

---

### METHOD 1: Using Windows File Explorer (Recommended)

1. **Navigate to the folder:**
   ```
   Open File Explorer
   Go to: c:\xampp\htdocs\
   ```

2. **Rename the folder:**
   - Find the folder named `123`
   - Right-click on it
   - Select "Rename"
   - Type: `prehospital`
   - Press Enter

3. **Verify the rename:**
   - Check that the folder is now: `c:\xampp\htdocs\prehospital\`
   - All files should still be inside

---

### METHOD 2: Using Command Prompt

1. **Open Command Prompt:**
   - Press `Win + R`
   - Type `cmd` and press Enter

2. **Navigate and rename:**
   ```bash
   cd c:\xampp\htdocs
   rename 123 prehospital
   ```

3. **Verify:**
   ```bash
   dir
   ```
   You should see `prehospital` folder listed.

---

### METHOD 3: Using PowerShell

1. **Open PowerShell:**
   - Press `Win + X`
   - Select "Windows PowerShell"

2. **Navigate and rename:**
   ```powershell
   cd c:\xampp\htdocs
   Rename-Item -Path "123" -NewName "prehospital"
   ```

3. **Verify:**
   ```powershell
   Get-ChildItem
   ```

---

## 🔧 AFTER RENAMING - IMPORTANT STEPS

### 1. Update Git Remote (if using Git)

The folder rename doesn't affect Git, but you should verify:

```bash
cd c:\xampp\htdocs\prehospital
git status
```

Everything should work normally. Git tracks the repository, not the folder name.

### 2. Restart XAMPP Services

- Open XAMPP Control Panel
- Start Apache
- Start MySQL
- Check that services start without errors

### 3. Update VSCode Workspace

If you're using VSCode with a workspace file:

**Option A: Re-open the folder**
- File → Open Folder
- Navigate to `c:\xampp\htdocs\prehospital`
- Open it

**Option B: Update workspace file** (if you have a `.code-workspace` file)
- Open the workspace file in a text editor
- Find any paths containing `123`
- Replace with `prehospital`

### 4. Test Your Local Development Site

Open your browser and test:

**OLD URLs (will no longer work):**
- ❌ `http://localhost/123/public/login.php`

**NEW URLs (use these now):**
- ✅ `http://localhost/prehospital/public/login.php`
- ✅ `http://localhost/prehospital/public/dashboard.php`
- ✅ `http://localhost/prehospital/public/prehospital_form.php`

### 5. Update Browser Bookmarks

If you have any localhost bookmarks, update them:
- Change `/123/` to `/prehospital/` in all URLs

---

## ✅ VERIFICATION CHECKLIST

After renaming, verify everything works:

- [ ] Folder renamed successfully to `prehospital`
- [ ] Apache and MySQL start in XAMPP
- [ ] Can access: `http://localhost/prehospital/public/login.php`
- [ ] Login page loads correctly
- [ ] Can log in successfully
- [ ] Dashboard loads after login
- [ ] No errors in browser console (F12)
- [ ] Git repository still works (`git status` shows no issues)
- [ ] VSCode/IDE opens the project correctly
- [ ] Error log file appears at: `c:\xampp\htdocs\prehospital\php_error.log`

---

## 🚨 TROUBLESHOOTING

### Issue: "Cannot rename folder - it's being used"

**Solution:**
1. Close all applications accessing the folder
2. Stop XAMPP Apache and MySQL
3. Close VSCode/IDE
4. Close File Explorer windows
5. Try renaming again

### Issue: "Permission denied"

**Solution:**
1. Run File Explorer as Administrator
2. Right-click on `123` folder → Properties → Security
3. Ensure you have "Full Control" permissions
4. Try renaming again

### Issue: After rename, site doesn't load

**Solution:**
1. Restart XAMPP (stop and start Apache/MySQL)
2. Clear browser cache (Ctrl + Shift + Delete)
3. Try accessing: `http://localhost/prehospital/public/login.php`
4. Check XAMPP error logs

### Issue: Git shows deleted/new files after rename

**Solution:**
This is normal. Git may see it as files moved. To fix:
```bash
cd c:\xampp\htdocs\prehospital
git add -A
git status
```

Git should recognize it as a rename, not deletion + addition.

---

## 📝 SUMMARY

**What changes:**
- Local development folder path: `c:\xampp\htdocs\123\` → `c:\xampp\htdocs\prehospital\`
- Local URLs: `localhost/123/...` → `localhost/prehospital/...`

**What stays the same:**
- All your code and files (unchanged)
- Git repository and history (intact)
- Database (unchanged)
- XAMPP configuration (mostly unchanged)
- Production deployment (will use different path anyway)

**Production deployment:**
When you deploy to **rescue116-link.online**, the folder structure on the server will be:
```
/home/rescue116link/public_html/
├── api/
├── includes/
├── public/
└── ...
```

The local folder name doesn't affect production!

---

## ✨ READY TO RENAME!

Follow the steps above, and your project will be renamed from `123` to `prehospital` successfully!

---

*Last Updated: January 10, 2026*
