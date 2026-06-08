<?php

namespace Workdo\PettyCashManagement\Database\Seeders;

use Workdo\PettyCashManagement\Models\PettyCashCategory;
use Illuminate\Database\Seeder;



class DemoPettyCashCategorySeeder extends Seeder
{
    public function run($userId): void
    {
        if (PettyCashCategory::where('created_by', $userId)->exists()) {
            return;
        }

        $categories = [
            'Office Supplies',
            'Travel Expenses',
            'Meals & Entertainment',
            'Transportation',
            'Stationery',
            'Postage & Courier',
            'Refreshments',
            'Parking Fees',
            'Fuel & Gas',
            'Maintenance & Repairs',
            'Cleaning Supplies',
            'Utilities',
            'Communication',
            'Emergency Expenses',
            'Miscellaneous',
        ];

        foreach ($categories as $category) {
            PettyCashCategory::create([
                'name' => $category,
                'creator_id' => $userId,
                'created_by' => $userId,
            ]);
        }
    }
}
