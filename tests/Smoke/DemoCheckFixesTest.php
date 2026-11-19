<?php

namespace Tests\Smoke;

use App\Models\User;
use App\Models\Warehouse;
use App\Services\AuthSessionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Workdo\Account\Models\BankAccount;
use Workdo\Account\Models\Expense;
use Workdo\Account\Models\JournalEntry;
use Workdo\Hrm\Models\Employee;
use Workdo\Hrm\Models\Payroll;
use Workdo\Hrm\Models\PayrollEntry;
use Workdo\Pos\Models\Pos;
use Workdo\Pos\Models\PosPayment;
use Workdo\ProductService\Models\ProductServiceCategory;
use Workdo\ProductService\Models\ProductServiceItem;
use Workdo\ProductService\Models\WarehouseStock;

class DemoCheckFixesTest extends TestCase
{
    use DatabaseTransactions;

    private function company(string $permission): User
    {
        $company = User::where('type', 'company')->orderBy('id')->firstOrFail();
        $this->actingAs($company)->withSession([
            AuthSessionService::SESSION_KEY => max(1, (int) ($company->security_version ?? 1)),
        ]);
        if (!$company->can($permission)) {
            $this->markTestSkipped("First company lacks {$permission}.");
        }

        return $company;
    }

    private function inertia(string $url): array
    {
        $response = $this->get($url, [
            'X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest',
            'X-Inertia-Version' => (string) app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
        ]);
        $response->assertOk();

        return $response->json('props');
    }

    private function user(User $company, string $type): User
    {
        return User::create([
            'name' => ucfirst($type) . ' Test ' . uniqid(), 'email' => $type . '-' . uniqid() . '@example.test',
            'password' => bcrypt(str()->random(32)), 'type' => $type,
            'created_by' => $company->id, 'creator_id' => $company->id,
        ]);
    }

    public function test_paying_salary_without_bank_account_shows_clear_message_and_writes_nothing(): void
    {
        $company = $this->company('pay-payslip');
        $staff = $this->user($company, 'staff');
        $payroll = Payroll::create([
            'title' => 'No bank payroll', 'payroll_frequency' => 'monthly', 'pay_period_start' => '2026-10-01',
            'pay_period_end' => '2026-10-31', 'pay_date' => '2026-11-01', 'status' => 'completed',
            'bank_account_id' => null, 'creator_id' => $company->id, 'created_by' => $company->id,
        ]);
        $entry = PayrollEntry::create([
            'payroll_id' => $payroll->id, 'employee_id' => $staff->id, 'basic_salary' => 1000,
            'gross_pay' => 1000, 'net_pay' => 1000, 'status' => 'unpaid',
            'creator_id' => $company->id, 'created_by' => $company->id,
        ]);
        $journalsBefore = JournalEntry::where('created_by', $company->id)->count();

        $this->from('/hrm/payrolls/' . $payroll->id)
            ->patch(route('hrm.payroll-entries.pay', $entry))
            ->assertRedirect()
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'Choose a bank account for this payroll'));

        $this->assertSame('unpaid', $entry->fresh()->status);
        $this->assertSame($journalsBefore, JournalEntry::where('created_by', $company->id)->count());
    }

    public function test_recent_expenses_use_expense_date_and_base_currency(): void
    {
        $company = $this->company('manage-account-dashboard');
        $template = Expense::where('created_by', $company->id)->first();
        $bank = BankAccount::where('created_by', $company->id)->first();
        if (!$template || !$bank) {
            $this->markTestSkipped('First company has no expense to copy from.');
        }

        Expense::create([
            'expense_number' => 'EXP-TEST-' . uniqid(), 'expense_date' => '2099-01-05',
            'category_id' => $template->category_id, 'bank_account_id' => $bank->id,
            'chart_of_account_id' => $template->chart_of_account_id, 'amount' => 100,
            'currency_code' => 'USD', 'exchange_rate' => 3.64, 'description' => 'Dashboard date test',
            'status' => 'posted', 'creator_id' => $company->id, 'created_by' => $company->id,
        ]);

        $first = $this->inertia(route('account.index'))['recentExpenses'][0];

        $this->assertSame('Dashboard date test', $first['description']);
        $this->assertSame('2099-01-05', $first['date']);
        $this->assertEqualsWithDelta(364.0, $first['amount'], 0.001);
    }

    public function test_pos_dashboard_counts_sales_by_sale_date(): void
    {
        $company = $this->company('manage-pos-dashboard');
        $warehouse = Warehouse::create([
            'name' => 'Dashboard Date Cafe', 'address' => 'Test', 'city' => 'Doha', 'zip_code' => '0',
            'is_active' => true, 'creator_id' => $company->id, 'created_by' => $company->id,
        ]);
        $beforeProps = $this->inertia(route('pos.index'));
        $before = $beforeProps['stats'];

        // Entered today but dated last year: not part of today's, this week's or this month's sales.
        $sale = Pos::create([
            'sale_number' => '#TEST' . uniqid(), 'warehouse_id' => $warehouse->id, 'pos_date' => now()->subYear()->toDateString(),
            'status' => 'completed', 'creator_id' => $company->id, 'created_by' => $company->id,
        ]);
        PosPayment::create(['pos_id' => $sale->id, 'amount' => 500, 'discount' => 0, 'discount_amount' => 500,
            'creator_id' => $company->id, 'created_by' => $company->id]);

        // Dated today: counted.
        $today = Pos::create([
            'sale_number' => '#TEST' . uniqid(), 'warehouse_id' => $warehouse->id, 'pos_date' => now()->toDateString(),
            'status' => 'completed', 'creator_id' => $company->id, 'created_by' => $company->id,
        ]);
        PosPayment::create(['pos_id' => $today->id, 'amount' => 80, 'discount' => 5, 'discount_amount' => 75,
            'creator_id' => $company->id, 'created_by' => $company->id]);

        $after = $this->inertia(route('pos.index'));
        $this->assertEqualsWithDelta(75.0, (float) $after['stats']['today_sales'] - (float) $before['today_sales'], 0.001);
        $this->assertEqualsWithDelta(75.0, (float) $after['stats']['month_sales'] - (float) $before['month_sales'], 0.001);
        $this->assertEqualsWithDelta(575.0, (float) $after['stats']['total_revenue'] - (float) $before['total_revenue'], 0.001);
        $this->assertEqualsWithDelta(75.0, (float) end($after['last10DaysSales'])['sales'] - (float) end($beforeProps['last10DaysSales'])['sales'], 0.001);
    }

    public function test_hr_role_users_appear_in_hrm_employee_lists(): void
    {
        $company = $this->company('manage-leave-applications');
        $hrUser = $this->user($company, 'hr');
        $vendor = $this->user($company, 'vendor');

        // Add Employee: an HR officer can be made an employee; vendors still cannot.
        if ($company->can('create-employees')) {
            $users = collect($this->inertia(route('hrm.employees.create'))['users'])->pluck('id');
            $this->assertTrue($users->contains($hrUser->id));
            $this->assertFalse($users->contains($vendor->id));
        }

        Employee::create(['employee_id' => 'EMP-TEST-' . uniqid(), 'user_id' => $hrUser->id,
            'creator_id' => $company->id, 'created_by' => $company->id]);

        $employees = collect($this->inertia(route('hrm.leave-applications.index'))['employees'])->pluck('id');
        $this->assertTrue($employees->contains($hrUser->id));
    }

    public function test_pos_screen_hides_items_without_selling_price(): void
    {
        $company = $this->company('create-pos');
        $warehouse = Warehouse::create([
            'name' => 'Till Test Cafe', 'address' => 'Test', 'city' => 'Doha', 'zip_code' => '0',
            'is_active' => true, 'creator_id' => $company->id, 'created_by' => $company->id,
        ]);
        $category = fn (string $name) => ProductServiceCategory::create([
            'name' => $name, 'color' => '#000000', 'creator_id' => $company->id, 'created_by' => $company->id,
        ]);
        $menu = $category('Till Test Menu');
        $raw = $category('Till Test Raw');
        $item = function (string $name, $categoryId, float $price) use ($company, $warehouse) {
            $product = ProductServiceItem::create([
                'name' => $name, 'sku' => uniqid(), 'category_id' => $categoryId, 'sale_price' => $price,
                'purchase_price' => 1, 'type' => 'product', 'is_active' => true,
                'creator_id' => $company->id, 'created_by' => $company->id,
            ]);
            WarehouseStock::create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => 10]);

            return $product;
        };
        $latte = $item('Till Test Latte', $menu->id, 18);
        $beans = $item('Till Test Beans', $raw->id, 0);

        $products = collect($this->getJson(route('pos.products', ['warehouse_id' => $warehouse->id]))->assertOk()->json())->pluck('id');
        $this->assertTrue($products->contains($latte->id));
        $this->assertFalse($products->contains($beans->id));

        $categories = collect($this->inertia(route('pos.create'))['categories'])->pluck('id');
        $this->assertTrue($categories->contains($menu->id));
        $this->assertFalse($categories->contains($raw->id));
    }
}
