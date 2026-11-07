<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\File;
use App\Models\Folder;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Departments
        $departments = [
            ['name' => 'Executive Management', 'code' => 'EXEC', 'description' => 'Executive board and leadership team'],
            ['name' => 'Information Technology', 'code' => 'IT', 'description' => 'IT infrastructure, security, and systems'],
            ['name' => 'Human Resources', 'code' => 'HR', 'description' => 'Personnel, talent acquisition, and company policies'],
            ['name' => 'Finance & Accounting', 'code' => 'FIN', 'description' => 'Financial statements, payroll, and billing'],
            ['name' => 'Marketing & Sales', 'code' => 'MKT', 'description' => 'Marketing campaigns, client collateral, and sales assets'],
        ];

        $deptModels = [];
        foreach ($departments as $dept) {
            $deptModels[$dept['code']] = Department::firstOrCreate(['code' => $dept['code']], $dept);
        }

        // 2. Roles
        $roles = [
            [
                'name' => 'Super Admin',
                'slug' => 'super-admin',
                'description' => 'Full unrestricted system access, system configuration, user and storage management',
                'is_system' => true,
            ],
            [
                'name' => 'CEO',
                'slug' => 'ceo',
                'description' => 'Chief Executive Officer - Executive leadership and operational oversight',
                'is_system' => true,
            ],
            [
                'name' => 'Director',
                'slug' => 'director',
                'description' => 'Director - Executive leadership, operations, and departmental management',
                'is_system' => true,
            ],
            [
                'name' => 'Admin',
                'slug' => 'admin',
                'description' => 'Manage company files, users, imports, audit logs and standard administrative operations',
                'is_system' => true,
            ],
            [
                'name' => 'Manager',
                'slug' => 'manager',
                'description' => 'Manage departmental files, folders, team permissions, and file collaboration',
                'is_system' => true,
            ],
            [
                'name' => 'Team Member',
                'slug' => 'employee',
                'description' => 'Standard user access to view, upload, download assigned company files and folders',
                'is_system' => true,
            ],
        ];

        $roleModels = [];
        foreach ($roles as $role) {
            $roleModels[$role['slug']] = Role::updateOrCreate(['slug' => $role['slug']], $role);
        }

        // 3. Permissions
        $permissions = [
            // Files
            ['name' => 'View Files', 'slug' => 'files.view', 'module' => 'files', 'description' => 'View files and file metadata'],
            ['name' => 'Upload Files', 'slug' => 'files.upload', 'module' => 'files', 'description' => 'Upload new files to drive'],
            ['name' => 'Download Files', 'slug' => 'files.download', 'module' => 'files', 'description' => 'Download private files'],
            ['name' => 'Edit Files', 'slug' => 'files.edit', 'module' => 'files', 'description' => 'Update file metadata and upload new versions'],
            ['name' => 'Rename Files', 'slug' => 'files.rename', 'module' => 'files', 'description' => 'Rename existing files'],
            ['name' => 'Move Files', 'slug' => 'files.move', 'module' => 'files', 'description' => 'Move files to different folders'],
            ['name' => 'Delete Files', 'slug' => 'files.delete', 'module' => 'files', 'description' => 'Move files to recycle bin'],
            ['name' => 'Restore Files', 'slug' => 'files.restore', 'module' => 'files', 'description' => 'Restore files from recycle bin'],
            ['name' => 'Share Files', 'slug' => 'files.share', 'module' => 'files', 'description' => 'Manage file permissions and share with users'],

            // Folders
            ['name' => 'Create Folders', 'slug' => 'folders.create', 'module' => 'folders', 'description' => 'Create new folders'],
            ['name' => 'Rename Folders', 'slug' => 'folders.rename', 'module' => 'folders', 'description' => 'Rename existing folders'],
            ['name' => 'Move Folders', 'slug' => 'folders.move', 'module' => 'folders', 'description' => 'Move folders to different parent folders'],
            ['name' => 'Delete Folders', 'slug' => 'folders.delete', 'module' => 'folders', 'description' => 'Delete folders and their contents'],

            // Users & Roles
            ['name' => 'Manage Users', 'slug' => 'users.manage', 'module' => 'users', 'description' => 'Create, edit, deactivate, reactivate users'],
            ['name' => 'Manage Roles', 'slug' => 'roles.manage', 'module' => 'roles', 'description' => 'Configure roles and assign permissions'],

            // Imports
            ['name' => 'Manage Imports', 'slug' => 'imports.manage', 'module' => 'imports', 'description' => 'Upload, inspect, and extract Google Takeout ZIP backups'],

            // Audit & System
            ['name' => 'View Audit Logs', 'slug' => 'audit.view', 'module' => 'audit', 'description' => 'Inspect user activity, login events, and audit trail'],
            ['name' => 'Manage Settings', 'slug' => 'settings.manage', 'module' => 'settings', 'description' => 'Manage system configuration, storage thresholds, and backups'],
        ];

        $permissionModels = [];
        foreach ($permissions as $perm) {
            $permissionModels[$perm['slug']] = Permission::firstOrCreate(['slug' => $perm['slug']], $perm);
        }

        // 4. Assign permissions to roles
        // Super Admin gets all permissions
        $roleModels['super-admin']->permissions()->sync(collect($permissionModels)->pluck('id'));

        // Executive / Admin permissions (Executive leadership, operations, file management, users, imports, audit)
        $adminPermSlugs = [
            'files.view', 'files.upload', 'files.download', 'files.edit', 'files.rename', 'files.move',
            'files.delete', 'files.restore', 'files.share', 'folders.create', 'folders.rename',
            'folders.move', 'folders.delete', 'users.manage', 'imports.manage', 'audit.view'
        ];
        $execPermissionIds = collect($permissionModels)->whereIn('slug', $adminPermSlugs)->pluck('id');

        $roleModels['ceo']->permissions()->sync($execPermissionIds);
        $roleModels['director']->permissions()->sync($execPermissionIds);
        $roleModels['admin']->permissions()->sync($execPermissionIds);

        // Manager gets departmental management permissions
        $managerPermSlugs = [
            'files.view', 'files.upload', 'files.download', 'files.edit', 'files.rename', 'files.move',
            'files.delete', 'files.restore', 'files.share', 'folders.create', 'folders.rename'
        ];
        $roleModels['manager']->permissions()->sync(
            collect($permissionModels)->whereIn('slug', $managerPermSlugs)->pluck('id')
        );

        // Team Member (Employee) gets standard operational permissions
        $employeePermSlugs = [
            'files.view', 'files.upload', 'files.download', 'folders.create'
        ];
        $roleModels['employee']->permissions()->sync(
            collect($permissionModels)->whereIn('slug', $employeePermSlugs)->pluck('id')
        );

        // 5. Create Arkle Homes Team Accounts (Exclusively)
        $allowedEmails = [
            'admin@arklehomes.com.au',
            'garima@arklehomes.com.au',
            'ishan@arklehomes.com.au',
            'skmann@arklehomes.com.au',
            'sean@arklehomes.com.au',
        ];

        // Purge any placeholder or legacy demo users
        User::whereNotIn('email', $allowedEmails)->forceDelete();

        // 1. Super Admin: admin@arklehomes.com.au (Super Admin)
        $superAdmin = User::updateOrCreate(
            ['email' => 'admin@arklehomes.com.au'],
            [
                'name' => 'Arkle Homes Admin',
                'password' => Hash::make('Admin@12345'),
                'department_id' => $deptModels['EXEC']->id,
                'status' => 'active',
                'phone' => '+61 400 000 001',
                'email_verified_at' => now(),
            ]
        );
        $superAdmin->roles()->sync([$roleModels['super-admin']->id, $roleModels['admin']->id]);

        // 2. Garima: garima@arklehomes.com.au (CEO)
        $garima = User::updateOrCreate(
            ['email' => 'garima@arklehomes.com.au'],
            [
                'name' => 'Garima',
                'password' => Hash::make('Admin@12345'),
                'department_id' => $deptModels['EXEC']->id,
                'status' => 'active',
                'phone' => '+61 400 000 003',
                'email_verified_at' => now(),
            ]
        );
        $garima->roles()->sync([$roleModels['ceo']->id]);

        // 3. Ishan: ishan@arklehomes.com.au (Director)
        $ishan = User::updateOrCreate(
            ['email' => 'ishan@arklehomes.com.au'],
            [
                'name' => 'Ishan',
                'password' => Hash::make('Admin@12345'),
                'department_id' => $deptModels['EXEC']->id,
                'status' => 'active',
                'phone' => '+61 400 000 004',
                'email_verified_at' => now(),
            ]
        );
        $ishan->roles()->sync([$roleModels['director']->id]);

        // 4. SK Mann: skmann@arklehomes.com.au (Team Member)
        $skmann = User::updateOrCreate(
            ['email' => 'skmann@arklehomes.com.au'],
            [
                'name' => 'SK Mann',
                'password' => Hash::make('Admin@12345'),
                'department_id' => $deptModels['EXEC']->id,
                'status' => 'active',
                'phone' => '+61 400 000 002',
                'email_verified_at' => now(),
            ]
        );
        $skmann->roles()->sync([$roleModels['employee']->id]);

        // 5. Sean: sean@arklehomes.com.au (Team Member)
        $sean = User::updateOrCreate(
            ['email' => 'sean@arklehomes.com.au'],
            [
                'name' => 'Sean',
                'password' => Hash::make('Admin@12345'),
                'department_id' => $deptModels['MKT']->id,
                'status' => 'active',
                'phone' => '+61 400 000 005',
                'email_verified_at' => now(),
            ]
        );
        $sean->roles()->sync([$roleModels['employee']->id]);

        // 6. Create Initial Folder Hierarchy
        $rootFolders = [
            ['name' => 'Company Policies & Guidelines', 'color' => '#2563eb', 'dept' => 'HR'],
            ['name' => 'Executive & Board Minutes', 'color' => '#7c3aed', 'dept' => 'EXEC'],
            ['name' => 'Financial Reports & Audits', 'color' => '#059669', 'dept' => 'FIN'],
            ['name' => 'IT Infrastructure & Backups', 'color' => '#0891b2', 'dept' => 'IT'],
            ['name' => 'Marketing Assets & Brand Kit', 'color' => '#ea580c', 'dept' => 'MKT'],
        ];

        $folderModels = [];
        foreach ($rootFolders as $f) {
            $folder = Folder::firstOrCreate(
                ['name' => $f['name'], 'parent_id' => null],
                [
                    'uuid' => (string) Str::uuid(),
                    'department_id' => $deptModels[$f['dept']]->id,
                    'created_by' => $superAdmin->id,
                    'color' => $f['color'],
                ]
            );
            $folderModels[$f['name']] = $folder;
        }

        // Subfolders
        $subFolders = [
            ['name' => 'Employee Handbooks', 'parent' => 'Company Policies & Guidelines', 'dept' => 'HR', 'color' => '#2563eb'],
            ['name' => 'Onboarding Checklists', 'parent' => 'Company Policies & Guidelines', 'dept' => 'HR', 'color' => '#2563eb'],
            ['name' => 'FY 2026 Reports', 'parent' => 'Financial Reports & Audits', 'dept' => 'FIN', 'color' => '#059669'],
            ['name' => 'Tax Filings', 'parent' => 'Financial Reports & Audits', 'dept' => 'FIN', 'color' => '#059669'],
            ['name' => 'System Architecture', 'parent' => 'IT Infrastructure & Backups', 'dept' => 'IT', 'color' => '#0891b2'],
        ];

        foreach ($subFolders as $sf) {
            Folder::firstOrCreate(
                ['name' => $sf['name'], 'parent_id' => $folderModels[$sf['parent']]->id],
                [
                    'uuid' => (string) Str::uuid(),
                    'department_id' => $deptModels[$sf['dept']]->id,
                    'created_by' => $superAdmin->id,
                    'color' => $sf['color'],
                ]
            );
        }

        // 7. Create a sample initial company document in private storage
        $sampleContent = "# Company Security & Drive Policy\n\n1. All confidential company data is encrypted and stored in private storage.\n2. Access is governed by role-based and item-level permissions.\n3. External backups (such as Google Takeout) are verified via SHA-256 before extraction.\n4. Never share private links outside the organization.\n\nApproved by Executive Board.\n";
        $sampleFilename = 'Company-Security-Policy-2026.md';
        $storageDir = 'files/' . date('Y/m');
        $randomStorageKey = $storageDir . '/' . Str::random(40) . '.md';

        Storage::disk('private')->put($randomStorageKey, $sampleContent);
        $checksum = hash('sha256', $sampleContent);

        File::firstOrCreate(
            ['name' => $sampleFilename, 'folder_id' => $folderModels['Company Policies & Guidelines']->id],
            [
                'uuid' => (string) Str::uuid(),
                'department_id' => $deptModels['HR']->id,
                'storage_disk' => 'private',
                'storage_path' => $randomStorageKey,
                'mime_type' => 'text/markdown',
                'extension' => 'md',
                'size_bytes' => strlen($sampleContent),
                'checksum_sha256' => $checksum,
                'uploaded_by' => $superAdmin->id,
            ]
        );

        // 8. Record Initial Audit Log
        AuditLog::create([
            'user_id' => $superAdmin->id,
            'action' => 'system.initialized',
            'resource_type' => 'System',
            'resource_id' => 1,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'System Initializer',
            'details' => ['message' => 'Company Drive Management System initialized with roles, permissions, and default security configuration.'],
            'created_at' => now(),
        ]);

        // 9. Configure 5 Main User Folders and Access Assignments
        $this->call(UserFolderStructureSeeder::class);
    }
}
