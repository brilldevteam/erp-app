<?php

namespace App\Jobs;

use App\Models\ZohoMigrationBatch;
use App\Models\ZohoMigrationEvent;
use App\Services\ZohoMigration\ZohoBankAccountService;
use App\Services\ZohoMigration\ZohoBankActivityService;
use App\Services\ZohoMigration\ZohoBillService;
use App\Services\ZohoMigration\ZohoChartOfAccountsService;
use App\Services\ZohoMigration\ZohoCreditApplicationService;
use App\Services\ZohoMigration\ZohoCustomerCreditService;
use App\Services\ZohoMigration\ZohoCustomerPaymentService;
use App\Services\ZohoMigration\ZohoCustomerService;
use App\Services\ZohoMigration\ZohoExpenseService;
use App\Services\ZohoMigration\ZohoIncomeExpenseCategoryService;
use App\Services\ZohoMigration\ZohoManualJournalService;
use App\Services\ZohoMigration\ZohoQuotationService;
use App\Services\ZohoMigration\ZohoReconciliationService;
use App\Services\ZohoMigration\ZohoSalesInvoiceService;
use App\Services\ZohoMigration\ZohoVendorCreditService;
use App\Services\ZohoMigration\ZohoVendorPaymentService;
use App\Services\ZohoMigration\ZohoVendorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ImportZohoMigrationStage implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1200;

    public function __construct(
        public readonly int $batchId,
        public readonly string $stageKey,
        public readonly int $actorId,
    ) {
        $this->onQueue('imports');
    }

    public function handle(
        ZohoChartOfAccountsService $chartOfAccounts,
        ZohoCustomerService $customers,
        ZohoVendorService $vendors,
        ZohoBankAccountService $bankAccounts,
        ZohoIncomeExpenseCategoryService $incomeExpenseCategories,
        ZohoQuotationService $quotations,
        ZohoSalesInvoiceService $salesInvoices,
        ZohoBillService $bills,
        ZohoCustomerCreditService $customerCredits,
        ZohoVendorCreditService $vendorCredits,
        ZohoCustomerPaymentService $customerPayments,
        ZohoVendorPaymentService $vendorPayments,
        ZohoExpenseService $expenses,
        ZohoBankActivityService $bankActivity,
        ZohoManualJournalService $manualJournals,
        ZohoCreditApplicationService $creditApplications,
        ZohoReconciliationService $reconciliation,
    ): void {
        $batch = ZohoMigrationBatch::findOrFail($this->batchId);

        try {
            match ($this->stageKey) {
                'chart_of_accounts' => $chartOfAccounts->import($batch, $this->actorId),
                'customers' => $customers->import($batch, $this->actorId),
                'vendors' => $vendors->import($batch, $this->actorId),
                'payment_accounts' => $bankAccounts->import($batch, $this->actorId),
                'income_expense_categories' => $incomeExpenseCategories->import($batch, $this->actorId),
                'quotations' => $quotations->import($batch, $this->actorId),
                'sales_invoices' => $salesInvoices->import($batch, $this->actorId),
                'purchase_invoices' => $bills->import($batch, $this->actorId),
                'credit_notes' => $customerCredits->import($batch, $this->actorId),
                'vendor_credits' => $vendorCredits->import($batch, $this->actorId),
                'customer_payments' => $customerPayments->import($batch, $this->actorId),
                'vendor_payments' => $vendorPayments->import($batch, $this->actorId),
                'income_expenses' => $expenses->import($batch, $this->actorId),
                'bank_activity' => $bankActivity->import($batch, $this->actorId),
                'accounting' => $manualJournals->import($batch, $this->actorId),
                'credit_applications' => $creditApplications->import($batch, $this->actorId),
                'reconciliation' => $reconciliation->import($batch, $this->actorId),
                default => throw new \RuntimeException('This migration stage is not executable yet.'),
            };
        } catch (\Throwable $exception) {
            Log::warning('Zoho migration stage import failed.', [
                'batch_id' => $this->batchId,
                'stage_key' => $this->stageKey,
                'exception' => $exception::class,
            ]);
            $batch->stages()->where('stage_key', $this->stageKey)->update(['status' => 'failed']);
            ZohoMigrationEvent::create([
                'batch_id' => $batch->id,
                'tenant_id' => $batch->tenant_id,
                'user_id' => $this->actorId,
                'stage_key' => $this->stageKey,
                'event_type' => 'stage_import_failed',
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
