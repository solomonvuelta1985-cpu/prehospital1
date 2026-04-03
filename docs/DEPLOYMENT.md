# Deployment Guide — Pre-Hospital Care System

## Overview

This project uses **GitHub as the source of truth**. Every time you push code to the `main` branch, **GitHub Actions automatically deploys to the production server** via SSH — no manual uploads, no zipping, no FTP.

---

## Production Server Details

| Item | Value |
|---|---|
| Hosting | cPanel (shared hosting) |
| Domain | `rescue116-link.online` |
| Project folder | `/home/btrahnqi/rescue116-link.online/prehospital` |
| cPanel username | `btrahnqi` |
| GitHub repo | `https://github.com/solomonvuelta1985-cpu/prehospital1` |

---

## How Auto-Deployment Works

```
You edit code locally (XAMPP)
        ↓
git push to GitHub (main branch)
        ↓
GitHub Actions triggers automatically
        ↓
GitHub Actions SSHes into rescue116-link.online
        ↓
Runs: git pull origin main
Runs: composer install --no-dev --optimize-autoloader
        ↓
Production is updated ✓
```

**Total time from push to live: ~30–60 seconds.**

---

## Daily Deployment Workflow

### Step 1 — Edit your code locally
Make your changes in `c:\xampp\htdocs\prehospital` using VS Code or any editor.

### Step 2 — Stage your changes
```bash
git add .
# Or add specific files:
git add public/login.php public/prehospital_form.php
```

### Step 3 — Commit with a meaningful message
```bash
git commit -m "fix: describe what you changed"
```

Commit message conventions:
| Prefix | When to use |
|---|---|
| `feat:` | New feature added |
| `fix:` | Bug fix |
| `refactor:` | Code restructure, no behavior change |
| `docs:` | Documentation only |
| `style:` | UI/CSS changes |

### Step 4 — Push to GitHub (triggers auto-deploy)
```bash
git push
```

### Step 5 — Watch the deployment
1. Go to `github.com/solomonvuelta1985-cpu/prehospital1`
2. Click the **Actions** tab
3. You will see the deployment running in real time
4. Green checkmark = deployed successfully
5. Red X = something failed (click it to see the error)

---

## Files Involved in Deployment

### `.github/workflows/deploy.yml`
GitHub Actions workflow — triggers on every push to `main` and SSHes into the server.

```yaml
name: Deploy to Production

on:
  push:
    branches:
      - main

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - name: Trigger cPanel Git deployment via SSH
        uses: appleboy/ssh-action@v1.0.3
        with:
          host: ${{ secrets.SSH_HOST }}
          username: ${{ secrets.SSH_USER }}
          password: ${{ secrets.SSH_PASS }}
          script: |
            cd ~/rescue116-link.online/prehospital
            git pull origin main
            composer install --no-dev --optimize-autoloader
```

### `.cpanel.yml`
Tells cPanel what to run after a manual deployment via cPanel Git Version Control panel.

```yaml
---
deployment:
  tasks:
    - composer install --no-dev --optimize-autoloader --working-dir=/home/btrahnqi/rescue116-link.online/prehospital
```

---

## GitHub Secrets (Required)

These are stored securely in GitHub and injected into the workflow at runtime. Never hardcode these in files.

| Secret Name | Value | Where to manage |
|---|---|---|
| `SSH_HOST` | `rescue116-link.online` | GitHub → repo Settings → Secrets → Actions |
| `SSH_USER` | `btrahnqi` | GitHub → repo Settings → Secrets → Actions |
| `SSH_PASS` | cPanel password | GitHub → repo Settings → Secrets → Actions |

To update a secret:
1. Go to `github.com/solomonvuelta1985-cpu/prehospital1/settings/secrets/actions`
2. Click the pencil icon next to the secret
3. Enter the new value → Save

---

## Files NOT Deployed (gitignored)

These files exist on the server but are **never pushed to GitHub** for security reasons. You must manage them manually on the server.

| File | Reason | How to update |
|---|---|---|
| `includes/config.php` | Contains DB credentials | Upload via cPanel File Manager |
| `.env` | Environment variables (reCAPTCHA keys, etc.) | Upload via cPanel File Manager |
| `backups/` | Database backups | Managed on server only |
| `uploads/` | Patient uploaded files | Managed on server only |

---

## Manual Deployment (Alternative)

If GitHub Actions is down or you need to deploy without pushing, you can deploy manually via cPanel:

1. Log in to cPanel
2. Go to **Git™ Version Control**
3. Find `prehospital1` → click **Manage**
4. Click **Update from Remote** → **Deploy HEAD Commit**

---

## Setting Up on a New Machine

If you need to work from a different computer:

```bash
# Clone the repo
git clone https://github.com/solomonvuelta1985-cpu/prehospital1.git

# Install PHP dependencies
cd prehospital1
composer install

# Copy and configure local config
cp includes/config.example.php includes/config.php
# Edit config.php with your local DB settings
```

---

## Troubleshooting

### Deployment failed on GitHub Actions
1. Click the **Actions** tab on GitHub
2. Click the failed run → expand the failing step
3. Common issues:
   - **SSH connection refused** — check `SSH_HOST` secret is correct
   - **Authentication failed** — update `SSH_PASS` secret (cPanel password may have changed)
   - **composer not found** — contact hosting provider to enable Composer

### Site not updating after push
1. Check Actions tab — did the workflow run?
2. If green but site not updated — clear browser cache (Ctrl+Shift+R)
3. SSH into server manually and check:
```bash
cd ~/rescue116-link.online/prehospital
git log --oneline -5
```

### Accidentally pushed broken code
Roll back to the previous working commit:
```bash
git revert HEAD
git push
```
This creates a new commit that undoes the last one and triggers a fresh deployment.

---

## Local Development Setup

| Item | Value |
|---|---|
| Local URL | `http://localhost/prehospital/public/` |
| Local DB | `pre_hospital_db` |
| DB user | `root` |
| DB pass | *(empty)* |
| XAMPP path | `c:\xampp\htdocs\prehospital` |

The `includes/config.php` automatically detects if you're on localhost or production and switches database credentials accordingly — no changes needed when switching environments.

---

*Last updated: April 3, 2026*
