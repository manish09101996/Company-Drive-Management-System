# Company Drive Management System

A secure, enterprise-grade, centralized web application built with **Laravel 12**, **PHP 8.2+**, **MySQL**, and **Bootstrap 5**, optimized for **Hostinger Web / Shared Hosting** and standalone servers.

---

## Key Features

### 1. Centralized Admin Dashboard
- Live counters for total files, folders, users, active/inactive staff, and storage consumed.
- Visual file category breakdown bar (Documents, Images, Media, Archives, Other).
- Server disk capacity monitor with threshold warnings.
- Real-time audit trail and recent uploads table.
- Quick action shortcuts for folder creation, file uploads, user onboarding, and Google Takeout imports.

### 2. Role-Based & Item-Level Access Control (RBAC)
- **Roles**:
  - `Super Admin`: Unrestricted control, protected from unauthorized deletion or demotion.
  - `Admin`: Manage drive files, folders, users, imports, and audit logs.
  - `Manager`: Department-level management, folder creation, file editing, and sharing.
  - `Employee`: Standard company drive access to view, upload, and download assigned assets.
- **Granular Permissions**:
  - Folder & file level permissions: `view`, `upload`, `download`, `edit`, `rename`, `move`, `delete`, `restore`, `share`.
  - Hierarchical inheritance: Subfolders inherit ancestor permission structures automatically.
  - Department matching: Files and folders can be scoped to specific business departments (`EXEC`, `IT`, `HR`, `FIN`, `MKT`).

### 3. Private File Manager
- **Zero Public Access**: File contents reside outside public web document root in `storage/app/private/files/`.
- **Randomized Storage Keys**: Physical paths are non-guessable random UUIDs (e.g. `files/2026/10/a8f7c9e1-....bin`).
- **Cryptographic Verification**: Every file generates an SHA-256 checksum on upload and stores it in the database.
- **Streaming & HTTP Range Support**: Stream downloads with chunked output buffers and `Range` header support for seamless audio/video seeking and large PDF previewing.
- **Version History**: Keep multiple versions of documents, view previous checksums, and revert to previous versions at any time.
- **Recycle Bin**: 30-day retention window, restoration to original folders, and permanent purge options.

### 4. Google Takeout ZIP Import Center
Designed specifically to handle **10–50 GB Google Takeout exports** safely on Hostinger Shared Hosting:
- **Resumable Chunked Uploader**: Files are uploaded in 4 MB chunks via JavaScript, preventing PHP upload timeouts.
- **Zip Slip & Path Traversal Protection**: Blocks any entries containing `../`, `..\`, absolute paths, or suspicious symlinks.
- **Decompression Bomb Detection**: Rejects archives exceeding safe compression ratios (> 100:1).
- **Disk Capacity Verification**: Evaluates uncompressed size against free server space before extraction.
- **Manifest Preview**: Scans the archive and presents a folder tree preview with file counts and sizes.
- **Controlled Batch Extraction**: Extracts files in controlled 50-item batches via an in-browser AJAX runner or via CLI (`php artisan drive:import-process`).
- **Idempotency & Retry**: Failed items can be retried without re-importing already completed files.

### 5. Audit Logging & Security
- Tracks logins, failed attempts, and rate-limiting triggers with IP and browser agent.
- Logs file uploads, downloads, renames, deletions, restorations, and permission grants.
- CSRF protection, secure sessions in MySQL, password hashing (Bcrypt), and protection against privilege escalation.

---

## Database Architecture

| Table | Purpose |
| :--- | :--- |
| `users` | User credentials, department, phone, active/inactive status |
| `roles` | Role definitions (`super-admin`, `admin`, `manager`, `employee`) |
| `permissions` | 18 system-level granular permissions |
| `user_roles` | Many-to-many relationship mapping users to roles |
| `role_permissions` | Many-to-many relationship mapping roles to permissions |
| `departments` | Organizational departments (`EXEC`, `IT`, `HR`, `FIN`, `MKT`) |
| `folders` | Recursive folder hierarchy with department and color coding |
| `files` | File metadata, private storage path, SHA-256 checksum, size, MIME |
| `folder_user_permissions` | Direct folder-level user overrides |
| `file_user_permissions` | Direct file-level user overrides |
| `file_versions` | Historical revisions with previous storage paths and checksums |
| `deleted_items` | Recycle bin tracking original parent folders and retention expiration |
| `import_jobs` | Google Takeout archive tracking, status, counts, and settings |
| `import_items` | Individual entries in Takeout archives with extraction status |
| `audit_logs` | Immutable audit trail with JSON contextual metadata |
| `login_events` | Authentication event monitoring with failure reasons and IP |

---

## Getting Started Locally (XAMPP / Localhost)

1. **Clone or navigate to the repository**:
   ```bash
   cd "C:\xampp\htdocs\Company Drive Management System"
   ```

2. **Configure Environment (`.env`)**:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=company_drive
   DB_USERNAME=root
   DB_PASSWORD=
   FILESYSTEM_DISK=private
   ```

3. **Install Dependencies & Run Migrations**:
   ```bash
   composer install
   php artisan migrate
   php artisan db:seed
   ```

4. **Start the Laravel Server**:
   ```bash
   php artisan serve
   ```
   Visit: `http://localhost:8000`

---

## Seeded Arkle Homes Accounts

| Name | Role | Email | Default Password |
| :--- | :--- | :--- | :--- |
| **Arkle Homes Admin** | **Super Admin & Admin** | `admin@arklehomes.com.au` | `Admin@12345` |
| **SK Mann** | **Admin** | `skmann@arklehomes.com.au` | `Admin@12345` |
| **Garima** | **Manager** | `garima@arklehomes.com.au` | `Admin@12345` |
| **Ishan** | **Employee** | `ishan@arklehomes.com.au` | `Admin@12345` |
| **Sean** | **Employee** | `sean@arklehomes.com.au` | `Admin@12345` |

---

## Testing

Run the full PHPUnit test suite:
```bash
php artisan test
```

All 18 feature and unit tests cover:
- Authentication & rate limiting
- File upload, SHA-256 hashing, private streaming, and Range header playback
- Soft delete & recycle bin restore
- Google Takeout archive inspection, zip slip defense, and batch extraction
- Super Admin account protection and role escalation defense
