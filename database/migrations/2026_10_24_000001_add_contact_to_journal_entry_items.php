<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Journal lines can name the vendor or customer they relate to, like Zoho's line Contact.
    public function up(): void
    {
        if (Schema::hasColumn('journal_entry_items', 'contact_id')) {
            return;
        }

        Schema::table('journal_entry_items', function (Blueprint $table) {
            $table->unsignedBigInteger('contact_id')->nullable()->after('account_id')->index();
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('journal_entry_items', 'contact_id')) {
            return;
        }

        Schema::table('journal_entry_items', function (Blueprint $table) {
            $table->dropIndex(['contact_id']);
            $table->dropColumn('contact_id');
        });
    }
};
