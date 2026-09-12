<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('video_production_settings')) {
            Schema::create('video_production_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('created_by')->unique();
                $table->unsignedSmallInteger('monthly_reel_target')->default(12);
                $table->unsignedSmallInteger('monthly_static_target')->default(12);
                $table->unsignedSmallInteger('minimum_shoots')->default(2);
                $table->unsignedSmallInteger('maximum_shoots')->default(4);
                $table->decimal('included_hours_per_shoot', 6, 2)->default(4);
                $table->unsignedSmallInteger('required_lead_days')->default(3);
                $table->unsignedSmallInteger('included_revisions')->default(3);
                $table->json('working_days')->nullable();
                $table->date('workflow_effective_date')->nullable();
                $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('video_production_jobs')) {
            Schema::create('video_production_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('reference')->nullable();
                $table->text('description')->nullable();
                $table->string('status', 40)->default('draft')->index();
                $table->date('start_date')->nullable()->index();
                $table->date('end_date')->nullable();
                $table->foreignId('creator_id')->nullable()->index();
                $table->foreignId('created_by')->index();
                $table->foreign('creator_id')->references('id')->on('users')->onDelete('set null');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
                $table->unique(['created_by', 'reference']);
                $table->index(['created_by', 'status', 'start_date']);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('video_production_jobs');
        Schema::dropIfExists('video_production_settings');
    }
};
