<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('document_templates') || Schema::hasColumn('document_templates', 'signature_url')) {
            return;
        }

        Schema::table('document_templates', function (Blueprint $table) {
            $table->string('signature_url', 500)->nullable()->after('bank_details');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('document_templates') || !Schema::hasColumn('document_templates', 'signature_url')) {
            return;
        }

        Schema::table('document_templates', function (Blueprint $table) {
            $table->dropColumn('signature_url');
        });
    }
};
