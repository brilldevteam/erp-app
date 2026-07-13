<?php

namespace App\Services\BulkImport\Definitions;

use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\SalesInvoiceItemTax;
use App\Services\BulkImport\AllowsRepeatedIdentity;
use App\Services\BulkImport\Concerns\NormalizesImportValues;
use App\Services\BulkImport\Definitions\Concerns\ResolvesImportReferences;
use App\Services\BulkImport\EntityDefinition;

class SalesInvoiceDefinition implements EntityDefinition, AllowsRepeatedIdentity
{
    use NormalizesImportValues;
    use ResolvesImportReferences;

    private array $resetInvoices = [];

    public function key(): string { return 'sales-invoices'; }
    public function permission(): string { return 'import-sales-invoices'; }
    public function createPermission(): string { return 'create-sales-invoices'; }
    public function headers(): array { return ['invoice_number', 'invoice_date', 'due_date', 'customer_email', 'customer', 'item_sku', 'item_name', 'quantity', 'unit_price', 'discount_percentage', 'tax_names', 'tax_percentage', 'payment_terms', 'warehouse', 'paid_amount', 'status', 'notes']; }
    public function requiredFields(): array { return ['invoice_number', 'invoice_date', 'customer_email', 'item_sku', 'quantity', 'unit_price']; }
    public function aliases(): array { return ['invoice_number' => ['invoice no', 'invoice number'], 'invoice_date' => ['date'], 'due_date' => ['due date'], 'customer_email' => ['customer email', 'email'], 'customer' => ['customer name', 'customer'], 'item_sku' => ['item code', 'sku', 'product code'], 'unit_price' => ['rate', 'price'], 'tax_names' => ['tax', 'tax name']]; }
    public function example(): array { return ['INV-1001', date('Y-m-d'), date('Y-m-d', strtotime('+30 days')), 'customer@example.com', 'Example Customer', 'SKU-100', 'Example Product', '2', '100', '0', 'VAT 15%', '15', 'Net 30', 'Main Warehouse', '0', 'posted', 'Imported from Zoho Books']; }
    public function instructions(): array { return ['Use one row per invoice line. Repeating invoice_number rows become line items on one invoice.', 'Customers and items must already exist.', 'status accepts draft, posted, partial, paid, or overdue.']; }
    public function prepare(array $row): array { $row['invoice_number'] = $this->text($row['invoice_number'] ?? ''); $row['status'] = strtolower($this->text($row['status'] ?? 'posted')) ?: 'posted'; return $row; }
    public function identity(array $row): string { return strtolower($this->text($row['invoice_number'] ?? '')); }

    public function validate(array $row, int $tenantId): array
    {
        $errors = [];
        foreach ($this->requiredFields() as $field) {
            if ($this->text($row[$field] ?? '') === '') $errors[] = ucfirst(str_replace('_', ' ', $field)).' is required.';
        }
        if (!$this->dateValue($row['invoice_date'] ?? null)) $errors[] = 'Invoice date is invalid.';
        if ($this->nullableText($row['due_date'] ?? null) && !$this->dateValue($row['due_date'])) $errors[] = 'Due date is invalid.';
        if (!$this->customerUser($row, $tenantId)) $errors[] = 'Customer was not found. Import customers before invoices.';
        if (!$this->product($row, $tenantId)) $errors[] = 'Item was not found. Import products/services before invoices.';
        if ($this->decimal($row['quantity'] ?? 0) <= 0) $errors[] = 'Quantity must be greater than zero.';
        if ($this->decimal($row['unit_price'] ?? -1, -1) < 0) $errors[] = 'Unit price must be zero or greater.';
        if (!in_array($row['status'], ['draft', 'posted', 'partial', 'paid', 'overdue'], true)) $errors[] = 'Status is invalid.';
        return array_values(array_unique($errors));
    }

    public function duplicate(array $row, int $tenantId): bool
    {
        return SalesInvoice::where('created_by', $tenantId)->whereRaw('LOWER(invoice_number) = ?', [$this->identity($row)])->exists();
    }

    public function import(array $row, string $strategy, int $tenantId, int $actorId): string
    {
        $invoice = SalesInvoice::where('created_by', $tenantId)->whereRaw('LOWER(invoice_number) = ?', [$this->identity($row)])->first();
        $preexisting = (bool) ($row['_preexisting_duplicate'] ?? false);
        if ($invoice && $preexisting && $strategy === 'skip') return 'skipped';

        if ($invoice && $preexisting && $strategy === 'update' && !isset($this->resetInvoices[$invoice->id])) {
            $invoice->items()->delete();
            $this->resetInvoices[$invoice->id] = true;
        }

        if (!$invoice) {
            $date = $this->dateValue($row['invoice_date']);
            $invoice = SalesInvoice::create([
                'invoice_number' => $this->text($row['invoice_number']),
                'invoice_date' => $date,
                'due_date' => $this->dateValue($row['due_date'] ?? null) ?? $date,
                'customer_id' => $this->customerUser($row, $tenantId)->id,
                'warehouse_id' => null,
                'subtotal' => 0,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => 0,
                'paid_amount' => $this->decimal($row['paid_amount'] ?? 0),
                'balance_amount' => 0,
                'status' => $row['status'],
                'type' => 'product',
                'payment_terms' => $this->nullableText($row['payment_terms'] ?? null),
                'notes' => $this->nullableText($row['notes'] ?? null),
                'creator_id' => $actorId,
                'created_by' => $tenantId,
            ]);
        }

        [$taxNames, $taxRate] = $this->taxNamesAndRate($row['tax_names'] ?? null, $row['tax_percentage'] ?? 0, $tenantId);
        $item = SalesInvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $this->product($row, $tenantId)->id,
            'quantity' => $this->integer($row['quantity'] ?? 1, 1),
            'unit_price' => $this->decimal($row['unit_price'] ?? 0),
            'discount_percentage' => $this->decimal($row['discount_percentage'] ?? 0),
            'tax_percentage' => $taxRate,
            'creator_id' => $actorId,
            'created_by' => $tenantId,
        ]);
        foreach ($taxNames as $taxName) {
            SalesInvoiceItemTax::create(['item_id' => $item->id, 'tax_name' => $taxName, 'tax_rate' => $taxRate]);
        }
        $this->refreshTotals($invoice);

        return $preexisting ? 'updated' : 'imported';
    }

    private function refreshTotals(SalesInvoice $invoice): void
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
