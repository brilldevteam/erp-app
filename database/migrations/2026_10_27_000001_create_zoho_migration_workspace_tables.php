<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zoho_migration_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('zoho_organization_id')->nullable();
            $table->string('status', 40)->default('draft');
            $table->string('accounting_mode', 30);
            $table->char('base_currency', 3);
            $table->string('source_date_format', 30);
            $table->timestamp('inventory_cutover_at');
            $table->foreignId('default_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->string('duplicate_strategy', 20)->default('block');
            $table->boolean('ai_enabled')->default(false);
            $table->timestamp('configuration_locked_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'created_at'], 'zoho_batches_tenant_status_index');
        });

        Schema::create('zoho_migration_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('zoho_migration_batches')->cascadeOnDelete();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('original_filename');
            $table->string('disk', 30)->default('local');
            $table->string('path');
            $table->char('sha256', 64);
            $table->unsignedBigInteger('size_bytes');
            $table->string('mime_type')->nullable();
            $table->string('status', 30)->default('uploaded');
            $table->string('dataset_type', 80)->nullable();
            $table->string('profile_key', 120)->nullable();
            $table->unsignedTinyInteger('detection_confidence')->nullable();
            $table->json('headers')->nullable();
            $table->unsignedInteger('row_count')->default(0);
            $table->text('failure_message')->nullable();
            $table->timestamps();

            $table->unique(['batch_id', 'sha256']);
            $table->index(['batch_id', 'dataset_type', 'status'], 'zoho_files_batch_dataset_index');
        });

        Schema::create('zoho_migration_staged_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('zoho_migration_batches')->cascadeOnDelete();
            $table->foreignId('file_id')->constrained('zoho_migration_files')->cascadeOnDelete();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('sheet_name')->nullable();
            $table->unsignedInteger('source_row_number');
            $table->string('source_external_id')->nullable();
            $table->json('original_values');
            $table->json('normalized_values')->nullable();
            $table->string('status', 30)->default('staged');
            $table->json('validation_errors')->nullable();
            $table->timestamps();

            $table->unique(['file_id', 'sheet_name', 'source_row_number'], 'zoho_rows_source_unique');
            $table->index(['batch_id', 'status'], 'zoho_rows_batch_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zoho_migration_staged_rows');
        Schema::dropIfExists('zoho_migration_files');
        Schema::dropIfExists('zoho_migration_batches');
    }
};
