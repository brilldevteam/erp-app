<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('contracts', 'amount_paid')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->decimal('amount_paid', 10, 2)->default(0)->after('value');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('contracts', 'amount_paid')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->dropColumn('amount_paid');
            });
        }
    }
};
