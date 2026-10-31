<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('import_jobs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title');
            $table->string('source_type')->default('takeout_zip');
            $table->string('archive_filename');
            $table->string('archive_path');
            $table->unsignedInteger('total_parts')->default(1);
            $table->unsignedBigInteger('archive_size_bytes')->default(0);
            $table->foreignId('destination_folder_id')->nullable()->constrained('folders')->nullOnDelete();
            $table->string('default_access_role')->default('Employee');
            $table->enum('status', [
                'pending',
                'validating',
                'manifest_ready',
                'processing',
                'completed',
                'partially_completed',
                'failed',
                'cancelled'
            ])->default('pending')->index();
            $table->unsignedInteger('total_entries')->default(0);
            $table->unsignedInteger('total_files')->default(0);
            $table->unsignedInteger('total_folders')->default(0);
            $table->unsignedInteger('imported_entries')->default(0);
            $table->unsignedInteger('failed_entries')->default(0);
            $table->unsignedInteger('skipped_entries')->default(0);
            $table->text('error_summary')->nullable();
            $table->json('settings')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('import_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_job_id')->constrained('import_jobs')->cascadeOnDelete();
            $table->unsignedInteger('entry_index')->index();
            $table->text('entry_path');
            $table->string('entry_name');
            $table->enum('entry_type', ['folder', 'file'])->index();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('crc32', 32)->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'skipped', 'failed'])->default('pending')->index();
            $table->foreignId('destination_folder_id')->nullable()->constrained('folders')->nullOnDelete();
            $table->foreignId('folder_id')->nullable()->constrained('folders')->nullOnDelete();
            $table->foreignId('file_id')->nullable()->constrained('files')->nullOnDelete();
            $table->text('error_message')->nullable();
            $table->unsignedSmallInteger('retries')->default(0);
            $table->char('checksum_sha256', 64)->nullable();
            $table->timestamps();

            $table->index(['import_job_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_items');
        Schema::dropIfExists('import_jobs');
    }
};
