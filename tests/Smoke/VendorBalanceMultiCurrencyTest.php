<?php

namespace Tests\Smoke;

use App\Models\PurchaseInvoice;
use App\Models\User;
use App\Services\AuthSessionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Workdo\Account\Services\ReportService;

class VendorBalanceMultiCurrencyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_vendor_balance_converts_foreign_currency_bills_to_base_currency(): void
    {
        $company = User::where('type', 'company')->orderBy('id')->firstOrFail();
        $this->actingAs($company)->withSession([
            AuthSessionService::SESSION_KEY => max(1, (int) ($company->security_version ?? 1)),
        ]);

        $vendor = User::create([
            'name' => 'FX Test Vendor', 'email' => 'fx-vendor-' . uniqid() . '@example.test',
            'password' => bcrypt(str()->random(32)), 'type' => 'vendor',
            'created_by' => $company->id, 'creator_id' => $company->id,
        ]);

        $bill = fn (array $values) => PurchaseInvoice::create(array_merge([
            'invoice_date' => '2026-11-16', 'due_date' => '2026-11-17', 'vendor_id' => $vendor->id,
            'subtotal' => 100, 'tax_amount' => 0, 'discount_amount' => 0, 'status' => 'posted',
            'creator_id' => $company->id, 'created_by' => $company->id,
        ], $values));

        // USD 100 at 3.64, unpaid: owes QAR 364.
        $bill(['currency_code' => 'USD', 'base_currency_code' => 'QAR', 'exchange_rate' => 3.64,
            'total_amount' => 100, 'paid_amount' => 0, 'balance_amount' => 100, 'base_amount' => 364]);
        // QAR 1000, half paid: owes QAR 500.
        $bill(['currency_code' => 'QAR', 'base_currency_code' => 'QAR', 'exchange_rate' => 1,
            'total_amount' => 1000, 'paid_amount' => 500, 'balance_amount' => 500, 'base_amount' => 1000]);
        // Drafts are never counted.
        $bill(['currency_code' => 'USD', 'base_currency_code' => 'QAR', 'exchange_rate' => 3.64, 'status' => 'draft',
            'total_amount' => 50, 'paid_amount' => 0, 'balance_amount' => 50, 'base_amount' => 182]);

        $summary = app(ReportService::class)->getVendorBalanceSummary(['as_of_date' => '2026-12-31']);
        $row = collect($summary['vendors'])->firstWhere('vendor_id', $vendor->id);

        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(1364.0, $row['total_billed'], 0.001);
        $this->assertEqualsWithDelta(864.0, $row['balance'], 0.001);
        $this->assertEqualsWithDelta(500.0, $row['total_paid'], 0.001);
        // Numbers, not text, so the page's Total row can add them up.
        $this->assertIsFloat($row['total_billed']);
        $this->assertIsFloat($row['total_returns']);
    }
}
