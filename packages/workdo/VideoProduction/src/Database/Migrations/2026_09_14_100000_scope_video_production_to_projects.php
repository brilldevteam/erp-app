<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('video_production_records') && ! Schema::hasColumn('video_production_records', 'project_id')) {
            Schema::table('video_production_records', function (Blueprint $table) {
                $table->foreignId('project_id')->nullable()->after('id')->constrained('projects')->cascadeOnDelete();
                $table->index(['created_by', 'project_id', 'type']);
            });
        }

        if (Schema::hasTable('video_production_settings') && ! Schema::hasColumn('video_production_settings', 'project_id')) {
            $indexes = collect(Schema::getIndexes('video_production_settings'))->pluck('name');
            if (! $indexes->contains('video_production_settings_created_by_index')) {
                Schema::table('video_production_settings', function (Blueprint $table) {
                    $table->index('created_by', 'video_production_settings_created_by_index');
                });
            }
            Schema::table('video_production_settings', function (Blueprint $table) {
                $table->dropUnique(['created_by']);
                $table->foreignId('project_id')->nullable()->after('id')->constrained('projects')->cascadeOnDelete();
                $table->unique(['created_by', 'project_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('video_production_settings') && Schema::hasColumn('video_production_settings', 'project_id')) {
            DB::table('video_production_settings')->whereNotNull('project_id')->delete();
            Schema::table('video_production_settings', function (Blueprint $table) {
                $table->dropUnique(['created_by', 'project_id']);
                $table->dropConstrainedForeignId('project_id');
                $table->unique('created_by');
            });
            $indexes = collect(Schema::getIndexes('video_production_settings'))->pluck('name');
            if ($indexes->contains('video_production_settings_created_by_index')) {
                Schema::table('video_production_settings', function (Blueprint $table) {
                    $table->dropIndex('video_production_settings_created_by_index');
                });
            }
        }

        if (Schema::hasTable('video_production_records') && Schema::hasColumn('video_production_records', 'project_id')) {
            Schema::table('video_production_records', function (Blueprint $table) {
                $table->dropIndex(['created_by', 'project_id', 'type']);
                $table->dropConstrainedForeignId('project_id');
            });
        }
    }
};
