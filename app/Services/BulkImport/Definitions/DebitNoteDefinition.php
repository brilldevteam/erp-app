<?php

namespace App\Services\BulkImport\Definitions;

use App\Services\BulkImport\AllowsRepeatedIdentity;
use App\Services\BulkImport\Concerns\NormalizesImportValues;
use App\Services\BulkImport\Definitions\Concerns\ResolvesImportReferences;
use App\Services\BulkImport\EntityDefinition;
use Illuminate\Support\Facades\DB;
use Workdo\Account\Models\DebitNote;
use Workdo\Account\Models\DebitNoteItem;
use Workdo\Account\Models\DebitNoteItemTax;

class DebitNoteDefinition implements EntityDefinition, AllowsRepeatedIdentity
{
    use NormalizesImportValues;
    use ResolvesImportReferences;

    private array $resetNotes = [];

    public function key(): string { return 'debit-notes'; }
    public function permission(): string { return 'import-debit-notes'; }
    public function createPermission(): string { return 'create-debit-notes'; }

    public function headers(): array
    {
        return ['vendor_credit_number', 'vendor_credit_date', 'vendor_email', 'vendor', 'reason', 'item_sku', 'item_name', 'quantity', 'unit_price', 'notes'];
    }

    public function requiredFields(): array
    {
        return ['vendor_credit_number', 'vendor_credit_date', 'vendor', 'reason', 'item_sku', 'item_name', 'quantity', 'unit_price'];
    }

    public function aliases(): array
    {
        return [
            'vendor_credit_number' => ['vendor credit no', 'vendor credit number', 'debit note no', 'debit note number'],
            'vendor_credit_date' => ['date', 'debit note date'],
            'vendor_email' => ['vendor email', 'supplier email', 'email'],
            'vendor' => ['vendor name', 'supplier'],
            'item_sku' => ['item code', 'sku', 'product code'],
            'unit_price' => ['rate', 'price'],
        ];
    }

    public function example(): array
    {
        return ['VC-1001', date('Y-m-d'), 'vendor@example.com', 'ABC Supplies', 'Balance write-off', 'SKU-100', 'Example Product', '1', '70', 'Imported from Zoho Books'];
    }

    public function instructions(): array
    {
        return [
            'Use one row per vendor credit line. Repeating vendor_credit_number rows become line items on one vendor credit.',
            'vendor and item_name are required; vendor_email is optional since a vendor may not have one on file.',
            'Vendors and items must already exist.',
            'Tax is calculated automatically from each item\'s configured tax rate, matching manual entry.',
            'Imported vendor credits are created already approved (reflecting historical data) and immediately reduce the vendor\'s balance. No journal entry is posted for imported rows, same as imported bills.',
        ];
    }

    public function prepare(array $row): array
    {
        $row['vendor_credit_number'] = $this->text($row['vendor_credit_number'] ?? '');
        return $row;
    }

    public function identity(array $row): string
    {
        return strtolower($this->text($row['vendor_credit_number'] ?? ''));
    }

    public function validate(array $row, int $tenantId): array
    {
        $errors = [];
        foreach ($this->requiredFields() as $field) {
            if ($this->text($row[$field] ?? '') === '') {
                $errors[] = ucfirst(str_replace('_', ' ', $field)).' is required.';
            }
        }

        if (!$this->dateValue($row['vendor_credit_date'] ?? null)) {
            $errors[] = 'Vendor credit date is invalid.';
        }
        if (!$this->vendorUser($row, $tenantId)) {
            $errors[] = 'Vendor was not found. Import vendors before vendor credits.';
        }
        if (!$this->product($row, $tenantId)) {
            $errors[] = 'Item was not found. Import products/services before vendor credits.';
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
        return DebitNote::where('created_by', $tenantId)
            ->whereRaw('LOWER(debit_note_number) = ?', [$this->identity($row)])
            ->exists();
    }

    public function import(array $row, string $strategy, int $tenantId, int $actorId): string
    {
        return DB::transaction(function () use ($row, $strategy, $tenantId, $actorId) {
            $debitNote = DebitNote::where('created_by', $tenantId)
                ->whereRaw('LOWER(debit_note_number) = ?', [$this->identity($row)])
                ->first();
            $preexisting = (bool) ($row['_preexisting_duplicate'] ?? false);

            if ($debitNote && $preexisting && $strategy === 'skip') {
                return 'skipped';
            }

            if ($debitNote && $preexisting && $strategy === 'update' && !isset($this->resetNotes[$debitNote->id])) {
                $debitNote->items()->delete();
                $this->resetNotes[$debitNote->id] = true;
            }

            $vendor = $this->vendorUser($row, $tenantId);
            $product = $this->product($row, $tenantId);

            $quantity = $this->decimal($row['quantity'] ?? 1);
            $unitPrice = $this->decimal($row['unit_price'] ?? 0);
            $lineSubtotal = $quantity * $unitPrice;
            $taxRate = $product->taxes->sum('rate');
            $lineTax = round($lineSubtotal * $taxRate / 100, 2);
            $lineTotal = $lineSubtotal + $lineTax;

            if (!$debitNote) {
                $debitNote = DebitNote::create([
                    'debit_note_number' => $this->text($row['vendor_credit_number']),
                    'debit_note_date' => $this->dateValue($row['vendor_credit_date']),
                    'vendor_id' => $vendor->id,
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

            $item = DebitNoteItem::create([
                'debit_note_id' => $debitNote->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'tax_percentage' => $taxRate,
                'tax_amount' => $lineTax,
                'total_amount' => $lineTotal,
                'creator_id' => $actorId,
                'created_by' => $tenantId,
            ]);

            foreach ($product->taxes as $tax) {
                DebitNoteItemTax::create([
                    'item_id' => $item->id,
                    'tax_name' => $tax->tax_name,
                    'tax_rate' => $tax->rate,
                ]);
            }

            $totals = $debitNote->items()->selectRaw('SUM(quantity * unit_price) as subtotal, SUM(tax_amount) as tax_amount, SUM(total_amount) as total_amount')->first();
            $debitNote->update([
                'subtotal' => $totals->subtotal ?? 0,
                'tax_amount' => $totals->tax_amount ?? 0,
                'total_amount' => $totals->total_amount ?? 0,
                'balance_amount' => $totals->total_amount ?? 0,
            ]);

            return $preexisting ? 'updated' : 'imported';
        });
    }
}
