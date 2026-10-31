<?php

namespace Tests\Unit;

require_once __DIR__ . '/../../packages/workdo/Account/src/Services/OutstandingDocumentService.php';

use PHPUnit\Framework\TestCase;
use Workdo\Account\Services\OutstandingDocumentService;

class OutstandingDocumentServiceTest extends TestCase
{
    public function test_outstanding_scope_excludes_only_non_payable_document_statuses(): void
    {
        $query = new RecordingQuery();

        OutstandingDocumentService::apply($query, 'bills.status', 'bills.balance_amount');

        $this->assertSame([['bills.balance_amount', '>', 0]], $query->whereCalls);
        $this->assertSame([['bills.status', ['draft', 'cancelled']]], $query->whereNotInCalls);
    }

    public function test_partial_payment_keeps_the_bill_outstanding(): void
    {
        $this->assertSame(
            ['balance_amount' => 300.0, 'status' => 'partial'],
            OutstandingDocumentService::paymentState(500, 200)
        );
    }

    public function test_full_payment_marks_the_bill_paid(): void
    {
        $this->assertSame(
            ['balance_amount' => 0.0, 'status' => 'paid'],
            OutstandingDocumentService::paymentState(500, 500)
        );
    }

    public function test_rounding_does_not_leave_a_paid_bill_open(): void
    {
        $this->assertSame(
            ['balance_amount' => 0.0, 'status' => 'paid'],
            OutstandingDocumentService::paymentState(500, 500.001)
        );
    }
}

class RecordingQuery
{
    public array $whereCalls = [];
    public array $whereNotInCalls = [];

    public function where(string $column, string $operator, int $value): self
    {
        $this->whereCalls[] = [$column, $operator, $value];

        return $this;
    }

    public function whereNotIn(string $column, array $values): self
    {
        $this->whereNotInCalls[] = [$column, $values];

        return $this;
    }
}
