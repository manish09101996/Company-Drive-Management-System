<?php

namespace App\Console\Commands;

use App\Models\ImportJob;
use App\Services\TakeoutImportService;
use Illuminate\Console\Command;

class ProcessTakeoutImportCommand extends Command
{
    protected $signature = 'drive:import-process {uuid? : The UUID of the import job to process} {--batch=100 : Number of entries per batch}';
    protected $description = 'Process pending or ongoing Google Takeout ZIP import jobs in batches';

    public function handle(TakeoutImportService $importService): int
    {
        $uuid = $this->argument('uuid');
        $batchSize = (int) $this->option('batch');

        $query = ImportJob::query();
        if ($uuid) {
            $query->where('uuid', $uuid);
        } else {
            $query->whereIn('status', ['manifest_ready', 'processing'])->orderBy('id', 'asc');
        }

        $job = $query->first();

        if (!$job) {
            $this->info("No pending import jobs to process.");
            return 0;
        }

        $this->info("Processing Import Job: [{$job->uuid}] {$job->title} ({$job->archive_filename})");

        while (true) {
            $result = $importService->processBatch($job, $batchSize);

            $this->line("Batch: +{$result['imported_in_batch']} imported, +{$result['failed_in_batch']} failed. Remaining: {$result['remaining']}. Progress: {$result['progress_percent']}%");

            if ($result['remaining'] === 0 || in_array($result['status'], ['completed', 'partially_completed', 'failed', 'cancelled'])) {
                $this->info("Job finished with status: {$result['status']}");
                break;
            }

            // Short pause to prevent CPU throttling on shared hosting
            usleep(100000); // 0.1s
        }

        return 0;
    }
}
