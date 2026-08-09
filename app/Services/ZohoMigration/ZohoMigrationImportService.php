<?php

namespace App\Services\ZohoMigration;

use App\Services\BulkImport\Concerns\NormalizesImportValues;
use App\Services\BulkImport\Definitions\Concerns\ResolvesImportReferences;
use Illuminate\Support\Facades\DB;
use Workdo\Account\Models\BankAccount;
use Workdo\Account\Models\CustomerPayment;
use Workdo\Account\Models\CustomerPaymentAllocation;
use Workdo\Account\Models\Expense;
use Workdo\Account\Models\VendorPayment;
use Workdo\Account\Models\VendorPaymentAllocation;

/**
 * One-time historical data migration path for Zoho Books exports.
 *
 * This is deliberately NOT wired into the generic bulk-import framework
 * (App\Services\BulkImport\Definitions\*) that backs the day-to-day
 * "Bulk Import" screens in the UI/API. Those definitions, their form
 * requests, and the underlying Expense/CustomerPayment/VendorPayment
 * models are untouched by this class and remain exactly as strict as
 * before. This service exists only to load Zoho's historical export,
 * which references bank/cash accounts that don't necessarily exist yet
 * in the ERP and uses Zoho's internal GL codes rather than real bank
 * account numbers.
 */
class ZohoMigrationImportService
{
    use NormalizesImportValues;
    use ResolvesImportReferences;

    /**
     * Zoho "Paid Through" values that are internal clearing/suspense
     * accounts rather than real cash or bank accounts.
     */
    private const CLEARING_ACCOUNT_NAMES = [
        'salary payable',
        'projects on process',
    ];

    private array $bankAccountCache = [];

    public function importExpenses(array $rows, int $tenantId, int $actorId): array
    {
        $summary = ['imported' => 0, 'skipped' => 0, 'errors' => []];

        foreach ($rows as $index => $row) {
            try {
                $outcome = DB::transaction(fn () => $this->importExpenseRow($row, $tenantId, $actorId));
                $summary[$outcome]++;
            } catch (\Throwable $e) {
                $summary['skipped']++;
                $summary['errors'][] = $this->rowLabel($index, $row['expense_number'] ?? null).': '.$e->getMessage();
            }
        }

        return $summary;
    }

    public function importCustomerPayments(array $rows, int $tenantId, int $actorId): array
    {
        $summary = ['imported' => 0, 'skipped' => 0, 'errors' => []];

        foreach ($rows as $index => $row) {
            try {
                $outcome = DB::transaction(fn () => $this->importCustomerPaymentRow($row, $tenantId, $actorId));
                $summary[$outcome]++;
            } catch (\Throwable $e) {
                $summary['skipped']++;
                $summary['errors'][] = $this->rowLabel($index, $row['payment_number'] ?? null).': '.$e->getMessage();
            }
        }

        return $summary;
    }

    public function importVendorPayments(array $rows, int $tenantId, int $actorId): array
    {
        $summary = ['imported' => 0, 'skipped' => 0, 'errors' => []];

        foreach ($rows as $index => $row) {
            try {
                $outcome = DB::transaction(fn () => $this->importVendorPaymentRow($row, $tenantId, $actorId));
                $summary[$outcome]++;
            } catch (\Throwable $e) {
                $summary['skipped']++;
                $summary['errors'][] = $this->rowLabel($index, $row['payment_number'] ?? null).': '.$e->getMessage();
            }
        }

        return $summary;
    }

    private function importExpenseRow(array $row, int $tenantId, int $actorId): string
    {
        $expenseNumber = $this->text($row['expense_number'] ?? '');
        if ($expenseNumber === '') {
            throw new \RuntimeException('expense_number is required.');
        }
        if (Expense::where('created_by', $tenantId)->whereRaw('LOWER(expense_number) = ?', [strtolower($expenseNumber)])->exists()) {
            return 'skipped';
        }

        $expenseDate = $this->dateValue($row['expense_date'] ?? null);
        if (!$expenseDate) {
            throw new \RuntimeException('expense_date is invalid.');
        }

        $category = $this->expenseCategory($row, $tenantId);
        if (!$category) {
            throw new \RuntimeException("expense category '{$this->text($row['category'] ?? '')}' was not found. Import expense categories before running this migration.");
        }

        $amount = $this->decimal($row['amount'] ?? 0);
        if ($amount <= 0) {
            throw new \RuntimeException('amount must be greater than zero.');
        }

        $bankAccount = $this->resolveOrCreateMigrationBankAccount($row['bank_account'] ?? '', $row['account_number'] ?? null, $tenantId, $actorId);

        Expense::create([
            'expense_number' => $expenseNumber,
            'expense_date' => $expenseDate,
            'category_id' => $category->id,
            'bank_account_id' => $bankAccount->id,
            'chart_of_account_id' => $this->chartAccount($row['chart_of_account'] ?? null, $tenantId)?->id,
            'amount' => $amount,
            'description' => $this->nullableText($row['description'] ?? null),
            'reference_number' => $this->nullableText($row['reference_number'] ?? null),
            'status' => in_array($row['status'] ?? null, ['draft', 'approved', 'posted'], true) ? $row['status'] : 'posted',
            'needs_bank_verification' => true,
            'creator_id' => $actorId,
            'created_by' => $tenantId,
        ]);

        return 'imported';
    }

    private function importCustomerPaymentRow(array $row, int $tenantId, int $actorId): string
    {
        $paymentNumber = $this->text($row['payment_number'] ?? '');
        if ($paymentNumber === '') {
            throw new \RuntimeException('payment_number is required.');
        }
        if (CustomerPayment::where('created_by', $tenantId)->whereRaw('LOWER(payment_number) = ?', [strtolower($paymentNumber)])->exists()) {
            return 'skipped';
        }

        $paymentDate = $this->dateValue($row['payment_date'] ?? null);
        if (!$paymentDate) {
            throw new \RuntimeException('payment_date is invalid.');
        }

        $customer = $this->customerUser($row, $tenantId);
        if (!$customer) {
            throw new \RuntimeException("customer '{$this->text($row['customer'] ?? '')}' was not found. Import customers before running this migration.");
        }

        $amount = $this->decimal($row['payment_amount'] ?? 0);
        if ($amount <= 0) {
            throw new \RuntimeException('payment_amount must be greater than zero.');
        }

        $bankAccount = $this->resolveOrCreateMigrationBankAccount($row['bank_account'] ?? '', $row['account_number'] ?? null, $tenantId, $actorId);

        $payment = CustomerPayment::create([
            'payment_number' => $paymentNumber,
            'payment_date' => $paymentDate,
            'customer_id' => $customer->id,
            'bank_account_id' => $bankAccount->id,
            'reference_number' => $this->nullableText($row['reference_number'] ?? null),
            'payment_amount' => $amount,
            'status' => in_array($row['status'] ?? null, ['pending', 'cleared', 'cancelled'], true) ? $row['status'] : 'pending',
            'needs_bank_verification' => true,
            'notes' => $this->nullableText($row['notes'] ?? null),
            'creator_id' => $actorId,
            'created_by' => $tenantId,
        ]);

        if ($this->nullableText($row['invoice_number'] ?? null) && ($invoice = $this->salesInvoice($row, $tenantId))) {
            CustomerPaymentAllocation::create([
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'allocated_amount' => $this->decimal($row['allocated_amount'] ?? $row['payment_amount'] ?? 0),
            ]);
        }

        return 'imported';
    }

    private function importVendorPaymentRow(array $row, int $tenantId, int $actorId): string
    {
        $paymentNumber = $this->text($row['payment_number'] ?? '');
        if ($paymentNumber === '') {
            throw new \RuntimeException('payment_number is required.');
        }
        if (VendorPayment::where('created_by', $tenantId)->whereRaw('LOWER(payment_number) = ?', [strtolower($paymentNumber)])->exists()) {
            return 'skipped';
        }

        $paymentDate = $this->dateValue($row['payment_date'] ?? null);
        if (!$paymentDate) {
            throw new \RuntimeException('payment_date is invalid.');
        }

        $vendor = $this->vendorUser($row, $tenantId);
        if (!$vendor) {
            throw new \RuntimeException("vendor '{$this->text($row['vendor'] ?? '')}' was not found. Import vendors before running this migration.");
        }

        $amount = $this->decimal($row['payment_amount'] ?? 0);
        if ($amount <= 0) {
            throw new \RuntimeException('payment_amount must be greater than zero.');
        }

        $bankAccount = $this->resolveOrCreateMigrationBankAccount($row['bank_account'] ?? '', $row['account_number'] ?? null, $tenantId, $actorId);

        $payment = VendorPayment::create([
            'payment_number' => $paymentNumber,
            'payment_date' => $paymentDate,
            'vendor_id' => $vendor->id,
            'bank_account_id' => $bankAccount->id,
            'reference_number' => $this->nullableText($row['reference_number'] ?? null),
            'payment_amount' => $amount,
            'status' => in_array($row['status'] ?? null, ['pending', 'cleared', 'cancelled'], true) ? $row['status'] : 'pending',
            'needs_bank_verification' => true,
            'notes' => $this->nullableText($row['notes'] ?? null),
            'creator_id' => $actorId,
            'created_by' => $tenantId,
        ]);

        if ($this->nullableText($row['invoice_number'] ?? null) && ($invoice = $this->purchaseInvoice($row, $tenantId))) {
            VendorPaymentAllocation::create([
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'allocated_amount' => $this->decimal($row['allocated_amount'] ?? $row['payment_amount'] ?? 0),
            ]);
        }

        return 'imported';
    }

    /**
     * Resolve a bank/cash account referenced by a migration row, auto-creating
     * it when it doesn't exist yet — this is the "bypass the must-have-a-real-
     * bank-account validation" behavior, and it is only reachable from this
     * migration service. Normal manual/API bank account creation (unchanged)
     * still requires account_number and bank_name up front via
     * StoreBankAccountRequest.
     *
     * $glCode is Zoho's internal chart-of-account code, stored in the
     * dedicated zoho_gl_code column — never written into account_number,
     * which is left null until finance verifies the real bank account.
     */
    private function resolveOrCreateMigrationBankAccount(string $accountName, ?string $glCode, int $tenantId, int $actorId): BankAccount
    {
        $accountName = trim($accountName);
        if ($accountName === '') {
            throw new \RuntimeException('bank_account is required.');
        }

        $cacheKey = $tenantId.'|'.strtolower($accountName);
        if (isset($this->bankAccountCache[$cacheKey])) {
            return $this->bankAccountCache[$cacheKey];
        }

        $existing = BankAccount::where('created_by', $tenantId)
            ->whereRaw('LOWER(account_name) = ?', [strtolower($accountName)])
            ->first();

        if ($existing) {
            return $this->bankAccountCache[$cacheKey] = $existing;
        }

        $isClearing = in_array(strtolower($accountName), self::CLEARING_ACCOUNT_NAMES, true);
        $glCode = $this->nullableText($glCode);

        $created = BankAccount::create([
            'account_name' => $accountName,
            'account_number' => null,
            'zoho_gl_code' => $glCode,
            'bank_name' => $isClearing ? 'Internal clearing account (Zoho migration)' : 'Pending verification',
            'account_type' => $isClearing ? 'clearing_account' : 'checking',
            'opening_balance' => 0,
            'current_balance' => 0,
            'is_active' => true,
            'creator_id' => $actorId,
            'created_by' => $tenantId,
        ]);

        return $this->bankAccountCache[$cacheKey] = $created;
    }

    private function rowLabel(int $index, ?string $number): string
    {
        $rowNumber = $index + 2; // header row + 1-based data row
        return $number ? "Row {$rowNumber} ({$number})" : "Row {$rowNumber}";
    }
}
