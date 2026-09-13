<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('video_production_records') && Schema::hasColumn('video_production_records', 'production_job_id')) {
            $indexes = collect(Schema::getIndexes('video_production_records'))->pluck('name');
            $hasJobForeignKey = collect(Schema::getForeignKeys('video_production_records'))
                ->contains(fn (array $foreign) => ($foreign['columns'] ?? []) === ['production_job_id']);
            Schema::table('video_production_records', function (Blueprint $table) use ($indexes, $hasJobForeignKey) {
                if ($indexes->contains('video_production_records_job_type_key_unique')) {
                    $table->dropUnique('video_production_records_job_type_key_unique');
                }
                if ($hasJobForeignKey) {
                    $table->dropForeign(['production_job_id']);
                }
                $table->dropColumn('production_job_id');
            });
        }

        if (Schema::hasTable('video_production_records')) {
            $indexes = collect(Schema::getIndexes('video_production_records'))->pluck('name');
            if (! $indexes->contains('video_production_records_created_by_type_record_key_unique')) {
                Schema::table('video_production_records', function (Blueprint $table) {
                    $table->unique(
                        ['created_by', 'type', 'record_key'],
                        'video_production_records_created_by_type_record_key_unique'
                    );
                });
            }
        }

        Schema::dropIfExists('video_production_jobs');

        if (Schema::hasTable('permissions')) {
            $permissionIds = DB::table('permissions')->whereIn('name', $this->jobPermissions())->pluck('id');
            if ($permissionIds->isNotEmpty()) {
                if (Schema::hasTable('role_has_permissions')) {
                    DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
                }
                if (Schema::hasTable('model_has_permissions')) {
                    DB::table('model_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
                }
                DB::table('permissions')->whereIn('id', $permissionIds)->delete();
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('video_production_jobs')) {
            Schema::create('video_production_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('reference')->nullable();
                $table->text('description')->nullable();
                $table->string('status', 40)->default('draft')->index();
                $table->date('start_date')->nullable()->index();
                $table->date('end_date')->nullable();
                $table->foreignId('creator_id')->nullable()->index()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->index()->constrained('users')->cascadeOnDelete();
                $table->unique(['created_by', 'reference']);
                $table->index(['created_by', 'status', 'start_date']);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('video_production_records') && ! Schema::hasColumn('video_production_records', 'production_job_id')) {
            $indexes = collect(Schema::getIndexes('video_production_records'))->pluck('name');
            Schema::table('video_production_records', function (Blueprint $table) use ($indexes) {
                if ($indexes->contains('video_production_records_created_by_type_record_key_unique')) {
                    $table->dropUnique('video_production_records_created_by_type_record_key_unique');
                }
                $table->foreignId('production_job_id')->nullable()->after('id')->constrained('video_production_jobs')->cascadeOnDelete();
                $table->unique(
                    ['created_by', 'production_job_id', 'type', 'record_key'],
                    'video_production_records_job_type_key_unique'
                );
            });
        }
    }

    private function jobPermissions(): array
    {
        return [
            'manage-any-video-production-job',
            'manage-own-video-production-job',
            'view-video-production-job',
            'create-video-production-job',
            'edit-video-production-job',
            'delete-video-production-job',
        ];
    }
};
