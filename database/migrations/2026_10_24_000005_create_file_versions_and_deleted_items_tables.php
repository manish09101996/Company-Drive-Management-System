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
        Schema::create('file_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained('files')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('storage_disk')->default('private');
            $table->string('storage_path');
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->char('checksum_sha256', 64)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('change_summary')->nullable();
            $table->timestamps();

            $table->unique(['file_id', 'version_number']);
        });

        Schema::create('deleted_items', function (Blueprint $table) {
            $table->id();
            $table->enum('item_type', ['file', 'folder']);
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('original_parent_id')->nullable();
            $table->string('original_name');
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('deleted_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['item_type', 'item_id']);
            $table->index('deleted_at');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deleted_items');
        Schema::dropIfExists('file_versions');
    }
};
