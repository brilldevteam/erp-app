<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $labels = [
        'manage-debit-notes' => 'Manage Vendor Credits',
        'manage-any-debit-notes' => 'Manage All Vendor Credits',
        'manage-own-debit-notes' => 'Manage Own Vendor Credits',
        'view-debit-notes' => 'View Vendor Credits',
        'create-debit-notes' => 'Create Vendor Credits',
        'approve-debit-notes' => 'Approve Vendor Credits',
        'delete-debit-notes' => 'Delete Vendor Credits',
    ];

    public function up(): void
    {
        foreach ($this->labels as $name => $label) {
            DB::table('permissions')
                ->where('module', 'debit-notes')
                ->where('name', $name)
                ->update(['label' => $label]);
        }
    }

    public function down(): void
    {
        $original = [
            'manage-debit-notes' => 'Manage Debit Notes',
            'manage-any-debit-notes' => 'Manage All Debit Notes',
            'manage-own-debit-notes' => 'Manage Own Debit Notes',
            'view-debit-notes' => 'View Debit Notes',
            'create-debit-notes' => 'Create Debit Notes',
            'approve-debit-notes' => 'Approve Debit Notes',
            'delete-debit-notes' => 'Delete Debit Notes',
        ];

        foreach ($original as $name => $label) {
            DB::table('permissions')
                ->where('module', 'debit-notes')
                ->where('name', $name)
                ->update(['label' => $label]);
        }
    }
};
