<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\ImportItem;
use App\Models\ImportJob;
use App\Models\User;
use App\Services\TakeoutImportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class TakeoutImportTest extends TestCase
{
    use DatabaseTransactions;

    protected function createSampleZip(string $filename, array $files): string
    {
        $dir = storage_path('app/private/imports/archives');
        if (!file_exists($dir)) {
            @mkdir($dir, 0755, true);
        }
        $zipPath = "{$dir}/{$filename}";

        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        return $zipPath;
    }

    public function test_archive_inspection_detects_entries_and_generates_manifest(): void
    {
        $user = User::where('email', 'admin@arklehomes.com.au')->first();
        $zipFilename = 'test_takeout_' . uniqid() . '.zip';
        $this->createSampleZip($zipFilename, [
            'Takeout/Drive/Finance/' => '',
            'Takeout/Drive/Finance/budget.xlsx' => 'Sample budget excel data',
            'Takeout/Drive/Policies/rules.pdf' => 'Sample policy pdf data',
        ]);

        $job = ImportJob::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'title' => 'Test Google Takeout',
            'archive_filename' => $zipFilename,
            'archive_path' => "imports/archives/{$zipFilename}",
            'archive_size_bytes' => 1024,
            'status' => 'pending',
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->post("/imports/{$job->id}/inspect");
        $response->assertSessionHasNoErrors();

        $freshJob = $job->fresh();
        $this->assertEquals('manifest_ready', $freshJob->status);
        $this->assertGreaterThan(0, $freshJob->total_entries);

        $this->assertDatabaseHas('import_items', [
            'import_job_id' => $job->id,
            'entry_name' => 'budget.xlsx',
            'entry_type' => 'file',
            'status' => 'pending',
        ]);
    }

    public function test_path_traversal_zip_slip_entries_are_rejected(): void
    {
        $user = User::where('email', 'admin@arklehomes.com.au')->first();
        $zipFilename = 'malicious_slip_' . uniqid() . '.zip';

        // Create archive with dangerous traversal path
        $this->createSampleZip($zipFilename, [
            '../../evil.php' => '<?php echo "Hacked"; ?>',
            'Takeout/valid.txt' => 'Valid document',
        ]);

        $job = ImportJob::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'title' => 'Malicious Zip Slip Test',
            'archive_filename' => $zipFilename,
            'archive_path' => "imports/archives/{$zipFilename}",
            'archive_size_bytes' => 1024,
            'status' => 'pending',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->post("/imports/{$job->id}/inspect");

        // Verify evil.php was NOT added to import items
        $this->assertDatabaseMissing('import_items', [
            'import_job_id' => $job->id,
            'entry_name' => 'evil.php',
        ]);

        // Verify valid file WAS accepted
        $this->assertDatabaseHas('import_items', [
            'import_job_id' => $job->id,
            'entry_name' => 'valid.txt',
        ]);
    }

    public function test_batch_processing_extracts_files_and_updates_status(): void
    {
        $user = User::where('email', 'admin@arklehomes.com.au')->first();
        $zipFilename = 'batch_extract_' . uniqid() . '.zip';
        $this->createSampleZip($zipFilename, [
            'Takeout/Drive/report.txt' => 'Monthly Performance Review Content',
        ]);

        $job = ImportJob::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'title' => 'Batch Processing Test',
            'archive_filename' => $zipFilename,
            'archive_path' => "imports/archives/{$zipFilename}",
            'archive_size_bytes' => 1024,
            'status' => 'pending',
            'created_by' => $user->id,
        ]);

        $importService = app(TakeoutImportService::class);
        $importService->inspectArchive($job);

        // Process batch via service or endpoint
        $response = $this->actingAs($user)->postJson("/imports/{$job->id}/batch", [
            'batch_size' => 10,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('import_items', [
            'import_job_id' => $job->id,
            'entry_name' => 'report.txt',
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('files', [
            'name' => 'report.txt',
        ]);
    }

    public function test_user_can_initiate_extraction_from_existing_drive_zip_file(): void
    {
        $user = User::where('email', 'admin@arklehomes.com.au')->first();
        $zipFilename = 'drive_sample_' . uniqid() . '.zip';
        $fullPath = $this->createSampleZip($zipFilename, [
            'documents/contract.pdf' => 'Sample Contract Content',
        ]);

        $file = File::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'archive_' . uniqid() . '.zip',
            'folder_id' => null,
            'storage_disk' => 'private',
            'storage_path' => "imports/archives/{$zipFilename}",
            'mime_type' => 'application/zip',
            'extension' => 'zip',
            'size_bytes' => filesize($fullPath),
            'checksum_sha256' => hash_file('sha256', $fullPath),
            'uploaded_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->post("/files/{$file->id}/extract");
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('import_jobs', [
            'archive_path' => $file->storage_path,
            'status' => 'manifest_ready',
        ]);
    }

    public function test_user_can_select_and_deselect_items_in_manifest(): void
    {
        $user = User::where('email', 'admin@arklehomes.com.au')->first();
        $zipFilename = 'selection_test_' . uniqid() . '.zip';
        $this->createSampleZip($zipFilename, [
            'file1.txt' => 'one',
            'file2.txt' => 'two',
        ]);

        $job = ImportJob::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'title' => 'Selection Test',
            'archive_filename' => $zipFilename,
            'archive_path' => "imports/archives/{$zipFilename}",
            'archive_size_bytes' => 1024,
            'status' => 'pending',
            'created_by' => $user->id,
        ]);

        $importService = app(TakeoutImportService::class);
        $importService->inspectArchive($job);

        // Test Deselect All
        $response = $this->actingAs($user)->postJson("/imports/{$job->id}/selection", [
            'action' => 'deselect_all',
        ]);
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'pending' => 0,
        ]);

        $this->assertEquals(0, ImportItem::where('import_job_id', $job->id)->where('status', 'pending')->count());

        // Test Select All
        $response2 = $this->actingAs($user)->postJson("/imports/{$job->id}/selection", [
            'action' => 'select_all',
        ]);
        $response2->assertStatus(200);
        $this->assertGreaterThan(0, ImportItem::where('import_job_id', $job->id)->where('status', 'pending')->count());
    }

    public function test_batch_processing_handles_duplicate_skipping(): void
    {
        $user = User::where('email', 'admin@arklehomes.com.au')->first();
        $zipFilename = 'dup_test_' . uniqid() . '.zip';
        $this->createSampleZip($zipFilename, [
            'duplicate_doc.txt' => 'New Duplicate Content',
        ]);

        // Pre-create existing file in Root Drive
        File::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'duplicate_doc.txt',
            'folder_id' => null,
            'storage_disk' => 'private',
            'storage_path' => 'files/pre_existing.txt',
            'mime_type' => 'text/plain',
            'extension' => 'txt',
            'size_bytes' => 100,
            'checksum_sha256' => hash('sha256', 'existing'),
            'uploaded_by' => $user->id,
        ]);

        $job = ImportJob::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'title' => 'Duplicate Handling Test',
            'archive_filename' => $zipFilename,
            'archive_path' => "imports/archives/{$zipFilename}",
            'archive_size_bytes' => 1024,
            'status' => 'pending',
            'created_by' => $user->id,
            'settings' => [
                'duplicate_action' => 'skip',
            ],
        ]);

        $importService = app(TakeoutImportService::class);
        $importService->inspectArchive($job);

        // Process batch
        $response = $this->actingAs($user)->postJson("/imports/{$job->id}/batch", [
            'batch_size' => 10,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('import_items', [
            'import_job_id' => $job->id,
            'entry_name' => 'duplicate_doc.txt',
            'status' => 'skipped',
        ]);
    }

    public function test_imports_index_displays_50gb_storage_capacity(): void
    {
        $user = User::where('email', 'admin@arklehomes.com.au')->first();

        $response = $this->actingAs($user)->get('/imports');
        $response->assertStatus(200);
        $response->assertSee('50 GB');
        $response->assertSee('FREE STORAGE');
        $response->assertSee('TOTAL PLAN');
    }
}
