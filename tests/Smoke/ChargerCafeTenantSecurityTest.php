<?php

namespace Tests\Smoke;

use App\Models\User;
use App\Models\Warehouse;
use App\Services\AuthSessionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\Account\Models\ExpenseCategories;
use Workdo\BudgetPlanner\Models\Budget;
use Workdo\BudgetPlanner\Models\BudgetPeriod;
use Workdo\Lead\Models\Deal;
use Workdo\Lead\Models\DealStage;
use Workdo\Lead\Models\Lead;
use Workdo\Lead\Models\LeadStage;
use Workdo\Pos\Events\CreatePos;
use Workdo\Pos\Models\Pos;
use Workdo\ProductService\Models\ProductServiceItem;
use Workdo\ProductService\Models\WarehouseStock;

class ChargerCafeTenantSecurityTest extends TestCase
{
    use DatabaseTransactions;

    private User $company;
    private User $foreignCompany;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = User::findOrFail(950);
        $this->foreignCompany = User::where('type', 'company')->whereKeyNot($this->company->id)->firstOrFail();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach ([
            'create-stock', 'create-pos', 'create-journal-entries', 'create-expense-categories',
            'delete-expense-categories',
            'lead-move', 'edit-deals', 'approve-budgets', 'active-budgets',
            'approve-budget-periods', 'active-budget-periods', 'create-sales-proposals',
        ] as $name) {
            $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            if (!$this->company->hasPermissionTo($permission)) {
                $this->company->givePermissionTo($permission);
            }
        }

        $this->actingAs($this->company)->withSession([
            AuthSessionService::SESSION_KEY => max(1, (int) ($this->company->security_version ?? 1)),
        ]);
    }

    private function product(int $tenantId, float $salePrice = 20): ProductServiceItem
    {
        return ProductServiceItem::create([
            'name' => 'Tenant Test Product',
            'sku' => uniqid('tenant-', true),
            'sale_price' => $salePrice,
            'purchase_price' => 5,
            'type' => 'product',
            'is_active' => true,
            'creator_id' => $tenantId,
            'created_by' => $tenantId,
        ]);
    }

    private function warehouse(int $tenantId): Warehouse
    {
        return Warehouse::create([
            'name' => 'Tenant Test Warehouse',
            'address' => 'Test',
            'city' => 'Doha',
            'zip_code' => '0',
            'is_active' => true,
            'creator_id' => $tenantId,
            'created_by' => $tenantId,
        ]);
    }

    public function test_stock_entry_rejects_foreign_product_and_warehouse(): void
    {
        $ownProduct = $this->product($this->company->id);
        $ownWarehouse = $this->warehouse($this->company->id);
        $foreignProduct = $this->product($this->foreignCompany->id);
        $foreignWarehouse = $this->warehouse($this->foreignCompany->id);

        $this->post(route('product-service.stock.store'), [
            'product_id' => $foreignProduct->id,
            'warehouse_id' => $ownWarehouse->id,
            'quantity' => 5,
        ])->assertNotFound();

        $this->post(route('product-service.stock.store'), [
            'product_id' => $ownProduct->id,
            'warehouse_id' => $foreignWarehouse->id,
            'quantity' => 5,
        ])->assertNotFound();

        $this->assertFalse(WarehouseStock::where('product_id', $foreignProduct->id)->exists());
        $this->assertFalse(WarehouseStock::where('warehouse_id', $foreignWarehouse->id)->exists());
    }

    public function test_journal_entry_rejects_a_foreign_chart_account(): void
    {
        $ownAccount = ChartOfAccount::where('created_by', $this->company->id)->firstOrFail();
        $foreignAccount = ChartOfAccount::create([
            'account_code' => uniqid('9'),
            'account_name' => 'Foreign Tenant Account',
            'account_type_id' => $ownAccount->account_type_id,
            'normal_balance' => 'debit',
            'is_active' => true,
            'creator_id' => $this->foreignCompany->id,
            'created_by' => $this->foreignCompany->id,
        ]);

        $this->post(route('account.journal-entries.store'), [
            'journal_date' => '2026-11-21',
            'currency_code' => 'QAR',
            'exchange_rate' => 1,
            'description' => 'Tenant isolation test',
            'items' => [
                ['account_id' => $ownAccount->id, 'debit_amount' => 10, 'credit_amount' => 0],
                ['account_id' => $foreignAccount->id, 'debit_amount' => 0, 'credit_amount' => 10],
            ],
        ])->assertForbidden();
    }

    public function test_pos_uses_database_price_and_rejects_foreign_products(): void
    {
        Event::fake([CreatePos::class]);
        $warehouse = $this->warehouse($this->company->id);
        $product = $this->product($this->company->id, 20);
        $foreignProduct = $this->product($this->foreignCompany->id, 1);

        $payload = [
            'customer_id' => null,
            'warehouse_id' => $warehouse->id,
            'pos_date' => '2026-11-21',
            // A sale needs the bank or cash account it is paid into.
            'bank_account_id' => \Workdo\Account\Models\BankAccount::where('created_by', $this->company->id)->value('id'),
            'items' => [['id' => $foreignProduct->id, 'quantity' => 2, 'price' => 1]],
            'discount' => 0,
        ];
        $this->post(route('pos.store'), $payload)->assertForbidden();

        $payload['items'] = [['id' => $product->id, 'quantity' => 2, 'price' => 1]];
        $this->post(route('pos.store'), $payload)->assertSessionHasNoErrors()->assertSessionHas('success');

        $sale = Pos::where('created_by', $this->company->id)->latest('id')->firstOrFail();
        $this->assertEqualsWithDelta(20, (float) $sale->items()->firstOrFail()->price, 0.001);
        $this->assertEqualsWithDelta(40, (float) $sale->payment()->firstOrFail()->amount, 0.001);
    }

    public function test_expense_category_code_is_unique_per_company_not_globally(): void
    {
        $code = 'TENANT-' . uniqid();
        $foreignCategory = ExpenseCategories::create([
            'category_name' => 'Foreign Category',
            'category_code' => $code,
            'is_active' => true,
            'creator_id' => $this->foreignCompany->id,
            'created_by' => $this->foreignCompany->id,
        ]);

        $payload = [
            'category_name' => 'Own Category',
            'category_code' => $code,
            'description' => null,
            'gl_account_id' => null,
            'is_active' => true,
        ];
        $this->post(route('account.expense-categories.store'), $payload)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->post(route('account.expense-categories.store'), $payload)
            ->assertSessionHasErrors('category_code');

        $this->delete(route('account.expense-categories.destroy', $foreignCategory))
            ->assertNotFound();
        $this->assertDatabaseHas('expense_categories', ['id' => $foreignCategory->id]);
    }

    public function test_lead_order_rejects_a_foreign_company_lead(): void
    {
        $stage = LeadStage::where('created_by', $this->company->id)->firstOrFail();
        $ownLead = Lead::create([
            'name' => 'Own Lead', 'email' => uniqid() . '@example.test', 'subject' => 'Test',
            'pipeline_id' => $stage->pipeline_id, 'stage_id' => $stage->id, 'order' => 1,
            'creator_id' => $this->company->id, 'created_by' => $this->company->id,
        ]);
        $foreignLead = Lead::create([
            'name' => 'Foreign Lead', 'email' => uniqid() . '@example.test', 'subject' => 'Test',
            'pipeline_id' => $stage->pipeline_id, 'stage_id' => $stage->id, 'order' => 9,
            'creator_id' => $this->foreignCompany->id, 'created_by' => $this->foreignCompany->id,
        ]);

        $this->post(route('lead.leads.order'), [
            'lead_id' => $ownLead->id,
            'stage_id' => $stage->id,
            'order' => [$foreignLead->id],
        ])->assertNotFound();

        $this->assertSame(9, (int) $foreignLead->fresh()->order);
    }

    public function test_deal_status_change_rejects_a_foreign_company_deal(): void
    {
        $stage = DealStage::where('created_by', $this->company->id)->firstOrFail();
        $deal = Deal::create([
            'name' => 'Foreign Deal', 'pipeline_id' => $stage->pipeline_id, 'stage_id' => $stage->id,
            'status' => 'Pending', 'creator_id' => $this->foreignCompany->id, 'created_by' => $this->foreignCompany->id,
        ]);

        $this->post(route('lead.deals.change-status', $deal), ['deal_status' => 'Won'])->assertNotFound();
        $this->assertSame('Pending', $deal->fresh()->status);
    }

    public function test_budget_and_period_approve_and_activate_reject_foreign_records(): void
    {
        $draftPeriod = BudgetPeriod::create([
            'period_name' => 'Foreign Draft', 'financial_year' => '2026', 'status' => 'draft',
            'creator_id' => $this->foreignCompany->id, 'created_by' => $this->foreignCompany->id,
        ]);
        $approvedPeriod = BudgetPeriod::create([
            'period_name' => 'Foreign Approved', 'financial_year' => '2026', 'status' => 'approved',
            'creator_id' => $this->foreignCompany->id, 'created_by' => $this->foreignCompany->id,
        ]);
        $draftBudget = Budget::create([
            'budget_name' => 'Foreign Draft Budget', 'period_id' => $draftPeriod->id,
            'budget_type' => 'operational', 'total_budget_amount' => 100, 'status' => 'draft',
            'creator_id' => $this->foreignCompany->id, 'created_by' => $this->foreignCompany->id,
        ]);
        $approvedBudget = Budget::create([
            'budget_name' => 'Foreign Approved Budget', 'period_id' => $approvedPeriod->id,
            'budget_type' => 'operational', 'total_budget_amount' => 100, 'status' => 'approved',
            'creator_id' => $this->foreignCompany->id, 'created_by' => $this->foreignCompany->id,
        ]);

        $this->post(route('budget-planner.budgets.approve', $draftBudget))->assertNotFound();
        $this->post(route('budget-planner.budgets.active', $approvedBudget))->assertNotFound();
        $this->post(route('budget-planner.budget-periods.approve', $draftPeriod))->assertNotFound();
        $this->post(route('budget-planner.budget-periods.active', $approvedPeriod))->assertNotFound();

        $this->assertSame('draft', $draftBudget->fresh()->status);
        $this->assertSame('approved', $approvedBudget->fresh()->status);
        $this->assertSame('draft', $draftPeriod->fresh()->status);
        $this->assertSame('approved', $approvedPeriod->fresh()->status);
    }

    public function test_sales_proposal_rejects_foreign_customer_and_product_ids(): void
    {
        $ownCustomer = User::create([
            'name' => 'Own Proposal Customer', 'email' => uniqid() . '@example.test', 'password' => 'test-password',
            'type' => 'client', 'email_verified_at' => now(), 'creator_id' => $this->company->id,
            'created_by' => $this->company->id,
        ]);
        $foreignCustomer = User::create([
            'name' => 'Foreign Proposal Customer', 'email' => uniqid() . '@example.test', 'password' => 'test-password',
            'type' => 'client', 'email_verified_at' => now(), 'creator_id' => $this->foreignCompany->id,
            'created_by' => $this->foreignCompany->id,
        ]);
        $ownProduct = $this->product($this->company->id);
        $foreignProduct = $this->product($this->foreignCompany->id);

        $payload = [
            'invoice_date' => '2026-11-21', 'due_date' => '2026-11-22',
            'customer_id' => $foreignCustomer->id,
            'items' => [['product_id' => $ownProduct->id, 'quantity' => 1, 'unit_price' => 20]],
        ];
        $this->post(route('sales-proposals.store'), $payload)->assertForbidden();

        $payload['customer_id'] = $ownCustomer->id;
        $payload['items'][0]['product_id'] = $foreignProduct->id;
        $this->post(route('sales-proposals.store'), $payload)->assertForbidden();
    }
}
