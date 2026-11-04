<?php

namespace App\Services;

use App\Models\InvoiceStatusHistory;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Workdo\Account\Models\CreditNote;
use Workdo\Account\Models\DebitNote;
use Workdo\Account\Services\JournalService;
use Workdo\ProductService\Models\WarehouseStock;

class InvoiceReversionService
{
    public function __construct(private readonly JournalService $journals)
    {
    }

    public function sales(SalesInvoice $invoice, string $reason): SalesInvoice
    {
        return DB::transaction(function () use ($invoice, $reason) {
            $invoice = SalesInvoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $this->assertCommon($invoice);
            if ($invoice->paymentAllocations()->exists()) {
                throw new \RuntimeException(__('Remove all customer payment allocations before reverting this invoice.'));
            }
            if (CreditNote::where('invoice_id', $invoice->id)->exists()) {
                throw new \RuntimeException(__('Remove or cancel linked credit notes before reverting this invoice.'));
            }
            if ($invoice->salesReturns()->exists()) {
                throw new \RuntimeException(__('A sales return is linked to this invoice and must be resolved first.'));
            }

            $this->restoreSalesStock($invoice);
            $journalIds = $this->journals->reverseInvoiceJournals('sales_invoice', $invoice->id, $invoice->invoice_number, $reason);
            return $this->finish($invoice, 'sales_invoice', $reason, $journalIds);
        });
    }

    public function purchase(PurchaseInvoice $invoice, string $reason): PurchaseInvoice
    {
        return DB::transaction(function () use ($invoice, $reason) {
            $invoice = PurchaseInvoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $this->assertCommon($invoice);
            if ($invoice->paymentAllocations()->exists()) {
                throw new \RuntimeException(__('Remove all vendor payment allocations before reverting this bill.'));
            }
            if (DebitNote::where('invoice_id', $invoice->id)->exists()) {
                throw new \RuntimeException(__('Remove or cancel linked vendor credits before reverting this bill.'));
            }
            if ($invoice->purchaseReturns()->exists()) {
                throw new \RuntimeException(__('A purchase return is linked to this bill and must be resolved first.'));
            }

            $this->removePurchaseStock($invoice);
            $journalIds = $this->journals->reverseInvoiceJournals('purchase_invoice', $invoice->id, $invoice->invoice_number, $reason);
            return $this->finish($invoice, 'purchase_invoice', $reason, $journalIds);
        });
    }

    private function assertCommon(Model $invoice): void
    {
        if ((int) $invoice->created_by !== (int) creatorId()) {
            throw new \RuntimeException(__('Permission denied'));
        }
        if ($invoice->status !== 'posted') {
            throw new \RuntimeException(__('Only unpaid posted invoices can be reverted to draft.'));
        }
    }

    private function restoreSalesStock(SalesInvoice $invoice): void
    {
        if ($invoice->type !== 'product') {
            return;
        }
        if (!$invoice->warehouse_id) {
            throw new \RuntimeException(__('This product invoice has no warehouse snapshot and cannot be safely reverted.'));
        }
        foreach ($invoice->items()->whereNotNull('product_id')->get() as $item) {
            $stock = WarehouseStock::where('warehouse_id', $invoice->warehouse_id)
                ->where('product_id', $item->product_id)->lockForUpdate()->first();
            if ($stock) {
                $stock->increment('quantity', $item->quantity);
            } else {
                WarehouseStock::create([
                    'warehouse_id' => $invoice->warehouse_id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                ]);
            }
        }
    }

    private function removePurchaseStock(PurchaseInvoice $invoice): void
    {
        if (!$invoice->warehouse_id) {
            return;
        }
        foreach ($invoice->items()->whereNotNull('product_id')->get() as $item) {
            $stock = WarehouseStock::where('warehouse_id', $invoice->warehouse_id)
                ->where('product_id', $item->product_id)->lockForUpdate()->first();
            if (!$stock || (float) $stock->quantity < (float) $item->quantity) {
                throw new \RuntimeException(__('This bill cannot be reverted because some received stock has already been consumed.'));
            }
            $stock->decrement('quantity', $item->quantity);
        }
    }

    private function finish(Model $invoice, string $type, string $reason, array $journalIds): Model
    {
        InvoiceStatusHistory::create([
            'document_type' => $type,
            'document_id' => $invoice->id,
            'from_status' => 'posted',
            'to_status' => 'draft',
            'action' => 'reverted_to_draft',
            'reason' => $reason,
            'metadata' => [
                'invoice_number' => $invoice->invoice_number,
                'total_amount' => $invoice->total_amount,
                'original_journal_ids' => $journalIds['original'],
                'reversal_journal_ids' => $journalIds['reversals'],
            ],
            'actor_id' => Auth::id(),
            'created_by' => creatorId(),
        ]);
        $invoice->update(['status' => 'draft']);

        return $invoice->fresh();
    }
}
