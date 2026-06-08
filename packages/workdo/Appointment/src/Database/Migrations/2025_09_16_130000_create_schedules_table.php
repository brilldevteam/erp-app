<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('schedules')) {
            Schema::create('schedules', function (Blueprint $table) {
                $table->id();
                $table->string('unique_id', 100)->unique();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('name')->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->date('date')->nullable();
                $table->time('start_time')->nullable();
                $table->time('end_time')->nullable();
                $table->foreignId('appointment_id')->nullable()->index();
                $table->longText('questions')->nullable();
                $table->longText('cancel_description')->nullable();
                $table->string('status')->default('pending');
                $table->foreignId('creator_id')->nullable()->index();
                $table->foreignId('created_by')->nullable()->index();

                $table->foreign('user_id', 'schedules_user_id_foreign')->references('id')->on('users')->onDelete('set null');
                $table->foreign('appointment_id', 'schedules_appointment_id_foreign')->references('id')->on('appointments')->onDelete('cascade');
                $table->foreign('creator_id', 'schedules_creator_id_foreign')->references('id')->on('users')->onDelete('set null');
                $table->foreign('created_by', 'schedules_created_by_foreign')->references('id')->on('users')->onDelete('cascade');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('schedules')) {
            Schema::table('schedules', function (Blueprint $table) {
                $table->dropForeign('schedules_user_id_foreign');
                $table->dropForeign('schedules_appointment_id_foreign');
                $table->dropForeign('schedules_creator_id_foreign');
                $table->dropForeign('schedules_created_by_foreign');
            });
        }
        Schema::dropIfExists('schedules');
    }
};
