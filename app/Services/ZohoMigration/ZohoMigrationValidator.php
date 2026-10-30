<?php

namespace App\Services\ZohoMigration;

use App\Models\ZohoMigrationBatch;
use App\Models\ZohoMigrationEvent;
use App\Models\ZohoMigrationIssue;
use App\Models\ZohoMigrationStagedRow;
use App\Services\BulkImport\Definitions\ChartOfAccountDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class ZohoMigrationValidator
{
    public function __construct(
        private readonly ZohoProfileCatalog $profiles,
        private readonly ZohoChartOfAccountsService $chartOfAccounts,
        private readonly ZohoCustomerService $customers,
        private readonly ZohoVendorService $vendors,
        private readonly ZohoBankAccountService $bankAccounts,
        private readonly ZohoIncomeExpenseCategoryService $incomeExpenseCategories,
        private readonly ZohoQuotationService $quotations,
        private readonly ZohoSalesInvoiceService $salesInvoices,
        private readonly ZohoBillService $bills,
        private readonly ZohoCustomerCreditService $customerCredits,
        private readonly ZohoVendorCreditService $vendorCredits,
        private readonly ZohoCustomerPaymentService $customerPayments,
        private readonly ZohoVendorPaymentService $vendorPayments,
        private readonly ZohoExpenseService $expenses,
        private readonly ZohoBankActivityService $bankActivity,
        private readonly ZohoManualJournalService $manualJournals,
        private readonly ZohoCreditApplicationService $creditApplications,
        private readonly ZohoReconciliationService $reconciliation,
    )
    {
    }

    public function validate(ZohoMigrationBatch $batch, string $stageKey, int $actorId): array
    {
        $files = $batch->files()->where('stage_key', $stageKey)->where('status', 'staged')->get();
        if ($files->isEmpty()) {
            return ['accepted' => 0, 'rejected' => 0, 'warnings' => 0, 'issues' => 0];
        }

        return DB::transaction(function () use ($batch, $stageKey, $actorId, $files): array {
            $batch->issues()->where('stage_key', $stageKey)->delete();
            $accepted = 0;
            $rejected = 0;
            $skipped = 0;
            $sourceAccounts = $stageKey === 'chart_of_accounts'
                ? $files->flatMap(fn ($file) => $file->stagedRows()->get())->map(fn ($row) => $this->chartOfAccounts->prepare($row->original_values))
                : collect();
            $sourceAccountKeys = $sourceAccounts->flatMap(fn (array $row) => [
                mb_strtolower((string) $row['account_code']),
                mb_strtolower((string) $row['account_name']),
            ])->filter()->all();
            $seenAccountCodes = [];
            $seenContacts = [];
            $seenChartRows = [];
            // Document checks compare each document's lines with its Zoho total, so all lines are prepared first.
            $documents = match ($stageKey) {
                'quotations' => $this->quotations,
                'sales_invoices' => $this->salesInvoices,
                'purchase_invoices' => $this->bills,
                'credit_notes' => $this->customerCredits,
                'vendor_credits' => $this->vendorCredits,
                'customer_payments' => $this->customerPayments,
                'vendor_payments' => $this->vendorPayments,
                'income_expenses' => $this->expenses,
                'bank_activity' => $this->bankActivity,
                'accounting' => $this->manualJournals,
                'credit_applications' => $this->creditApplications,
                'reconciliation' => $this->reconciliation,
                default => null,
            };
            $documentContext = $documents
                ? $documents->context($files->flatMap(fn ($file) => $file->stagedRows()->get())
                    // Rows of different files (a stage taking several kinds of file) can share row numbers.
                    ->mapWithKeys(fn ($row) => [($files->count() > 1 ? $row->file_id.':' : '').$row->source_row_number => $documents->prepare($row->original_values, $batch)])
                    ->filter(fn (array $row) => $row['include'] ?? true))
                : [];
            $contacts = match ($stageKey) {
                'customers' => $this->customers,
                'vendors' => $this->vendors,
                default => null,
            };

            foreach ($files as $file) {
                foreach ($file->stagedRows()->orderBy('source_row_number')->cursor() as $row) {
                    $normalized = match (true) {
                        $stageKey === 'chart_of_accounts' => $this->chartOfAccounts->prepare($row->original_values),
                        $stageKey === 'payment_accounts' => $this->bankAccounts->prepare($row->original_values, (string) $batch->base_currency),
                        $stageKey === 'income_expense_categories' => $this->incomeExpenseCategories->prepare($row->original_values),
                        $documents !== null => $documents->prepare($row->original_values, $batch),
                        $contacts !== null => $contacts->prepare($row->original_values, (string) $batch->base_currency),
                        default => $this->normalize($row->original_values),
                    };
                    // Rows the stage deliberately leaves out (other chart account types, void invoices) are skipped, not errors.
                    if (in_array($stageKey, ['payment_accounts', 'income_expense_categories', 'sales_invoices', 'purchase_invoices', 'credit_notes', 'vendor_credits', 'bank_activity', 'accounting', 'reconciliation'], true) && !$normalized['include']) {
                        $row->update(['status' => 'skipped', 'validation_errors' => null, 'normalized_values' => $normalized]);
                        continue;
                    }
                    $issues = match (true) {
                        $stageKey === 'chart_of_accounts' => $this->chartAccountIssues($normalized, $batch->tenant_id, $sourceAccountKeys, $seenAccountCodes),
                        $stageKey === 'payment_accounts' => $this->bankAccounts->issues($normalized, $batch, (int) $row->source_row_number, $seenChartRows),
                        $stageKey === 'income_expense_categories' => $this->incomeExpenseCategories->issues($normalized, $batch, (int) $row->source_row_number, $seenChartRows),
                        $documents !== null => $documents->issues($normalized, $batch, (int) $row->source_row_number, $documentContext),
                        $contacts !== null => $contacts->issues($normalized, $batch, (int) $row->source_row_number, $seenContacts),
                        // Stages without an importer yet only check that the file is readable.
                        default => [],
                    };
                    $row->normalized_values = $normalized;
                    if ($issues) {
                        $rejected++;
                        $this->storeIssues($batch, $row, $stageKey, $issues);
                        $row->update(['status' => 'invalid', 'validation_errors' => array_column($issues, 'message'), 'normalized_values' => $normalized]);
                    } elseif ($documents !== null && method_exists($documents, 'alreadyImported') && $documents->alreadyImported($normalized, $batch, (int) $row->source_row_number, $documentContext)) {
                        // A newer export of a step imported before: rows already in ERP (and unchanged) are skipped.
                        $skipped++;
                        $row->update(['status' => 'skipped', 'validation_errors' => null, 'normalized_values' => $normalized]);
                    } else {
                        $accepted++;
                        $row->update(['status' => 'validated', 'validation_errors' => null, 'normalized_values' => $normalized]);
                    }
                }
            }

            $status = $rejected > 0 ? 'review_required' : 'ready';
            $batch->stages()->where('stage_key', $stageKey)->update([
                'status' => $status,
                'accepted_count' => $accepted,
                'rejected_count' => $rejected,
                'warning_count' => 0,
                'summary' => json_encode(['create' => $accepted, 'link' => 0, 'skip' => $skipped, 'update' => 0, 'reject' => $rejected]),
                'updated_at' => now(),
            ]);

            ZohoMigrationEvent::create([
                'batch_id' => $batch->id,
                'tenant_id' => $batch->tenant_id,
                'user_id' => $actorId,
                'stage_key' => $stageKey,
                'event_type' => 'stage_validated',
                'message' => $rejected > 0
                    ? "Validation found {$rejected} rows requiring master-data correction."
                    : "Validation passed for {$accepted} source rows.".($skipped ? " {$skipped} rows were imported earlier and are skipped." : ''),
                'metadata' => ['accepted' => $accepted, 'rejected' => $rejected, 'skipped' => $skipped],
            ]);

            return ['accepted' => $accepted, 'rejected' => $rejected, 'warnings' => 0, 'issues' => $rejected];
        });
    }

    private function chartAccountIssues(array $row, int $tenantId, array $sourceAccountKeys, array &$seenCodes): array
    {
        $definition = new ChartOfAccountDefinition();
        $errors = $definition->validate($row, $tenantId);
        $parent = mb_strtolower(trim((string) ($row['parent_account'] ?? '')));
        if ($parent !== '' && in_array($parent, $sourceAccountKeys, true)) {
            $errors = array_values(array_filter($errors, fn (string $error) => $error !== 'Parent account was not found.'));
        }

        $code = mb_strtolower(trim((string) ($row['account_code'] ?? '')));
        if ($code !== '' && isset($seenCodes[$code])) {
            $errors[] = 'Account code appears more than once in this file.';
        }
        if ($code !== '') {
            $seenCodes[$code] = true;
        }

        return array_map(fn (string $message) => [
            'type' => 'invalid_chart_account',
            'field' => str_contains($message, 'Account type') ? 'account_type' : (str_contains($message, 'Parent') ? 'parent_account' : 'account_code'),
            'source_value' => str_contains($message, 'Account type') ? $row['account_type'] : (str_contains($message, 'Parent') ? $row['parent_account'] : $row['account_code']),
            'message' => $message,
            'resolution_url' => Route::has('account.chart-of-accounts.index') ? route('account.chart-of-accounts.index') : null,
        ], array_values(array_unique($errors)));
    }

    private function normalize(array $values): array
    {
        $normalized = [];
        foreach ($values as $key => $value) {
            $normalized[$this->profiles->normalize($key)] = is_scalar($value) ? trim((string) $value) : null;
        }

        return $normalized;
    }

    private function storeIssues(ZohoMigrationBatch $batch, ZohoMigrationStagedRow $row, string $stageKey, array $issues): void
    {
        foreach ($issues as $issue) {
            ZohoMigrationIssue::create($issue + [
                'batch_id' => $batch->id,
                'file_id' => $row->file_id,
                'staged_row_id' => $row->id,
                'tenant_id' => $batch->tenant_id,
                'stage_key' => $stageKey,
                'severity' => 'error',
                'status' => 'open',
            ]);
        }
    }
}
