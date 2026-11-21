<?php

namespace Tests\Smoke;

use App\Models\User;
use App\Models\Warehouse;
use App\Services\AuthSessionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use Workdo\Account\Models\JournalEntry;
use Workdo\Pos\Events\CreatePos;
use Workdo\Pos\Models\Pos;
use Workdo\ProductService\Models\ProductServiceItem;
use Workdo\ProductService\Models\WarehouseStock;

/** A POS sale is saved together with its stock, cash and accounting entries, or not at all. */
class PosSaleSavingTest extends TestCase
{
    use DatabaseTransactions;

    private User $company;
    private Warehouse $warehouse;
    private ProductServiceItem $latte;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = User::findOrFail(950);
        $this->actingAs($this->company)->withSession([
            AuthSessionService::SESSION_KEY => max(1, (int) ($this->company->security_version ?? 1)),
        ]);
        if (!$this->company->can('create-pos')) {
            $this->markTestSkipped('Test company lacks create-pos.');
        }

        $this->warehouse = Warehouse::create(['name' => 'Saving Test Cafe', 'address' => 'Test', 'city' => 'Doha', 'zip_code' => '0',
            'is_active' => true, 'creator_id' => 950, 'created_by' => 950]);
        $this->latte = ProductServiceItem::create(['name' => 'Saving Test Latte', 'sku' => uniqid(), 'sale_price' => 20,
            'purchase_price' => 6, 'type' => 'product', 'is_active' => true, 'creator_id' => 950, 'created_by' => 950]);
        WarehouseStock::create(['product_id' => $this->latte->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 10]);
    }

    private function sell()
    {
        $bank = \Workdo\Account\Models\BankAccount::where('created_by', 950)->whereNotNull('gl_account_id')->firstOrFail();

        return $this->from('/pos/create')->post(route('pos.store'), [
            'customer_id' => null, 'warehouse_id' => $this->warehouse->id, 'pos_date' => '2026-11-20', 'bank_account_id' => $bank->id,
            'items' => [['id' => $this->latte->id, 'quantity' => 3, 'price' => 20, 'discount_amount' => 0, 'tax_amount' => 0]],
            'tax_amount' => 0, 'discount' => 5,
        ]);
    }

    private function stock(): float
    {
        return (float) WarehouseStock::where('warehouse_id', $this->warehouse->id)->where('product_id', $this->latte->id)->value('quantity');
    }

    public function test_sale_posts_stock_and_accounts(): void
    {
        $this->sell()->assertSessionHasNoErrors()->assertSessionHas('success');

        $sale = Pos::where('warehouse_id', $this->warehouse->id)->firstOrFail();
        $this->assertSame(7.0, $this->stock());
        $revenue = JournalEntry::where('reference_type', 'pos_sale')->where('reference_id', $sale->id)->first();
        $this->assertNotNull($revenue);
        $this->assertEqualsWithDelta(55, (float) $revenue->total_debit, 0.001);
        $this->assertTrue(JournalEntry::where('reference_type', 'pos_sale_cogs')->where('reference_id', $sale->id)->exists());
    }

    public function test_sale_without_a_bank_account_asks_for_one_and_saves_nothing(): void
    {
        $this->from('/pos/create')->post(route('pos.store'), [
            'customer_id' => null, 'warehouse_id' => $this->warehouse->id, 'pos_date' => '2026-11-20', 'bank_account_id' => null,
            'items' => [['id' => $this->latte->id, 'quantity' => 1, 'price' => 20]], 'tax_amount' => 0, 'discount' => 0,
        ])->assertSessionHasErrors(['bank_account_id' => 'Choose the bank or cash account for this sale.']);

        $this->assertFalse(Pos::where('warehouse_id', $this->warehouse->id)->exists());
        $this->assertSame(10.0, $this->stock());
    }

    public function test_sale_is_not_saved_and_cashier_sees_why_when_a_step_fails(): void
    {
        // Simulate the accounting step failing, as it did when journal numbers were stuck.
        Event::listen(CreatePos::class, function () {
            throw new \RuntimeException('Ledger is closed for this period.');
        });

        $this->sell()->assertSessionHasErrors(['pos' => 'The sale could not be saved: Ledger is closed for this period.'])
            ->assertSessionMissing('success');

        // Nothing half-saved: no sale, no stock taken, no journal.
        $this->assertFalse(Pos::where('warehouse_id', $this->warehouse->id)->exists());
        $this->assertSame(10.0, $this->stock());
    }
}
