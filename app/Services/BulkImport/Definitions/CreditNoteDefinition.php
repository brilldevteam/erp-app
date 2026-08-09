<?php

namespace App\Services\BulkImport\Definitions;

use App\Services\BulkImport\AllowsRepeatedIdentity;
use App\Services\BulkImport\Concerns\NormalizesImportValues;
use App\Services\BulkImport\Definitions\Concerns\ResolvesImportReferences;
use App\Services\BulkImport\EntityDefinition;
use Illuminate\Support\Facades\DB;
use Workdo\Account\Models\CreditNote;
use Workdo\Account\Models\CreditNoteItem;
use Workdo\Account\Models\CreditNoteItemTax;

class CreditNoteDefinition implements EntityDefinition, AllowsRepeatedIdentity
{
    use NormalizesImportValues;
    use ResolvesImportReferences;

    private array $resetNotes = [];

    public function key(): string { return 'credit-notes'; }
    public function permission(): string { return 'import-credit-notes'; }
    public function createPermission(): string { return 'import-credit-notes'; }

    public function headers(): array
    {
        return ['credit_note_number', 'credit_note_date', 'customer_email', 'customer', 'reason', 'item_sku', 'item_name', 'quantity', 'unit_price', 'notes'];
    }

    public function requiredFields(): array
    {
        return ['credit_note_number', 'credit_note_date', 'customer', 'reason', 'item_sku', 'item_name', 'quantity', 'unit_price'];
    }

    public function aliases(): array
    {
        return [
            'credit_note_number' => ['credit note no', 'credit note number'],
            'credit_note_date' => ['date'],
            'customer_email' => ['customer email', 'email'],
            'customer' => ['customer name'],
            'item_sku' => ['item code', 'sku', 'product code'],
            'unit_price' => ['rate', 'price'],
        ];
    }

    public function example(): array
    {
        return ['CN-1001', date('Y-m-d'), 'customer@example.com', 'Example Customer', 'Balance write-off', 'SKU-100', 'Example Product', '1', '100', 'Imported from Zoho Books'];
    }

    public function instructions(): array
    {
        return [
            'Use one row per credit note line. Repeating credit_note_number rows become line items on one credit note.',
            'customer and item_name are required; customer_email is optional since a customer may not have one on file.',
            'Customers and items must already exist.',
            'Tax is calculated automatically from each item\'s configured tax rate, matching manual entry.',
            'Imported credit notes are created already approved (reflecting historical data) and immediately reduce the customer\'s balance. No journal entry is posted for imported rows, same as imported invoices.',
        ];
    }

    public function prepare(array $row): array
    {
        $row['credit_note_number'] = $this->text($row['credit_note_number'] ?? '');
        return $row;
    }

    public function identity(array $row): string
    {
        return strtolower($this->text($row['credit_note_number'] ?? ''));
    }

    public function validate(array $row, int $tenantId): array
    {
        $errors = [];
        foreach ($this->requiredFields() as $field) {
            if ($this->text($row[$field] ?? '') === '') {
                $errors[] = ucfirst(str_replace('_', ' ', $field)).' is required.';
            }
        }

        if (!$this->dateValue($row['credit_note_date'] ?? null)) {
            $errors[] = 'Credit note date is invalid.';
        }
        if (!$this->customerUser($row, $tenantId)) {
            $errors[] = 'Customer was not found. Import customers before credit notes.';
        }
        if (!$this->product($row, $tenantId)) {
            $errors[] = 'Item was not found. Import products/services before credit notes.';
        }
        if ($this->decimal($row['quantity'] ?? 0) <= 0) {
            $errors[] = 'Quantity must be greater than zero.';
        }
        if ($this->decimal($row['unit_price'] ?? -1, -1) < 0) {
            $errors[] = 'Unit price must be zero or greater.';
        }

        return array_values(array_unique($errors));
    }

    public function duplicate(array $row, int $tenantId): bool
    {
        return CreditNote::where('created_by', $tenantId)
            ->whereRaw('LOWER(credit_note_number) = ?', [$this->identity($row)])
            ->exists();
    }

    public function import(array $row, string $strategy, int $tenantId, int $actorId): string
    {
        return DB::transaction(function () use ($row, $strategy, $tenantId, $actorId) {
            $creditNote = CreditNote::where('created_by', $tenantId)
                ->whereRaw('LOWER(credit_note_number) = ?', [$this->identity($row)])
                ->first();
            $preexisting = (bool) ($row['_preexisting_duplicate'] ?? false);

            if ($creditNote && $preexisting && $strategy === 'skip') {
                return 'skipped';
            }

            if ($creditNote && $preexisting && $strategy === 'update' && !isset($this->resetNotes[$creditNote->id])) {
                $creditNote->items()->delete();
                $this->resetNotes[$creditNote->id] = true;
            }

            $customer = $this->customerUser($row, $tenantId);
            $product = $this->product($row, $tenantId);

            $quantity = $this->decimal($row['quantity'] ?? 1);
            $unitPrice = $this->decimal($row['unit_price'] ?? 0);
            $lineSubtotal = $quantity * $unitPrice;
            $taxRate = $product->taxes->sum('rate');
            $lineTax = round($lineSubtotal * $taxRate / 100, 2);
            $lineTotal = $lineSubtotal + $lineTax;

            if (!$creditNote) {
                $creditNote = CreditNote::create([
                    'credit_note_number' => $this->text($row['credit_note_number']),
                    'credit_note_date' => $this->dateValue($row['credit_note_date']),
                    'customer_id' => $customer->id,
                    'reason' => $this->text($row['reason']),
                    'status' => 'approved',
                    'subtotal' => 0,
                    'tax_amount' => 0,
                    'discount_amount' => 0,
                    'total_amount' => 0,
                    'applied_amount' => 0,
                    'balance_amount' => 0,
                    'notes' => $this->nullableText($row['notes'] ?? null),
                    'approved_by' => $actorId,
                    'creator_id' => $actorId,
                    'created_by' => $tenantId,
                ]);
            }

            $item = CreditNoteItem::create([
                'credit_note_id' => $creditNote->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'tax_percentage' => $taxRate,
                'tax_amount' => $lineTax,
                'total_amount' => $lineTotal,
            ]);

            foreach ($product->taxes as $tax) {
                CreditNoteItemTax::create([
                    'item_id' => $item->id,
                    'tax_name' => $tax->tax_name,
                    'tax_rate' => $tax->rate,
                ]);
            }

            $totals = $creditNote->items()->selectRaw('SUM(quantity * unit_price) as subtotal, SUM(tax_amount) as tax_amount, SUM(total_amount) as total_amount')->first();
            $creditNote->update([
                'subtotal' => $totals->subtotal ?? 0,
                'tax_amount' => $totals->tax_amount ?? 0,
                'total_amount' => $totals->total_amount ?? 0,
                'balance_amount' => $totals->total_amount ?? 0,
            ]);

            return $preexisting ? 'updated' : 'imported';
        });
    }
}
