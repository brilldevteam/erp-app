<?php

namespace Workdo\Account\Services;

class OutstandingDocumentService
{
    public static function apply($query, string $statusColumn = 'status', string $balanceColumn = 'balance_amount')
    {
        return $query
            ->where($balanceColumn, '>', 0)
            ->whereNotIn($statusColumn, ['draft', 'cancelled']);
    }

    public static function paymentState(float $totalAmount, float $paidAmount): array
    {
        $balanceAmount = (float) max(0, round($totalAmount - $paidAmount, 2));

        return [
            'balance_amount' => $balanceAmount,
            'status' => $balanceAmount <= 0 ? 'paid' : 'partial',
        ];
    }
}
