<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zoho_migration_batches', function (Blueprint $table) {
            $table->string('workflow_mode', 20)->default('full')->after('status');
        });

        Schema::table('zoho_migration_files', function (Blueprint $table) {
            $table->string('stage_key', 80)->nullable()->after('tenant_id');
            $table->index(['batch_id', 'stage_key', 'status'], 'zoho_files_batch_stage_index');
        });

        Schema::create('zoho_migration_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('zoho_migration_batches')->cascadeOnDelete();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('stage_key', 80);
            $table->unsignedTinyInteger('position');
            $table->string('status', 30)->default('not_started');
            $table->unsignedInteger('accepted_count')->default(0);
            $table->unsignedInteger('rejected_count')->default(0);
            $table->unsignedInteger('warning_count')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('summary')->nullable();
            $table->timestamps();

            $table->unique(['batch_id', 'stage_key']);
            $table->index(['batch_id', 'position']);
        });

        Schema::create('zoho_migration_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('zoho_migration_batches')->cascadeOnDelete();
            $table->foreignId('file_id')->nullable()->constrained('zoho_migration_files')->cascadeOnDelete();
            $table->foreignId('staged_row_id')->nullable()->constrained('zoho_migration_staged_rows')->cascadeOnDelete();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('stage_key', 80);
            $table->string('type', 50);
            $table->string('severity', 20)->default('error');
            $table->string('field')->nullable();
            $table->string('source_value')->nullable();
            $table->text('message');
            $table->json('suggestions')->nullable();
            $table->string('resolution_url')->nullable();
            $table->string('status', 20)->default('open');
            $table->timestamps();
            $table->index(['batch_id', 'stage_key', 'status']);
        });

        Schema::create('zoho_migration_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('zoho_migration_batches')->cascadeOnDelete();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('stage_key', 80)->nullable();
            $table->string('event_type', 60);
            $table->text('message');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['batch_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zoho_migration_events');
        Schema::dropIfExists('zoho_migration_issues');
        Schema::dropIfExists('zoho_migration_stages');
        Schema::table('zoho_migration_files', function (Blueprint $table) {
            $table->dropIndex('zoho_files_batch_stage_index');
            $table->dropColumn('stage_key');
        });
        Schema::table('zoho_migration_batches', function (Blueprint $table) {
            $table->dropColumn('workflow_mode');
        });
    }
};
