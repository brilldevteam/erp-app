<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'electronic_address')) {
                $table->string('electronic_address')->nullable()->after('notes');
            }
            if (!Schema::hasColumn('customers', 'electronic_address_scheme')) {
                $table->string('electronic_address_scheme')->nullable()->after('electronic_address');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['electronic_address', 'electronic_address_scheme']);
        });
    }
};
