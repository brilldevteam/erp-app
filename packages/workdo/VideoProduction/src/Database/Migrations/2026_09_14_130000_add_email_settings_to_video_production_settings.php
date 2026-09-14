<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_production_settings', function (Blueprint $table) {
            $table->boolean('report_email_enabled')->default(true);
            $table->json('report_email_recipients')->nullable();
            $table->json('report_email_cc')->nullable();
            $table->string('report_email_subject')->default('Production Report: {record_id}');
            $table->text('report_email_message')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('video_production_settings', function (Blueprint $table) {
            $table->dropColumn([
                'report_email_enabled',
                'report_email_recipients',
                'report_email_cc',
                'report_email_subject',
                'report_email_message',
            ]);
        });
    }
};
