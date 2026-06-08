<?php

namespace Workdo\PettyCashManagement\Database\Seeders;

use Workdo\PettyCashManagement\Models\PettyCashRequest;
use Workdo\PettyCashManagement\Models\PettyCashExpense;
use Workdo\PettyCashManagement\Models\PettyCash;
use Illuminate\Database\Seeder;
use Workdo\PettyCashManagement\Models\PettyCashCategory;
use App\Models\User;

class DemoPettyCashRequestSeeder extends Seeder
{
    public function run($userId): void
    {
        if (PettyCashRequest::where('created_by', $userId)->exists()) {
            return;
        }

        $users       = User::where('created_by', $userId)->emp()->pluck('id')->toArray();
        $categories  = PettyCashCategory::where('created_by', $userId)->pluck('id')->toArray();
        $pettyCashes = PettyCash::where('created_by', $userId)->get();

        if (empty($users) || empty($categories) || $pettyCashes->isEmpty()) {
            return;
        }

        $requests = [
            ['amount' => 150, 'status' => '1', 'remarks' => 'Office supplies purchase for monthly requirements'],
            ['amount' => 75,  'status' => '1', 'remarks' => 'Parking fees and courier charges for client meeting'],
            ['amount' => 200, 'status' => '1', 'remarks' => 'Travel expenses for business trip'],
            ['amount' => 125, 'status' => '1', 'remarks' => 'Stationery and postage for office use'],
            ['amount' => 180, 'status' => '1', 'remarks' => 'Maintenance and cleaning supplies'],
            ['amount' => 90,  'status' => '1', 'remarks' => 'Communication and utilities payment'],
            ['amount' => 160, 'status' => '0', 'remarks' => 'Pending request for emergency office expenses'],
            ['amount' => 220, 'status' => '0', 'remarks' => 'Pending request for monthly office maintenance'],
            ['amount' => 110, 'status' => '0', 'remarks' => 'Pending request for fuel and transportation costs'],
            ['amount' => 300, 'status' => '0', 'remarks' => 'Pending request for equipment purchase'],
            ['amount' => 250, 'status' => '2', 'remarks' => 'Rejected request for unnecessary expenses'],
        ];

        $startDate = now()->subDays(50);
        $pettyCashIndex = 0;

        foreach ($requests as $index => $requestData) {
            $date          = $startDate->copy()->addDays($index * 2);
            $year          = $date->format('Y');
            $month         = $date->format('m');
            $requestNumber = "REQ-{$year}-{$month}-" . str_pad($index + 1, 3, '0', STR_PAD_LEFT);

            $request = PettyCashRequest::create([
                'request_number'   => $requestNumber,
                'user_id'          => $users[array_rand($users)],
                'categorie_id'     => $categories[array_rand($categories)],
                'requested_amount' => $requestData['amount'],
                'status'           => $requestData['status'],
                'remarks'          => $requestData['remarks'],
                'approved_at'      => $requestData['status'] == '1' ? $date->format('Y-m-d H:i:s') : null,
                'approved_by'      => $requestData['status'] == '1' ? $userId : null,
                'approved_amount'  => $requestData['status'] == '1' ? $requestData['amount'] : null,
                'rejection_reason' => $requestData['status'] == '2' ? 'Budget constraints and non-essential expense' : null,
                'creator_id'       => $userId,
                'created_by'       => $userId,
                'created_at'       => $date,
                'updated_at'       => $date,
            ]);

            if ($requestData['status'] == '1') {
                $pettyCash = PettyCash::where('created_by', $userId)->where('status', 1)->latest()->first();

                if ($pettyCash && $pettyCash->closing_balance >= $requestData['amount']) {
                    $pettyCash->closing_balance -= $requestData['amount'];
                    $pettyCash->total_expense += $requestData['amount'];
                    $pettyCash->save();

                    PettyCashExpense::create([
                        'request_id'   => $request->id,
                        'pettycash_id' => $pettyCash->id,
                        'type'         => 'pettycash',
                        'amount'       => $requestData['amount'],
                        'remarks'      => $requestData['remarks'],
                        'status'       => 1,
                        'approved_at'  => $date->format('Y-m-d H:i:s'),
                        'approved_by'  => $userId,
                        'creator_id'   => $userId,
                        'created_by'   => $userId,
                        'created_at'   => $date,
                        'updated_at'   => $date,
                    ]);
                }
            }
        }
    }
}
