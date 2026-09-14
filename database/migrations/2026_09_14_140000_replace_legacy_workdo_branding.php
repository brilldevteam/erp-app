<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('settings')) {
            $replacements = [
                'titleText' => 'Wazely ERP',
                'footerText' => '© Wazely ERP. All rights reserved.',
                'metaTitle' => 'Wazely ERP - Dashboard',
                'company_email_from_name' => 'Wazely ERP',
            ];

            foreach ($replacements as $key => $value) {
                DB::table('settings')
                    ->where('key', $key)
                    ->where(function ($query) {
                        $query->whereNull('value')
                            ->orWhere('value', '')
                            ->orWhereRaw('LOWER(value) LIKE ?', ['%workdo%dash%'])
                            ->orWhereRaw('LOWER(value) LIKE ?', ['%work do dash%'])
                            ->orWhereRaw('LOWER(value) = ?', ['wazely.io'])
                            ->orWhereRaw('LOWER(value) LIKE ?', ['%wazely.io%dashboard%'])
                            ->orWhereRaw('LOWER(value) LIKE ?', ['%wazely.io%rights reserved%']);
                    })
                    ->update(['value' => $value]);
            }
        }

        if (Schema::hasTable('landing_page_settings')) {
            DB::table('landing_page_settings')
                ->where(function ($query) {
                    $query->whereRaw('LOWER(company_name) LIKE ?', ['%workdo%dash%'])
                        ->orWhereRaw('LOWER(company_name) LIKE ?', ['%work do dash%'])
                        ->orWhereRaw('LOWER(company_name) = ?', ['wazely.io']);
                })
                ->update(['company_name' => 'Wazely ERP']);
        }
    }

    public function down(): void
    {
        // Branding migrations are intentionally not reversed.
    }
};
