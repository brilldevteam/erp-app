<?php

namespace Tests\Smoke;

use App\Models\User;
use App\Models\Warehouse;
use App\Services\AuthSessionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Workdo\Pos\Models\Pos;
use Workdo\Pos\Models\PosPayment;

class PosSalesReportTest extends TestCase
{
    use DatabaseTransactions;

    private function company(): User
    {
        $company = User::where('type', 'company')->orderBy('id')->firstOrFail();
        $this->actingAs($company)->withSession([
            AuthSessionService::SESSION_KEY => max(1, (int) ($company->security_version ?? 1)),
        ]);
        if (!$company->can('manage-pos-reports')) {
            $this->markTestSkipped('First company cannot open POS reports.');
        }

        return $company;
    }

    private function sale(User $company, Warehouse $warehouse, string $date, float $amount, float $discount): void
    {
        $sale = Pos::create([
            'sale_number' => '#TEST' . uniqid(), 'warehouse_id' => $warehouse->id, 'pos_date' => $date,
            'status' => 'completed', 'creator_id' => $company->id, 'created_by' => $company->id,
        ]);
        PosPayment::create([
            'pos_id' => $sale->id, 'amount' => $amount, 'discount' => $discount, 'discount_amount' => $amount - $discount,
            'creator_id' => $company->id, 'created_by' => $company->id,
        ]);
    }

    private function report(array $query): array
    {
        $response = $this->get(route('pos.reports.sales', $query), [
            'X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest',
            'X-Inertia-Version' => (string) app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
        ]);
        $response->assertOk();

        return $response->json('props');
    }

    public function test_sales_report_uses_net_amounts_sale_dates_and_filters(): void
    {
        $company = $this->company();
        $warehouse = fn (string $name) => Warehouse::create([
            'name' => $name, 'address' => 'Test', 'city' => 'Doha', 'zip_code' => '0',
            'is_active' => true, 'creator_id' => $company->id, 'created_by' => $company->id,
        ]);
        $cafeA = $warehouse('Report Test Cafe A');
        $cafeB = $warehouse('Report Test Cafe B');

        $this->sale($company, $cafeA, '2026-10-05', 100, 10);   // net 90
        $this->sale($company, $cafeA, '2026-10-06', 50, 0);     // net 50
        $this->sale($company, $cafeB, '2026-10-06', 80, 5);     // net 75
        $this->sale($company, $cafeA, '2026-09-30', 999, 0);    // outside the period

        $props = $this->report(['date_from' => '2026-10-01', 'date_to' => '2026-10-31', 'warehouse_id' => $cafeA->id]);

        $this->assertSame(2, $props['summary']['sales_count']);
        $this->assertEqualsWithDelta(140.0, $props['summary']['net'], 0.001);
        $this->assertEqualsWithDelta(10.0, $props['summary']['discount'], 0.001);
        $this->assertSame(2, $props['salesData']['total']);
        $this->assertSame([['name' => 'Report Test Cafe A', 'sales' => 140, 'count' => 2]], $props['warehouseSales']);

        // Daily chart is dated by the sale date, not the entry date.
        $daily = collect($props['dailySales'])->keyBy('date');
        $this->assertEqualsWithDelta(90.0, $daily['Oct 05']['sales'], 0.001);
        $this->assertEqualsWithDelta(50.0, $daily['Oct 06']['sales'], 0.001);

        $monthly = collect($props['monthlySales'])->keyBy('month');
        $this->assertEqualsWithDelta(140.0, $monthly['Oct 2026']['sales'], 0.001);
        $this->assertEqualsWithDelta(999.0, $monthly['Sep 2026']['sales'], 0.001);

        // Both cafes: net of discounts per cafe.
        $both = $this->report(['date_from' => '2026-10-01', 'date_to' => '2026-10-31']);
        $byCafe = collect($both['warehouseSales'])->keyBy('name');
        $this->assertEqualsWithDelta(140.0, $byCafe['Report Test Cafe A']['sales'], 0.001);
        $this->assertEqualsWithDelta(75.0, $byCafe['Report Test Cafe B']['sales'], 0.001);
    }

    public function test_account_dashboard_recent_revenue_includes_pos_sales(): void
    {
        $company = $this->company();
        $cafe = Warehouse::create([
            'name' => 'Dashboard Test Cafe', 'address' => 'Test', 'city' => 'Doha', 'zip_code' => '0',
            'is_active' => true, 'creator_id' => $company->id, 'created_by' => $company->id,
        ]);
        $this->sale($company, $cafe, '2099-01-01', 120, 20);

        $response = $this->get(route('account.index'), [
            'X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest',
            'X-Inertia-Version' => (string) app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
        ]);
        $response->assertOk();
        $first = $response->json('props.recentRevenues.0');

        $this->assertStringStartsWith('pos-', (string) $first['id']);
        $this->assertStringContainsString('Dashboard Test Cafe', $first['description']);
        $this->assertEqualsWithDelta(100.0, $first['amount'], 0.001);
    }
}
