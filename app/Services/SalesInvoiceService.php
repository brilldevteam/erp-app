<?php

namespace App\Services;

use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\SalesInvoiceItemTax;
use App\Models\DocumentTemplate;
use App\Services\DocumentTemplates\DocumentTemplateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Workdo\Quotation\Models\SalesQuotation;

class SalesInvoiceService
{
    public function __construct(private readonly DocumentTemplateService $templates)
    {
    }

    public function create(array $data, int $userId, int $creatorId): SalesInvoice
    {
        return DB::transaction(function () use ($data, $userId, $creatorId) {
            $quotation = $this->lockQuotation($data['quotation_id'] ?? null, $creatorId);
            $template = $this->templates->resolveForDocument(
                DocumentTemplate::TYPE_INVOICE,
                $creatorId,
                $data['document_template_id'] ?? null
            );
            $totals = $this->calculateTotals($data['items']);

            $invoice = new SalesInvoice();
            $invoice->quotation_id = $quotation?->id;
            $invoice->document_template_id = $template->id;
            $invoice->invoice_date = $data['invoice_date'];
            $invoice->due_date = $data['due_date'];
            $invoice->customer_id = $data['customer_id'];
            $invoice->warehouse_id = $data['type'] === 'product'
                ? ($data['warehouse_id'] ?? null)
                : null;
            $invoice->type = $data['type'];
            $invoice->payment_terms = $data['payment_terms'] ?? null;
            $invoice->notes = $data['notes'] ?? null;
            $invoice->subtotal = $totals['subtotal'];
            $invoice->tax_amount = $totals['tax_amount'];
            $invoice->discount_amount = $totals['discount_amount'];
            $invoice->total_amount = $totals['total_amount'];
            $invoice->balance_amount = $totals['total_amount'];
            $invoice->creator_id = $userId;
            $invoice->created_by = $creatorId;
            $invoice->save();

            $this->createItems($invoice->id, $data['items']);

            if ($quotation) {
                $quotation->update([
                    'converted_to_invoice' => true,
                    'invoice_id' => $invoice->id,
                ]);
            }

            return $invoice->load([
                'customer',
                'warehouse',
                'items.taxes',
                'quotation',
            ]);
        });
    }

    private function lockQuotation(?int $quotationId, int $creatorId): ?SalesQuotation
    {
        if (!$quotationId) {
            return null;
        }

        $quotation = SalesQuotation::query()
            ->whereKey($quotationId)
            ->where('created_by', $creatorId)
            ->lockForUpdate()
            ->first();

        if (!$quotation) {
            throw ValidationException::withMessages([
                'quotation_id' => __('The selected quotation is invalid.'),
            ]);
        }

        if ($quotation->converted_to_invoice || $quotation->invoice_id) {
            throw ValidationException::withMessages([
                'quotation_id' => __('This quotation has already been converted to invoice.'),
            ]);
        }

        if (!$quotation->canConvertToInvoice()) {
            throw ValidationException::withMessages([
                'quotation_id' => __('This quotation cannot be converted to invoice.'),
            ]);
        }

        return $quotation;
    }

    private function calculateTotals(array $items): array
    {
        $subtotal = 0;
        $totalTax = 0;
        $totalDiscount = 0;

        foreach ($items as $item) {
            $lineTotal = $item['quantity'] * $item['unit_price'];
            $discountAmount = ($lineTotal * ($item['discount_percentage'] ?? 0)) / 100;
            $afterDiscount = $lineTotal - $discountAmount;
            $taxAmount = ($afterDiscount * ($item['tax_percentage'] ?? 0)) / 100;

            $subtotal += $lineTotal;
            $totalDiscount += $discountAmount;
            $totalTax += $taxAmount;
        }

        return [
            'subtotal' => $subtotal,
            'tax_amount' => $totalTax,
            'discount_amount' => $totalDiscount,
            'total_amount' => $subtotal + $totalTax - $totalDiscount,
        ];
    }

    private function createItems(int $invoiceId, array $items): void
    {
        foreach ($items as $itemData) {
            $item = new SalesInvoiceItem();
            $item->invoice_id = $invoiceId;
            $item->product_id = $itemData['product_id'];
            $item->quantity = $itemData['quantity'];
            $item->unit_price = $itemData['unit_price'];
            $item->discount_percentage = $itemData['discount_percentage'] ?? 0;
            $item->tax_percentage = $itemData['tax_percentage'] ?? 0;
            $item->save();

            foreach ($itemData['taxes'] ?? [] as $tax) {
                $itemTax = new SalesInvoiceItemTax();
                $itemTax->item_id = $item->id;
                $itemTax->tax_name = $tax['tax_name'];
                $itemTax->tax_rate = $tax['tax_rate'] ?? $tax['rate'] ?? 0;
                $itemTax->save();
            }
        }
    }
}
