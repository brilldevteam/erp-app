<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('sales_quotations', 'type')) {
            Schema::table('sales_quotations', function (Blueprint $table) {
                // Empty on quotations made before the split; their type is worked out from their lines.
                $table->string('type', 20)->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sales_quotations', 'type')) {
            Schema::table('sales_quotations', function (Blueprint $table) {
                $table->dropColumn('type');
            });
        }
    }
};
