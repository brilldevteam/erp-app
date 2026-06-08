<?php

namespace Workdo\PettyCashManagement\Database\Seeders;

use Workdo\PettyCashManagement\Models\PettyCash;
use Illuminate\Database\Seeder;



class DemoPettyCashSeeder extends Seeder
{
    public function run($userId): void
    {
        if (PettyCash::where('created_by', $userId)->exists()) {
            return;
        }

        $entries = [
            ['added_amount' => 5000, 'status' => 1, 'remarks' => 'Initial petty cash fund setup'],
            ['added_amount' => 3000, 'status' => 1, 'remarks' => 'Monthly fund replenishment'],
            ['added_amount' => 2000, 'status' => 1, 'remarks' => 'Additional funds for operations'],
            ['added_amount' => 2500, 'status' => 1, 'remarks' => 'Quarterly budget allocation'],
            ['added_amount' => 1500, 'status' => 1, 'remarks' => 'Emergency fund addition'],
            ['added_amount' => 2000, 'status' => 1, 'remarks' => 'Office expenses fund'],
            ['added_amount' => 1800, 'status' => 1, 'remarks' => 'Travel and transport fund'],
            ['added_amount' => 2200, 'status' => 0, 'remarks' => 'Maintenance fund allocation'],
        ];

        $cumulativeBalance = 0;
        $startDate = now()->subDays(60);

        foreach ($entries as $index => $entry) {
            $date           = $startDate->copy()->addDays($index * 7);
            $openingBalance = $cumulativeBalance;
            $addedAmount    = $entry['added_amount'];
            $totalBalance   = $openingBalance + $addedAmount;
            $totalExpense   = 0;
            $closingBalance = $totalBalance - $totalExpense;

            $year            = $date->format('Y');
            $month           = $date->format('m');
            $pettycashNumber = "PC-{$year}-{$month}-" . str_pad($index + 1, 3, '0', STR_PAD_LEFT);

            PettyCash::create([
                'pettycash_number' => $pettycashNumber,
                'date'            => $date->format('Y-m-d'),
                'opening_balance' => $openingBalance,
                'added_amount'    => $addedAmount,
                'total_balance'   => $totalBalance,
                'total_expense'   => $totalExpense,
                'closing_balance' => $closingBalance,
                'status'          => $entry['status'],
                'remarks'         => $entry['remarks'],
                'creator_id'      => $userId,
                'created_by'      => $userId,
            ]);

            $cumulativeBalance = $closingBalance;
        }
    }
}
