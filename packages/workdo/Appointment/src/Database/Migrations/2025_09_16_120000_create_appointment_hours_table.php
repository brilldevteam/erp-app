<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('appointment_hours')) {
            Schema::create('appointment_hours', function (Blueprint $table) {
                $table->id();
                $table->string('day_name');
                $table->time('start_time')->nullable();
                $table->time('end_time')->nullable();
                $table->boolean('day_off')->default(false);
                $table->foreignId('creator_id')->nullable()->index();
                $table->foreignId('created_by')->nullable()->index();

                $table->foreign('creator_id', 'appointment_hours_creator_id_foreign')->references('id')->on('users')->onDelete('set null');
                $table->foreign('created_by', 'appointment_hours_created_by_foreign')->references('id')->on('users')->onDelete('cascade');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_hours');
    }
};
