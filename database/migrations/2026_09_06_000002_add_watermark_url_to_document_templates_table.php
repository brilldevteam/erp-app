<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('document_templates') || Schema::hasColumn('document_templates', 'watermark_url')) {
            return;
        }

        Schema::table('document_templates', function (Blueprint $table) {
            $table->string('watermark_url', 500)->nullable()->after('logo_url');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('document_templates') || !Schema::hasColumn('document_templates', 'watermark_url')) {
            return;
        }

        Schema::table('document_templates', function (Blueprint $table) {
            $table->dropColumn('watermark_url');
        });
    }
};
