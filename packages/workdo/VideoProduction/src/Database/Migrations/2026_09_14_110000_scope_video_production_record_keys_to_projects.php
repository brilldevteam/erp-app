<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('video_production_records') || ! Schema::hasColumn('video_production_records', 'project_id')) {
            return;
        }

        $indexes = collect(Schema::getIndexes('video_production_records'))->pluck('name');
        Schema::table('video_production_records', function (Blueprint $table) use ($indexes) {
            if ($indexes->contains('video_production_records_created_by_type_record_key_unique')) {
                $table->dropUnique('video_production_records_created_by_type_record_key_unique');
            }
            if (! $indexes->contains('video_production_records_project_type_key_unique')) {
                $table->unique(['created_by', 'project_id', 'type', 'record_key'], 'video_production_records_project_type_key_unique');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('video_production_records')) {
            return;
        }

        $indexes = collect(Schema::getIndexes('video_production_records'))->pluck('name');
        Schema::table('video_production_records', function (Blueprint $table) use ($indexes) {
            if ($indexes->contains('video_production_records_project_type_key_unique')) {
                $table->dropUnique('video_production_records_project_type_key_unique');
            }
            if (! $indexes->contains('video_production_records_created_by_type_record_key_unique')) {
                $table->unique(['created_by', 'type', 'record_key'], 'video_production_records_created_by_type_record_key_unique');
            }
        });
    }
};
