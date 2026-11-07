# Hostinger Deployment Guide: Company Drive Management System

This document outlines the step-by-step procedure for deploying the **Company Drive Management System** on **Hostinger Web / Shared Hosting** (or Cloud Hosting / VPS).

---

## 1. Hosting Architecture & Directory Separation

To ensure private company files and environment secrets (`.env`) are never accessible over the public web, structure your files on Hostinger as follows:

```text
/home/u123456789/
│
├── company_drive/                 <-- Laravel application root (OUTSIDE public_html)
│   ├── app/
│   ├── bootstrap/
│   ├── config/
│   ├── database/
│   ├── routes/
│   ├── storage/
│   │   └── app/
│   │       └── private/           <-- Private files & Takeout archives (Strictly Protected)
│   │           ├── files/
│   │           └── imports/
│   ├── vendor/
│   └── .env                       <-- Never exposed to web
│
└── public_html/                   <-- Public Document Root
    ├── index.php                  <-- Pointed to ../company_drive/public/index.php
    ├── .htaccess                  <-- HTTPS enforcement & security headers
    └── favicon.ico
```

### Setting the Document Root in Hostinger hPanel:
1. Log in to **Hostinger hPanel**.
2. Go to **Websites** &rarr; Select your Domain.
3. Look for **Document Root** or navigate to **Advanced** &rarr; **File Manager**.
4. Set the document root directly to `/home/u123456789/company_drive/public` (recommended), **OR** point `public_html/index.php` to require `../company_drive/vendor/autoload.php` and `../company_drive/bootstrap/app.php`.

---

## 2. PHP Version & Required Extensions

In Hostinger hPanel, navigate to **Advanced** &rarr; **PHP Configuration**:
- **PHP Version**: Select **PHP 8.2** or **PHP 8.3**.
- **Required PHP Extensions** (ensure checked in PHP Extensions tab):
  - `zip` (for Google Takeout ZIP extraction and manifest generation)
  - `pdo_mysql` & `mysqli` (database connectivity)
  - `fileinfo` (MIME type verification)
  - `mbstring`
  - `openssl`
  - `curl`
  - `gd` or `imagick` (image preview handling)
  - `exif`
- **Recommended PHP Options**:
  - `memory_limit`: **512M** (or highest available on your plan)
  - `max_execution_time`: **120** or **300** seconds
  - `upload_max_filesize`: **128M**
  - `post_max_size`: **128M**

> **Note on 10–50 GB Archives:** Thanks to our built-in **Resumable Chunked Uploader** (4 MB chunks) and **AJAX Batch Processor** (50 items/step), the system will successfully upload and extract multi-gigabyte Takeout archives even on modest shared hosting plans without tripping `upload_max_filesize` or `max_execution_time` limits!

---

## 3. Database Setup (MySQL)

1. In hPanel, go to **Databases** &rarr; **Management**.
2. Create a new MySQL database:
   - Database Name: `u123456789_drive`
   - Username: `u123456789_drive_user`
   - Password: `YourStrongPassword123!`
3. In SSH or using **File Manager**, configure `company_drive/.env`:

```env
APP_NAME="Company Drive"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourcompanydrive.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=u123456789_drive
DB_USERNAME=u123456789_drive_user
DB_PASSWORD="YourStrongPassword123!"

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true

FILESYSTEM_DISK=private
QUEUE_CONNECTION=database
```

4. Run migrations and initial seeding via SSH (or Terminal in hPanel):

```bash
php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 4. Default Seeded Credentials

When `db:seed` is executed, the following default accounts are provisioned:

| Role / Designation | Name | Email | Default Password | Access Level |
| :--- | :--- | :--- | :--- | :--- |
| **Super Admin** | Arkle Homes Admin | `admin@arklehomes.com.au` | `Admin@12345` | Unrestricted full system, user, and storage control |
| **CEO** | Garima | `garima@arklehomes.com.au` | `Admin@12345` | Chief Executive Officer - Executive leadership & drive control |
| **Director** | Ishan | `ishan@arklehomes.com.au` | `Admin@12345` | Director - Operational management & drive oversight |
| **Team Member** | SK Mann | `skmann@arklehomes.com.au` | `Admin@12345` | Dedicated workspace & company file collaboration |
| **Team Member** | Sean | `sean@arklehomes.com.au` | `Admin@12345` | Dedicated workspace & company file collaboration |

> **IMPORTANT**: Log in immediately upon deployment and update the Super Admin password via **Profile** &rarr; **Change Password**.

---

## 5. Scheduled Batch Processing (Hostinger Cron Jobs)

Hostinger Shared Hosting does not support always-on supervisor daemons, but it supports **Cron Jobs**.

In hPanel, navigate to **Advanced** &rarr; **Cron Jobs**, and add the following two jobs:

### Job 1: Laravel Scheduler (Every Minute)
Runs scheduled tasks, cleans expired sessions, and checks jobs:
```bash
* * * * * cd /home/u123456789/company_drive && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

### Job 2: Background Takeout Import Worker (Every 5 Minutes)
Automatically processes any pending or ongoing Google Takeout ZIP extraction in 100-file batches:
```bash
*/5 * * * * cd /home/u123456789/company_drive && /usr/bin/php artisan drive:import-process --batch=100 >> /home/u123456789/company_drive/storage/logs/cron_import.log 2>&1
```

### Job 3: Automated Recycle Bin 30-Day Purge (Daily at 2:00 AM)
```bash
0 2 * * * cd /home/u123456789/company_drive && /usr/bin/php artisan drive:purge-recycle-bin --days=30 >> /dev/null 2>&1
```

---

## 6. Security Hardening on Hostinger

### 1. SSL / HTTPS Enforcement
In hPanel, enable **Force HTTPS** under **Security** &rarr; **SSL**.
Ensure `SESSION_SECURE_COOKIE=true` is set in `.env`.

### 2. Protect Storage & Private Directories (`.htaccess`)
Ensure `storage/app/private` has an `.htaccess` preventing direct web access:
```apache
<IfModule authz_core_module>
    Require all denied
</IfModule>
<IfModule !authz_core_module>
    Deny from all
</IfModule>
```

### 3. Public Web Security Headers
In `public_html/.htaccess`:
```apache
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>
```

---

## 7. Resource & Inode Monitoring on Shared Hosting

Hostinger shared hosting plans enforce:
1. **Inode Limits**: Usually between 200,000 and 600,000 files.
   - An import with 50,000 files will consume ~50,000 inodes. Check current inode count in hPanel &rarr; **Account Details**.
2. **Disk Space**: Monitor storage on the dashboard.
   - The importer automatically checks free disk space and warns the administrator if the uncompressed archive exceeds safe storage limits.
3. **Temporary File Cleanup**:
   - The chunked uploader cleans up temporary chunk parts immediately after reassembly.

---

## 8. Hybrid VPS / Temporary Processing Option

If your shared hosting plan reaches inode limits or has insufficient disk space for a 50 GB archive:
1. Spin up a temporary $4–$6/month Hostinger VPS with 100 GB NVMe storage.
2. Clone the codebase and configure `.env` pointing to the production MySQL database (or import locally).
3. Run the CLI worker:
   ```bash
   php artisan drive:import-process
   ```
4. Once extraction is complete, the metadata exists in MySQL and files are safely stored in private storage (which can also be configured to point to an S3-compatible private object store by changing `FILESYSTEM_DISK=s3`).
