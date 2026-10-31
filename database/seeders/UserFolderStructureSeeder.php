<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UserFolderStructureSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@arklehomes.com.au')->firstOrFail();
        $skmann = User::where('email', 'skmann@arklehomes.com.au')->firstOrFail();
        $garima = User::where('email', 'garima@arklehomes.com.au')->firstOrFail();
        $ishan = User::where('email', 'ishan@arklehomes.com.au')->firstOrFail();
        $sean = User::where('email', 'sean@arklehomes.com.au')->firstOrFail();

        // 1. Create or retrieve the 5 Main Root Folders
        $mainFoldersData = [
            [
                'name' => 'Admin Folder',
                'user' => $admin,
                'color' => '#2563eb',
            ],
            [
                'name' => 'Garima Folder',
                'user' => $garima,
                'color' => '#d946ef',
            ],
            [
                'name' => 'Ishan Folder',
                'user' => $ishan,
                'color' => '#059669',
            ],
            [
                'name' => 'Sean Folder',
                'user' => $sean,
                'color' => '#ea580c',
            ],
            [
                'name' => 'SK Mann Folder',
                'user' => $skmann,
                'color' => '#0891b2',
            ],
        ];

        $mainFolders = [];
        foreach ($mainFoldersData as $data) {
            $folder = Folder::where('name', $data['name'])->whereNull('parent_id')->first();
            if (!$folder) {
                $folder = Folder::create([
                    'uuid' => (string) Str::uuid(),
                    'name' => $data['name'],
                    'parent_id' => null,
                    'department_id' => $data['user']->department_id,
                    'created_by' => $admin->id,
                    'assigned_user_id' => $data['user']->id,
                    'color' => $data['color'],
                ]);
            } else {
                $folder->update([
                    'assigned_user_id' => $data['user']->id,
                    'color' => $data['color'],
                ]);
            }
            $mainFolders[$data['name']] = $folder;
        }

        $adminFolder = $mainFolders['Admin Folder'];
        $garimaFolder = $mainFolders['Garima Folder'];
        $ishanFolder = $mainFolders['Ishan Folder'];
        $seanFolder = $mainFolders['Sean Folder'];
        $skmannFolder = $mainFolders['SK Mann Folder'];

        // 2. Move any legacy root folders into Admin Folder (so root ONLY has the 5 main folders)
        $legacyRootFolders = Folder::whereNull('parent_id')
            ->whereNotIn('id', collect($mainFolders)->pluck('id'))
            ->get();

        foreach ($legacyRootFolders as $legacy) {
            $legacy->update([
                'parent_id' => $adminFolder->id,
                'assigned_user_id' => $admin->id,
            ]);
        }

        // 3. Move matched Takeout subfolders into corresponding employee main folders
        // Garima
        $garimaFolders = Folder::where(function ($q) {
            $q->where('name', 'like', '%garima%');
        })->whereNotIn('id', collect($mainFolders)->pluck('id'))->get();

        foreach ($garimaFolders as $f) {
            // Only move top-level matching containers that were directly under Takeout root (parent 1 or admin)
            if ($f->parent_id === 1 || $f->parent_id === $adminFolder->id) {
                $f->update([
                    'parent_id' => $garimaFolder->id,
                    'assigned_user_id' => $garima->id,
                ]);
            }
        }

        // Ishan
        $ishanFolders = Folder::where(function ($q) {
            $q->where('name', 'like', '%ishan%');
        })->whereNotIn('id', collect($mainFolders)->pluck('id'))->get();

        foreach ($ishanFolders as $f) {
            if ($f->parent_id === 1 || $f->parent_id === $adminFolder->id) {
                $f->update([
                    'parent_id' => $ishanFolder->id,
                    'assigned_user_id' => $ishan->id,
                ]);
            }
        }

        // Sean
        $seanFolders = Folder::where(function ($q) {
            $q->where('name', 'like', '%sean%');
        })->whereNotIn('id', collect($mainFolders)->pluck('id'))->get();

        foreach ($seanFolders as $f) {
            if ($f->parent_id === 1 || $f->parent_id === $adminFolder->id) {
                $f->update([
                    'parent_id' => $seanFolder->id,
                    'assigned_user_id' => $sean->id,
                ]);
            }
        }

        // 4. Log the audit event
        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'folders.user_structure_initialized',
            'resource_type' => 'Folder',
            'resource_id' => $adminFolder->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'System Initializer',
            'details' => [
                'message' => 'Configured exactly 5 main folders with user assignment: Admin, Garima, Ishan, Sean, SK Mann.',
            ],
            'created_at' => now(),
        ]);
    }
}
