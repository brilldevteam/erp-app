<?php

namespace Workdo\PettyCashManagement\Database\Seeders;

use Workdo\PettyCashManagement\Models\PettyCashReimbursement;
use Workdo\PettyCashManagement\Models\PettyCashExpense;
use Workdo\PettyCashManagement\Models\PettyCash;
use Workdo\PettyCashManagement\Models\PettyCashCategory;
use Illuminate\Database\Seeder;
use App\Models\User;

class DemoReimbursementSeeder extends Seeder
{
    public function run($userId): void
    {
        if (PettyCashReimbursement::where('created_by', $userId)->exists()) {
            return;
        }

        $users       = User::where('created_by', $userId)->emp()->pluck('id')->toArray();
        $categories  = PettyCashCategory::where('created_by', $userId)->pluck('id')->toArray();
        $pettyCashes = PettyCash::where('created_by', $userId)->get();

        if (empty($users) || empty($categories) || $pettyCashes->isEmpty()) {
            return;
        }

        $reimbursements = [
            ['amount' => 85,  'status' => '1', 'description' => 'Business lunch with client - restaurant bill'],
            ['amount' => 120, 'status' => '1', 'description' => 'Taxi fare for urgent client meeting'],
            ['amount' => 45,  'status' => '1', 'description' => 'Office supplies purchased from personal funds'],
            ['amount' => 200, 'status' => '1', 'description' => 'Hotel accommodation for business trip'],
            ['amount' => 65,  'status' => '0', 'description' => 'Pending reimbursement for fuel expenses'],
            ['amount' => 150, 'status' => '0', 'description' => 'Pending reimbursement for conference registration fee'],
            ['amount' => 75,  'status' => '0', 'description' => 'Pending reimbursement for parking and toll charges'],
            ['amount' => 95,  'status' => '0', 'description' => 'Pending reimbursement for emergency equipment repair'],
            ['amount' => 180, 'status' => '0', 'description' => 'Pending reimbursement for training materials'],
            ['amount' => 300, 'status' => '2', 'description' => 'Personal expense incorrectly submitted'],
        ];

        $startDate = now()->subDays(45);
        $pettyCashIndex = 0;

        foreach ($reimbursements as $index => $reimbursementData) {
            $requestDate = $startDate->copy()->addDays($index * 3);
            $approvedDate = $reimbursementData['status'] == '1' ? $requestDate->copy()->addHours(rand(2, 48)) : null;

            $year = $requestDate->format('Y');
            $month = $requestDate->format('m');
            $reimbursementNumber = "RMB-{$year}-{$month}-" . str_pad($index + 1, 3, '0', STR_PAD_LEFT);

            $reimbursement = PettyCashReimbursement::create([
                'reimbursement_number' => $reimbursementNumber,
                'user_id'              => $users[array_rand($users)],
                'category_id'          => $categories[array_rand($categories)],
                'amount'               => $reimbursementData['amount'],
                'status'               => $reimbursementData['status'],
                'description'          => $reimbursementData['description'],
                'approved_date'        => $approvedDate ? $approvedDate->format('Y-m-d H:i:s') : null,
                'approved_by'          => $reimbursementData['status'] == '1' ? $userId : null,
                'approved_amount'      => $reimbursementData['status'] == '1' ? $reimbursementData['amount'] : null,
                'rejection_reason'     => $reimbursementData['status'] == '2' ? 'Personal expense not eligible for reimbursement' : null,
                'creator_id'           => $userId,
                'created_by'           => $userId,
                'created_at'           => $requestDate,
                'updated_at'           => $approvedDate ?? $requestDate,
            ]);

            if ($reimbursementData['status'] == '1') {
                $pettyCash = PettyCash::where('created_by', $userId)->where('status', 1)->latest()->first();

                if ($pettyCash && $pettyCash->closing_balance >= $reimbursementData['amount']) {

                    $pettyCash->closing_balance -= $reimbursementData['amount'];
                    $pettyCash->total_expense += $reimbursementData['amount'];
                    $pettyCash->save();

                    PettyCashExpense::create([
                        'reimbursement_id' => $reimbursement->id,
                        'pettycash_id'     => $pettyCash->id,
                        'type'             => 'reimbursement',
                        'amount'           => $reimbursementData['amount'],
                        'remarks'          => $reimbursementData['description'],
                        'status'           => 1,
                        'approved_at'      => $approvedDate->format('Y-m-d H:i:s'),
                        'approved_by'      => $userId,
                        'creator_id'       => $userId,
                        'created_by'       => $userId,
                        'created_at'       => $approvedDate,
                        'updated_at'       => $approvedDate,
                    ]);
                }
            }
        }
    }
}
