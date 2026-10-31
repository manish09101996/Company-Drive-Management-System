<?php

namespace Tests\Feature;

use App\Models\DeletedItem;
use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileAndFolderManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_user_can_create_folder(): void
    {
        $user = User::where('email', 'admin@arklehomes.com.au')->first();

        $response = $this->actingAs($user)->post('/folders', [
            'name' => 'Test Finance Folder',
            'color' => '#059669',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('folders', [
            'name' => 'Test Finance Folder',
            'created_by' => $user->id,
        ]);
    }

    public function test_user_can_upload_file_with_private_storage_and_sha256(): void
    {
        Storage::fake('private');
        $user = User::where('email', 'admin@arklehomes.com.au')->first();

        $file = UploadedFile::fake()->create('contract-2026.pdf', 1024, 'application/pdf');

        $response = $this->actingAs($user)->post('/files/upload', [
            'files' => [$file],
        ]);

        $response->assertSessionHasNoErrors();

        $dbFile = File::where('name', 'contract-2026.pdf')->first();
        $this->assertNotNull($dbFile);
        $this->assertNotEmpty($dbFile->checksum_sha256);
        $this->assertNotEmpty($dbFile->storage_path);
        $this->assertNotEquals('contract-2026.pdf', basename($dbFile->storage_path)); // Ensures randomized storage key
    }

    public function test_user_can_upload_large_file_via_chunks(): void
    {
        Storage::fake('private');
        $user = User::where('email', 'admin@arklehomes.com.au')->first();

        $chunk1 = UploadedFile::fake()->create('chunk1.part', 100);
        $chunk2 = UploadedFile::fake()->create('chunk2.part', 100);

        // Upload chunk 0 of 2
        $res1 = $this->actingAs($user)->postJson('/files/chunk-upload', [
            'chunk' => $chunk1,
            'upload_id' => 'test_upload_123',
            'chunk_index' => 0,
            'total_chunks' => 2,
            'original_filename' => 'large_project_blueprint.pdf',
        ]);
        $res1->assertStatus(200);
        $res1->assertJson(['complete' => false]);

        // Upload chunk 1 of 2 (triggers merge)
        $res2 = $this->actingAs($user)->postJson('/files/chunk-upload', [
            'chunk' => $chunk2,
            'upload_id' => 'test_upload_123',
            'chunk_index' => 1,
            'total_chunks' => 2,
            'original_filename' => 'large_project_blueprint.pdf',
        ]);
        $res2->assertStatus(200);
        $res2->assertJson(['complete' => true]);

        $this->assertDatabaseHas('files', [
            'name' => 'large_project_blueprint.pdf',
        ]);
    }

    public function test_user_can_download_file(): void
    {
        Storage::fake('private');
        $user = User::where('email', 'admin@arklehomes.com.au')->first();

        $fileContent = 'Confidential Executive Document Content';
        $storagePath = 'files/2026/10/random_hash_key.pdf';
        Storage::disk('private')->put($storagePath, $fileContent);

        $dbFile = File::create([
            'uuid' => 'test-download-uuid',
            'name' => 'Confidential.pdf',
            'storage_disk' => 'private',
            'storage_path' => $storagePath,
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => strlen($fileContent),
            'checksum_sha256' => hash('sha256', $fileContent),
            'uploaded_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->get("/files/{$dbFile->id}/download");

        $response->assertStatus(200);
        $response->assertHeader('Content-Disposition', 'attachment; filename="Confidential.pdf"');
    }

    public function test_file_deletion_moves_to_recycle_bin_and_can_be_restored(): void
    {
        $user = User::where('email', 'admin@arklehomes.com.au')->first();

        $file = File::create([
            'uuid' => 'test-recycle-uuid',
            'name' => 'DeleteMe.txt',
            'storage_disk' => 'private',
            'storage_path' => 'files/2026/10/dummy.txt',
            'mime_type' => 'text/plain',
            'extension' => 'txt',
            'size_bytes' => 10,
            'uploaded_by' => $user->id,
        ]);

        // Delete file
        $response = $this->actingAs($user)->delete("/files/{$file->id}");
        $response->assertSessionHasNoErrors();

        $this->assertSoftDeleted('files', ['id' => $file->id]);
        $this->assertDatabaseHas('deleted_items', [
            'item_type' => 'file',
            'item_id' => $file->id,
            'original_name' => 'DeleteMe.txt',
        ]);

        // Restore file
        $deletedItem = DeletedItem::where('item_id', $file->id)->first();
        $restoreResponse = $this->actingAs($user)->post("/recycle-bin/{$deletedItem->id}/restore");

        $restoreResponse->assertSessionHasNoErrors();
        $this->assertNotSoftDeleted('files', ['id' => $file->id]);
        $this->assertDatabaseMissing('deleted_items', ['id' => $deletedItem->id]);
    }

    public function test_search_finds_files_located_in_nested_subfolders(): void
    {
        $user = User::where('email', 'admin@arklehomes.com.au')->first();

        // Create nested subfolder
        $parentFolder = Folder::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Top Level ' . uniqid(),
            'parent_id' => null,
            'created_by' => $user->id,
        ]);

        $subFolder = Folder::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Subfolder ' . uniqid(),
            'parent_id' => $parentFolder->id,
            'created_by' => $user->id,
        ]);

        // Create file inside deep subfolder
        $uniqueFilename = 'Special-Contract-' . uniqid() . '.pdf';
        $file = File::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => $uniqueFilename,
            'folder_id' => $subFolder->id,
            'storage_disk' => 'private',
            'storage_path' => 'files/2026/10/dummy_contract.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => 1024,
            'uploaded_by' => $user->id,
        ]);

        // Search from root drive
        $response = $this->actingAs($user)->get("/drive?search={$uniqueFilename}");
        $response->assertStatus(200);
        $response->assertSee($uniqueFilename);
        $response->assertSee($subFolder->name);
    }

    public function test_folder_cards_display_files_directly_inline(): void
    {
        $user = User::where('email', 'admin@arklehomes.com.au')->first();

        // 1. Create a folder with 5 files
        $folderWithFiles = Folder::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Project Alpha ' . uniqid(),
            'parent_id' => null,
            'created_by' => $user->id,
        ]);

        $fileNames = [];
        for ($i = 1; $i <= 5; $i++) {
            $name = "Document-{$i}-" . uniqid() . ".pdf";
            $fileNames[] = $name;
            File::create([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'name' => $name,
                'folder_id' => $folderWithFiles->id,
                'storage_disk' => 'private',
                'storage_path' => "files/2026/10/{$name}",
                'mime_type' => 'application/pdf',
                'extension' => 'pdf',
                'size_bytes' => 2048 * $i,
                'uploaded_by' => $user->id,
            ]);
        }

        // 2. Create an empty folder
        $emptyFolder = Folder::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Empty Folder ' . uniqid(),
            'parent_id' => null,
            'created_by' => $user->id,
        ]);

        // Visit Drive
        $response = $this->actingAs($user)->get('/drive');
        $response->assertStatus(200);

        // Assert folder names are visible
        $response->assertSee($folderWithFiles->name);
        $response->assertSee($emptyFolder->name);

        // Assert all 5 files are present in the response
        foreach ($fileNames as $name) {
            $response->assertSee($name);
        }

        // Assert inline collapse and more files indicator
        $response->assertSee("collapseFolder-{$folderWithFiles->id}");
        $response->assertSee("+1 more files");

        // Assert empty folder displays empty-state text
        $response->assertSee("No files in this folder");
    }
}
