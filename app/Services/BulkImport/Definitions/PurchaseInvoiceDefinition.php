<?php

namespace App\Services\BulkImport\Definitions;

use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\PurchaseInvoiceItemTax;
use App\Services\BulkImport\AllowsRepeatedIdentity;
use App\Services\BulkImport\Concerns\NormalizesImportValues;
use App\Services\BulkImport\Definitions\Concerns\ResolvesImportReferences;
use App\Services\BulkImport\EntityDefinition;

class PurchaseInvoiceDefinition implements EntityDefinition, AllowsRepeatedIdentity
{
    use NormalizesImportValues;
    use ResolvesImportReferences;

    private array $resetInvoices = [];

    public function key(): string { return 'purchase-invoices'; }
    public function permission(): string { return 'import-purchase-invoices'; }
    public function createPermission(): string { return 'create-purchase-invoices'; }
    public function headers(): array { return ['invoice_number', 'invoice_date', 'due_date', 'vendor_email', 'vendor', 'item_sku', 'item_name', 'quantity', 'unit_price', 'discount_percentage', 'tax_names', 'tax_percentage', 'payment_terms', 'warehouse', 'paid_amount', 'status', 'notes']; }
    public function requiredFields(): array { return ['invoice_number', 'invoice_date', 'vendor_email', 'item_sku', 'quantity', 'unit_price']; }
    public function aliases(): array { return ['invoice_number' => ['bill no', 'bill number', 'invoice no'], 'vendor_email' => ['vendor email', 'supplier email', 'email'], 'vendor' => ['vendor name', 'supplier'], 'item_sku' => ['item code', 'sku', 'product code'], 'unit_price' => ['rate', 'price']]; }
    public function example(): array { return ['BILL-1001', date('Y-m-d'), date('Y-m-d', strtotime('+30 days')), 'vendor@example.com', 'ABC Supplies', 'SKU-100', 'Example Product', '2', '70', '0', 'VAT 15%', '15', 'Net 30', 'Main Warehouse', '0', 'posted', 'Imported from Zoho Books']; }
    public function instructions(): array { return ['Use one row per bill line. Repeating invoice_number rows become line items on one purchase invoice.', 'Vendors and items must already exist.']; }
    public function prepare(array $row): array { $row['invoice_number'] = $this->text($row['invoice_number'] ?? ''); $row['status'] = strtolower($this->text($row['status'] ?? 'posted')) ?: 'posted'; return $row; }
    public function identity(array $row): string { return strtolower($this->text($row['invoice_number'] ?? '')); }

    public function validate(array $row, int $tenantId): array
    {
        $errors = [];
        foreach ($this->requiredFields() as $field) {
            if ($this->text($row[$field] ?? '') === '') $errors[] = ucfirst(str_replace('_', ' ', $field)).' is required.';
        }
        if (!$this->dateValue($row['invoice_date'] ?? null)) $errors[] = 'Invoice date is invalid.';
        if (!$this->vendorUser($row, $tenantId)) $errors[] = 'Vendor was not found. Import vendors before purchase invoices.';
        if (!$this->product($row, $tenantId)) $errors[] = 'Item was not found. Import products/services before purchase invoices.';
        if ($this->decimal($row['quantity'] ?? 0) <= 0) $errors[] = 'Quantity must be greater than zero.';
        if (!in_array($row['status'], ['draft', 'posted', 'partial', 'paid', 'overdue'], true)) $errors[] = 'Status is invalid.';
        return array_values(array_unique($errors));
    }

    public function duplicate(array $row, int $tenantId): bool
    {
        return PurchaseInvoice::where('created_by', $tenantId)->whereRaw('LOWER(invoice_number) = ?', [$this->identity($row)])->exists();
    }

    public function import(array $row, string $strategy, int $tenantId, int $actorId): string
    {
        $invoice = PurchaseInvoice::where('created_by', $tenantId)->whereRaw('LOWER(invoice_number) = ?', [$this->identity($row)])->first();
        $preexisting = (bool) ($row['_preexisting_duplicate'] ?? false);
        if ($invoice && $preexisting && $strategy === 'skip') return 'skipped';
        if ($invoice && $preexisting && $strategy === 'update' && !isset($this->resetInvoices[$invoice->id])) {
            $invoice->items()->delete();
            $this->resetInvoices[$invoice->id] = true;
        }
        if (!$invoice) {
            $date = $this->dateValue($row['invoice_date']);
            $invoice = PurchaseInvoice::create([
                'invoice_number' => $this->text($row['invoice_number']),
                'invoice_date' => $date,
                'due_date' => $this->dateValue($row['due_date'] ?? null) ?? $date,
                'vendor_id' => $this->vendorUser($row, $tenantId)->id,
                'warehouse_id' => null,
                'subtotal' => 0,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => 0,
                'paid_amount' => $this->decimal($row['paid_amount'] ?? 0),
                'debit_note_applied' => 0,
                'balance_amount' => 0,
                'status' => $row['status'],
                'payment_terms' => $this->nullableText($row['payment_terms'] ?? null),
                'notes' => $this->nullableText($row['notes'] ?? null),
                'creator_id' => $actorId,
                'created_by' => $tenantId,
            ]);
        }
        [$taxNames, $taxRate] = $this->taxNamesAndRate($row['tax_names'] ?? null, $row['tax_percentage'] ?? 0, $tenantId);
        $item = PurchaseInvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $this->product($row, $tenantId)->id,
            'quantity' => $this->integer($row['quantity'] ?? 1, 1),
            'unit_price' => $this->decimal($row['unit_price'] ?? 0),
            'discount_percentage' => $this->decimal($row['discount_percentage'] ?? 0),
            'tax_percentage' => $taxRate,
        ]);
        foreach ($taxNames as $taxName) {
            PurchaseInvoiceItemTax::create(['item_id' => $item->id, 'tax_name' => $taxName, 'tax_rate' => $taxRate]);
        }
        $this->refreshTotals($invoice);
        return $preexisting ? 'updated' : 'imported';
    }

    private function refreshTotals(PurchaseInvoice $invoice): void
    {
        $items = $invoice->items()->get();
        $subtotal = $items->sum(fn ($item) => (float) $item->quantity * (float) $item->unit_price);
        $discount = $items->sum('discount_amount');
        $tax = $items->sum('tax_amount');
        $total = $items->sum('total_amount');
        $paid = min((float) $invoice->paid_amount, (float) $total);
        $invoice->update(['subtotal' => $subtotal, 'discount_amount' => $discount, 'tax_amount' => $tax, 'total_amount' => $total, 'paid_amount' => $paid, 'balance_amount' => max(0, $total - $paid)]);
    }
}
