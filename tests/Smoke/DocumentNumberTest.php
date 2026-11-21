<?php

namespace Tests\Smoke;

use App\Models\User;
use App\Services\AuthSessionService;
use App\Support\DocumentNumber;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Workdo\Account\Models\BankTransfer;
use Workdo\Account\Models\DebitNote;
use Workdo\Pos\Models\Pos;
use Workdo\PettyCashManagement\Models\PettyCashRequest;

/** Document numbers keep counting past 999 and never clash. */
class DocumentNumberTest extends TestCase
{
    use DatabaseTransactions;

    private User $company;
    private string $month;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = User::findOrFail(950);
        $this->actingAs($this->company)->withSession([
            AuthSessionService::SESSION_KEY => max(1, (int) ($this->company->security_version ?? 1)),
        ]);
        $this->month = date('Y') . '-' . date('m') . '-';
    }

    private function otherCompany(): int
    {
        return (int) User::where('type', 'company')->where('id', '!=', 950)->orderBy('id')->value('id');
    }

    /** Highest number used after $prefix in $table, read the safe way, for picking seeds above it. */
    private function highest(string $table, string $column, string $prefix, ?int $company = null): int
    {
        return (int) DB::table($table)->where($column, 'like', $prefix . '%')
            ->when($company, fn ($q) => $q->where('created_by', $company))
            ->get([$column])->map(fn ($r) => (int) substr($r->$column, strlen($prefix)))->max();
    }

    public function test_company_sequence_counts_past_999_and_ignores_other_companies(): void
    {
        $prefix = 'Req-' . $this->month;
        $seed = fn (string $number, int $company) => DB::table('petty_cash_requests')->insert([
            'request_number' => $number, 'user_id' => 950, 'requested_amount' => 1, 'status' => '0',
            'created_by' => $company, 'creator_id' => $company, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $big = max($this->highest('petty_cash_requests', 'request_number', $prefix, 950), 1000) + 1;

        // Text order puts "...-999" after "...-1000"; the old code then repeated the same number.
        if (!DB::table('petty_cash_requests')->where('created_by', 950)->where('request_number', $prefix . '999')->exists()) {
            $seed($prefix . '999', 950);
        }
        $seed($prefix . $big, 950);
        // Another company's much higher number does not move this company's sequence.
        $seed($prefix . ($big + 5000), $this->otherCompany());

        $this->assertSame($prefix . ($big + 1), PettyCashRequest::generateRequestNumber());
    }

    public function test_first_numbers_keep_their_padding(): void
    {
        $prefix = 'Req-' . $this->month;
        DB::table('petty_cash_requests')->where('created_by', 950)->where('request_number', 'like', $prefix . '%')->delete();

        $this->assertSame($prefix . '001', PettyCashRequest::generateRequestNumber());
    }

    public function test_debit_note_numbers_do_not_clash_between_companies(): void
    {
        // The column is unique across all companies while each company counts its own notes.
        $prefix = 'DN-' . $this->month;
        $vendor = (int) User::where('type', 'vendor')->value('id');
        $other = $this->otherCompany();
        $taken = DB::table('debit_notes')->where('debit_note_number', 'like', $prefix . '%')->pluck('debit_note_number')->all();
        $mine = $this->highest('debit_notes', 'debit_note_number', $prefix, 950);
        $clash = $prefix . str_pad((string) ($mine + 1), 3, '0', STR_PAD_LEFT);
        if (!in_array($clash, $taken, true)) {
            DB::table('debit_notes')->insert(['debit_note_number' => $clash, 'debit_note_date' => now()->toDateString(),
                'vendor_id' => $vendor, 'reason' => 'Number test', 'created_by' => $other, 'creator_id' => $other,
                'created_at' => now(), 'updated_at' => now()]);
        }

        $next = DebitNote::generateDebitNoteNumber();

        $this->assertNotSame($clash, $next);
        $this->assertFalse(DB::table('debit_notes')->where('debit_note_number', $next)->exists());
        // It can actually be saved (the old code hit the unique key here).
        DB::table('debit_notes')->insert(['debit_note_number' => $next, 'debit_note_date' => now()->toDateString(),
            'vendor_id' => $vendor, 'reason' => 'Number test', 'created_by' => 950, 'creator_id' => 950,
            'created_at' => now(), 'updated_at' => now()]);
        $this->assertTrue(DB::table('debit_notes')->where('debit_note_number', $next)->exists());
    }

    public function test_bank_transfer_numbers_count_past_999(): void
    {
        $prefix = 'BT-' . $this->month;
        $accounts = DB::table('bank_accounts')->where('created_by', 950)->limit(2)->pluck('id');
        if ($accounts->count() < 2) {
            $this->markTestSkipped('Test company needs two bank accounts.');
        }
        $seed = fn (string $number) => DB::table('bank_transfers')->insert(['transfer_number' => $number,
            'transfer_date' => now()->toDateString(), 'from_account_id' => $accounts[0], 'to_account_id' => $accounts[1],
            'transfer_amount' => 1, 'description' => 'Number test', 'created_by' => 950, 'creator_id' => 950,
            'created_at' => now(), 'updated_at' => now()]);
        $big = max($this->highest('bank_transfers', 'transfer_number', $prefix), 1000) + 1;
        if (!DB::table('bank_transfers')->where('transfer_number', $prefix . '999')->exists()) {
            $seed($prefix . '999');
        }
        $seed($prefix . $big);

        $this->assertSame($prefix . ($big + 1), BankTransfer::generateTransferNumber());
    }

    public function test_pos_sale_number_follows_the_highest_number_not_the_count(): void
    {
        $warehouse = (int) DB::table('warehouses')->where('created_by', 950)->value('id');
        $big = max($this->highest('pos', 'sale_number', '#POS', 950), 999) + 50;
        DB::table('pos')->insert(['sale_number' => '#POS' . str_pad((string) $big, 5, '0', STR_PAD_LEFT), 'warehouse_id' => $warehouse,
            'pos_date' => now()->toDateString(), 'status' => 'completed', 'created_by' => 950, 'creator_id' => 950,
            'created_at' => now(), 'updated_at' => now()]);

        $this->assertSame('#POS' . str_pad((string) ($big + 1), 5, '0', STR_PAD_LEFT), Pos::generateSaleNumber());
    }

    public function test_helper_skips_numbers_taken_elsewhere_and_escapes_the_prefix(): void
    {
        $prefix = 'Req_' . $this->month;     // "_" must be matched literally, not as a wildcard
        DB::table('petty_cash_requests')->insert(['request_number' => 'ReqX' . $this->month . '777', 'user_id' => 950,
            'requested_amount' => 1, 'status' => '0', 'created_by' => 950, 'creator_id' => 950, 'created_at' => now(), 'updated_at' => now()]);

        $scope = PettyCashRequest::where('created_by', 950);
        $this->assertSame($prefix . '001', DocumentNumber::next($scope, 'request_number', $prefix));
    }
}
