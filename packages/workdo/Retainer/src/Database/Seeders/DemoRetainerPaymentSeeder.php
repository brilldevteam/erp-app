<?php

namespace Workdo\Retainer\Database\Seeders;

use Illuminate\Database\Seeder;
use Carbon\Carbon;
use Workdo\Retainer\Models\SalesRetainer;
use Workdo\Retainer\Models\SalesRetainerPayment;
use Workdo\Retainer\Models\RetainerPaymentAllocation;
use Workdo\Account\Models\BankAccount;

class DemoRetainerPaymentSeeder extends Seeder
{
    public function run($userId)
    {
        $hasRetainerPayment = SalesRetainerPayment::where('created_by', $userId)->exists();
        $hasPaymentAllocation = RetainerPaymentAllocation::where('created_by', $userId)->exists();

        if ($hasRetainerPayment && $hasPaymentAllocation) {
            return; // All three tables contain user data → skip seeding
        }
        if (!empty($userId)) {
              // Get accepted retainers with balance
            $retainers = SalesRetainer::where('created_by', $userId)
                ->whereIn('status', ['accepted', 'sent'])
                ->where('balance_amount', '>', 0)
                ->limit(10)
                ->get();

            $bankAccounts = BankAccount::where('created_by', $userId)->pluck('id')->toArray();

            if ($retainers->isEmpty() || empty($bankAccounts)) {
                return;
            }

            $paymentData = [
                ['days_ago' => 45, 'percentage' => [60, 80], 'status' => 'cleared', 'method' => 'Bank Transfer'],
                ['days_ago' => 40, 'percentage' => [50, 70], 'status' => 'cleared', 'method' => 'Check'],
                ['days_ago' => 35, 'percentage' => [70, 90], 'status' => 'cleared', 'method' => 'Online Payment'],
                ['days_ago' => 30, 'percentage' => [40, 60], 'status' => 'cleared', 'method' => 'Cash'],
                ['days_ago' => 25, 'percentage' => [55, 75], 'status' => 'cleared', 'method' => 'Bank Transfer'],
                ['days_ago' => 20, 'percentage' => [65, 85], 'status' => 'pending', 'method' => 'Check'],
                ['days_ago' => 15, 'percentage' => [45, 65], 'status' => 'cleared', 'method' => 'Online Payment'],
                ['days_ago' => 10, 'percentage' => [50, 70], 'status' => 'pending', 'method' => 'Bank Transfer'],
                ['days_ago' => 5, 'percentage' => [60, 80], 'status' => 'cleared', 'method' => 'Cash'],
                ['days_ago' => 2, 'percentage' => [40, 60], 'status' => 'pending', 'method' => 'Online Payment']
            ];

            foreach ($retainers as $index => $retainer) {
                if ($index >= count($paymentData)) break;

                $data        = $paymentData[$index];
                $paymentDate = Carbon::now()->subDays($data['days_ago']);

                  // Calculate payment amount
                $paymentPercentage = rand($data['percentage'][0], $data['percentage'][1]) / 100;
                $paymentAmount     = round($retainer->balance_amount * $paymentPercentage, 2);

                $payment = SalesRetainerPayment::create([
                    'payment_number'   => 'RP-' . $paymentDate->format('Y-m') . '-' . str_pad($index + 1, 3, '0', STR_PAD_LEFT),
                    'payment_date'     => $paymentDate->toDateString(),
                    'customer_id'      => $retainer->customer_id,
                    'bank_account_id'  => $bankAccounts[array_rand($bankAccounts)],
                    'payment_amount'   => $paymentAmount,
                    'reference_number' => 'REF-' . $paymentDate->format('Ymd') . '-' . rand(1000, 9999),
                    'status'           => $data['status'],
                    'notes'            => 'Payment for retainer services - ' . $data['method'],
                    'creator_id'       => $userId,
                    'created_by'       => $userId,
                    'created_at'       => $paymentDate,
                    'updated_at'       => $paymentDate,
                ]);

                  // Create allocation
                RetainerPaymentAllocation::create([
                    'payment_id'       => $payment->id,
                    'retainer_id'      => $retainer->id,
                    'allocated_amount' => $paymentAmount,
                    'creator_id'       => $userId,
                    'created_by'       => $userId,
                    'created_at'       => $paymentDate,
                    'updated_at'       => $paymentDate,
                ]);

                  // Update retainer if payment is cleared
                if ($payment->status === 'cleared') {
                    $retainer->paid_amount    += $paymentAmount;
                    $retainer->balance_amount -= $paymentAmount;

                    if ($retainer->balance_amount <= 0) {
                        $retainer->status         = 'paid';
                        $retainer->balance_amount = 0;
                    } else {
                        $retainer->status = 'partial';
                    }

                    $retainer->save();
                }
            }
        }
    }
}
