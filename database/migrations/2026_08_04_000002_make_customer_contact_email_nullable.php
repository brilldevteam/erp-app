<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customers') && Schema::hasColumn('customers', 'contact_person_email')) {
            Schema::table('customers', function (Blueprint $table): void {
                $table->string('contact_person_email')->nullable()->change();
            });

            DB::table('customers')
                ->where('contact_person_email', 'like', 'zoho.customer.%@example.com')
                ->update(['contact_person_email' => null]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('customers') && Schema::hasColumn('customers', 'contact_person_email')) {
            DB::table('customers')
                ->whereNull('contact_person_email')
                ->update(['contact_person_email' => '']);

            Schema::table('customers', function (Blueprint $table): void {
                $table->string('contact_person_email')->nullable(false)->change();
            });
        }
    }
};
