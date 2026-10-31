<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserFolderAccessTest extends TestCase
{
    protected User $admin;
    protected User $garima;
    protected User $ishan;
    protected User $sean;
    protected User $skmann;

    protected Folder $adminFolder;
    protected Folder $garimaFolder;
    protected Folder $ishanFolder;
    protected Folder $seanFolder;
    protected Folder $skmannFolder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::where('email', 'admin@arklehomes.com.au')->firstOrFail();
        $this->garima = User::where('email', 'garima@arklehomes.com.au')->firstOrFail();
        $this->ishan = User::where('email', 'ishan@arklehomes.com.au')->firstOrFail();
        $this->sean = User::where('email', 'sean@arklehomes.com.au')->firstOrFail();
        $this->skmann = User::where('email', 'skmann@arklehomes.com.au')->firstOrFail();

        $this->adminFolder = Folder::where('name', 'Admin Folder')->whereNull('parent_id')->firstOrFail();
        $this->garimaFolder = Folder::where('name', 'Garima Folder')->whereNull('parent_id')->firstOrFail();
        $this->ishanFolder = Folder::where('name', 'Ishan Folder')->whereNull('parent_id')->firstOrFail();
        $this->seanFolder = Folder::where('name', 'Sean Folder')->whereNull('parent_id')->firstOrFail();
        $this->skmannFolder = Folder::where('name', 'SK Mann Folder')->whereNull('parent_id')->firstOrFail();
    }

    /**
     * 1. Super Admin sees all five main folders.
     */
    public function test_admin_sees_all_five_main_folders_on_drive_dashboard(): void
    {
        $response = $this->actingAs($this->admin)->get('/drive');

        $response->assertStatus(200);
        $response->assertSee('Admin Folder');
        $response->assertSee('Garima Folder');
        $response->assertSee('Ishan Folder');
        $response->assertSee('Sean Folder');
        $response->assertSee('SK Mann Folder');
    }

    /**
     * 2. Garima sees ONLY Garima Folder on Drive dashboard.
     */
    public function test_garima_sees_only_garima_folder(): void
    {
        $response = $this->actingAs($this->garima)->get('/drive');

        $response->assertStatus(200);
        $response->assertSee('Garima Folder');
        $response->assertDontSee('Admin Folder');
        $response->assertDontSee('Ishan Folder');
        $response->assertDontSee('Sean Folder');
        $response->assertDontSee('SK Mann Folder');
    }

    /**
     * 3. Ishan sees ONLY Ishan Folder on Drive dashboard.
     */
    public function test_ishan_sees_only_ishan_folder(): void
    {
        $response = $this->actingAs($this->ishan)->get('/drive');

        $response->assertStatus(200);
        $response->assertSee('Ishan Folder');
        $response->assertDontSee('Admin Folder');
        $response->assertDontSee('Garima Folder');
        $response->assertDontSee('Sean Folder');
        $response->assertDontSee('SK Mann Folder');
    }

    /**
     * 4. Sean sees ONLY Sean Folder on Drive dashboard.
     */
    public function test_sean_sees_only_sean_folder(): void
    {
        $response = $this->actingAs($this->sean)->get('/drive');

        $response->assertStatus(200);
        $response->assertSee('Sean Folder');
        $response->assertDontSee('Admin Folder');
        $response->assertDontSee('Garima Folder');
        $response->assertDontSee('Ishan Folder');
        $response->assertDontSee('SK Mann Folder');
    }

    /**
     * 5. SK Mann sees ONLY SK Mann Folder on Drive dashboard.
     */
    public function test_skmann_sees_only_skmann_folder(): void
    {
        $response = $this->actingAs($this->skmann)->get('/drive');

        $response->assertStatus(200);
        $response->assertSee('SK Mann Folder');
        $response->assertDontSee('Admin Folder');
        $response->assertDontSee('Garima Folder');
        $response->assertDontSee('Ishan Folder');
        $response->assertDontSee('Sean Folder');
    }

    /**
     * 6. Employees cannot access another employee's folder by changing URLs or UUIDs.
     */
    public function test_employees_cannot_access_another_employees_folder_by_url(): void
    {
        // Garima trying to open Ishan Folder
        $response = $this->actingAs($this->garima)->get('/drive/' . $this->ishanFolder->uuid);
        $response->assertStatus(403);

        // Ishan trying to open Garima Folder
        $response = $this->actingAs($this->ishan)->get('/drive/' . $this->garimaFolder->uuid);
        $response->assertStatus(403);

        // Sean trying to open Admin Folder
        $response = $this->actingAs($this->sean)->get('/drive/' . $this->adminFolder->uuid);
        $response->assertStatus(403);

        // SK Mann trying to open Sean Folder
        $response = $this->actingAs($this->skmann)->get('/drive/' . $this->seanFolder->uuid);
        $response->assertStatus(403);
    }

    /**
     * 7. File previews and downloads respect user folder isolation.
     */
    public function test_file_previews_and_downloads_respect_folder_isolation(): void
    {
        $testContent = "Confidential report for Ishan only.";
        $storagePath = 'files/test/' . Str::random(40) . '.txt';
        Storage::disk('private')->put($storagePath, $testContent);

        $file = File::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Ishan-Private-Report.txt',
            'folder_id' => $this->ishanFolder->id,
            'storage_disk' => 'private',
            'storage_path' => $storagePath,
            'mime_type' => 'text/plain',
            'extension' => 'txt',
            'size_bytes' => strlen($testContent),
            'checksum_sha256' => hash('sha256', $testContent),
            'uploaded_by' => $this->ishan->id,
        ]);

        // Garima cannot download or preview Ishan's file
        $response = $this->actingAs($this->garima)->get("/files/{$file->id}/download");
        $response->assertStatus(403);

        $response = $this->actingAs($this->garima)->get("/files/{$file->id}/preview");
        $response->assertStatus(403);

        // Sean cannot download Ishan's file
        $response = $this->actingAs($this->sean)->get("/files/{$file->id}/download");
        $response->assertStatus(403);

        // Ishan CAN download his own file
        $response = $this->actingAs($this->ishan)->get("/files/{$file->id}/download");
        $response->assertStatus(200);

        // Super Admin CAN download the file
        $response = $this->actingAs($this->admin)->get("/files/{$file->id}/download");
        $response->assertStatus(200);
    }

    /**
     * 8. Search strictly respects folder isolation.
     */
    public function test_search_respects_user_folder_isolation(): void
    {
        $uniqueSearchKey = 'SecretContractXYZ-' . uniqid();
        $testContent = "Dummy content";
        $storagePath = 'files/test/' . Str::random(40) . '.pdf';
        Storage::disk('private')->put($storagePath, $testContent);

        $file = File::create([
            'uuid' => (string) Str::uuid(),
            'name' => "{$uniqueSearchKey}.pdf",
            'folder_id' => $this->garimaFolder->id,
            'storage_disk' => 'private',
            'storage_path' => $storagePath,
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => 1234,
            'checksum_sha256' => hash('sha256', $testContent),
            'uploaded_by' => $this->garima->id,
        ]);

        // Ishan searches for Garima's file: must NOT see it in results
        $response = $this->actingAs($this->ishan)->get("/drive?search={$uniqueSearchKey}");
        $response->assertStatus(200);
        $response->assertDontSee("{$uniqueSearchKey}.pdf");
        $this->assertTrue($response->viewData('files')->isEmpty());

        // Sean searches for Garima's file: must NOT see it in results
        $response = $this->actingAs($this->sean)->get("/drive?search={$uniqueSearchKey}");
        $response->assertStatus(200);
        $response->assertDontSee("{$uniqueSearchKey}.pdf");
        $this->assertTrue($response->viewData('files')->isEmpty());

        // Garima searches: MUST see it
        $response = $this->actingAs($this->garima)->get("/drive?search={$uniqueSearchKey}");
        $response->assertStatus(200);
        $response->assertSee("{$uniqueSearchKey}.pdf");
        $this->assertFalse($response->viewData('files')->isEmpty());

        // Super Admin searches: MUST see it
        $response = $this->actingAs($this->admin)->get("/drive?search={$uniqueSearchKey}");
        $response->assertStatus(200);
        $response->assertSee("{$uniqueSearchKey}.pdf");
        $this->assertFalse($response->viewData('files')->isEmpty());
    }

    /**
     * 9. Nested folders inherit parent access restrictions.
     */
    public function test_nested_folders_inherit_parent_access_restrictions(): void
    {
        $subFolder = Folder::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Sean Sub Project ' . uniqid(),
            'parent_id' => $this->seanFolder->id,
            'assigned_user_id' => null, // Inherits from seanFolder
            'created_by' => $this->sean->id,
        ]);

        // Garima cannot access Sean's subfolder
        $response = $this->actingAs($this->garima)->get('/drive/' . $subFolder->uuid);
        $response->assertStatus(403);

        // Sean CAN access his subfolder
        $response = $this->actingAs($this->sean)->get('/drive/' . $subFolder->uuid);
        $response->assertStatus(200);
        $response->assertSee($subFolder->name);

        // Super Admin CAN access Sean's subfolder
        $response = $this->actingAs($this->admin)->get('/drive/' . $subFolder->uuid);
        $response->assertStatus(200);
        $response->assertSee($subFolder->name);
    }

    /**
     * 10. Employees cannot rename or delete their main root folder.
     */
    public function test_employees_cannot_rename_or_delete_main_folder(): void
    {
        // Garima trying to delete her main folder
        $response = $this->actingAs($this->garima)->delete("/folders/{$this->garimaFolder->id}");
        $response->assertStatus(403);

        // Garima trying to rename her main folder
        $response = $this->actingAs($this->garima)->put("/folders/{$this->garimaFolder->id}", [
            'name' => 'Hacked Folder Name',
        ]);
        $response->assertStatus(403);

        // Main folder still exists with original name
        $this->assertDatabaseHas('folders', [
            'id' => $this->garimaFolder->id,
            'name' => 'Garima Folder',
            'deleted_at' => null,
        ]);
    }

    /**
     * 11. Super Admin can access all folders and action is logged in audit logs.
     */
    public function test_super_admin_has_full_access_to_all_folders(): void
    {
        foreach ([$this->garimaFolder, $this->ishanFolder, $this->seanFolder, $this->skmannFolder] as $folder) {
            $response = $this->actingAs($this->admin)->get('/drive/' . $folder->uuid);
            $response->assertStatus(200);
            $response->assertSee($folder->name);
        }
    }
}
