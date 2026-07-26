<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Workdo\Taskly\Models\ProjectContract;

class ProjectContractFinancialSummaryTest extends TestCase
{
    public function test_it_calculates_paid_and_remaining_values_from_the_loaded_invoice_total(): void
    {
        $contract = new ProjectContract(['contract_value' => 100000]);
        $contract->setAttribute('amount_paid', 35000.50);

        $this->assertSame([
            'contract_value' => 100000.0,
            'amount_paid' => 35000.5,
            'remaining_balance' => 64999.5,
        ], $contract->financialSummary());
    }

    public function test_it_keeps_an_overpayment_visible_as_a_negative_balance(): void
    {
        $contract = new ProjectContract(['contract_value' => 1000]);
        $contract->setAttribute('amount_paid', 1250);

        $this->assertSame(-250.0, $contract->financialSummary()['remaining_balance']);
    }
}
