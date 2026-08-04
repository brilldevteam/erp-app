<?php

namespace App\Services\BulkImport\Definitions;

use App\Models\DocumentTemplate;
use App\Models\Warehouse;
use App\Services\BulkImport\AllowsRepeatedIdentity;
use App\Services\BulkImport\Concerns\NormalizesImportValues;
use App\Services\BulkImport\Definitions\Concerns\ResolvesImportReferences;
use App\Services\BulkImport\EntityDefinition;
use App\Services\DocumentTemplates\DocumentTemplateService;
use Illuminate\Support\Facades\DB;
use Workdo\Quotation\Models\SalesQuotation;
use Workdo\Quotation\Models\SalesQuotationItem;
use Workdo\Quotation\Models\SalesQuotationItemTax;

class QuotationDefinition implements EntityDefinition, AllowsRepeatedIdentity
{
    use NormalizesImportValues;
    use ResolvesImportReferences;

    private array $resetQuotations = [];

    public function key(): string { return 'quotations'; }
    public function permission(): string { return 'import-quotations'; }
    public function createPermission(): string { return 'create-quotations'; }
    public function headers(): array { return ['quotation_number', 'quotation_date', 'due_date', 'customer_email', 'customer', 'item_sku', 'item_name', 'quantity', 'unit_price', 'discount_percentage', 'tax_names', 'tax_percentage', 'payment_terms', 'warehouse', 'status', 'notes']; }
    public function requiredFields(): array { return ['quotation_number', 'quotation_date', 'due_date', 'customer_email', 'item_sku', 'quantity', 'unit_price']; }
    public function aliases(): array { return ['quotation_number' => ['quote no', 'quote number', 'quotation no'], 'quotation_date' => ['date', 'quote date'], 'due_date' => ['expiry date', 'valid until'], 'customer_email' => ['customer email', 'email'], 'customer' => ['customer name', 'customer'], 'item_sku' => ['item code', 'sku', 'product code'], 'unit_price' => ['rate', 'price'], 'tax_names' => ['tax', 'tax name']]; }
    public function example(): array { return ['QT-1001', date('Y-m-d'), date('Y-m-d', strtotime('+14 days')), 'customer@example.com', 'Example Customer', 'SKU-100', 'Example Product', '2', '100', '0', 'VAT 15%', '15', 'Net 14', 'Main Warehouse', 'draft', 'Imported from Zoho Books']; }
    public function instructions(): array { return ['Use one row per quotation line. Repeating quotation_number rows become line items on one quotation.', 'Customers and items must already exist.', 'status accepts draft, sent, accepted, or rejected.', 'Warehouse is optional and must match an existing active warehouse when supplied.']; }

    public function prepare(array $row): array
    {
        $row['quotation_number'] = $this->text($row['quotation_number'] ?? '');
        $row['status'] = strtolower($this->text($row['status'] ?? 'draft')) ?: 'draft';

        return $row;
    }

    public function identity(array $row): string
    {
        return strtolower($this->text($row['quotation_number'] ?? ''));
    }

    public function validate(array $row, int $tenantId): array
    {
        $errors = [];
        foreach ($this->requiredFields() as $field) {
            if ($this->text($row[$field] ?? '') === '') {
                $errors[] = ucfirst(str_replace('_', ' ', $field)).' is required.';
            }
        }

        if (!$this->dateValue($row['quotation_date'] ?? null)) $errors[] = 'Quotation date is invalid.';
        if (!$this->dateValue($row['due_date'] ?? null)) $errors[] = 'Due date is invalid.';
        if (!$this->customerUser($row, $tenantId)) $errors[] = 'Customer was not found. Import customers before quotations.';
        if (!$this->product($row, $tenantId)) $errors[] = 'Item was not found. Import products/services before quotations.';
        if ($this->decimal($row['quantity'] ?? 0) <= 0) $errors[] = 'Quantity must be greater than zero.';
        if ($this->decimal($row['unit_price'] ?? -1, -1) < 0) $errors[] = 'Unit price must be zero or greater.';
        if (!in_array($row['status'], ['draft', 'sent', 'accepted', 'rejected'], true)) $errors[] = 'Status is invalid.';
        if (($warehouse = $this->nullableText($row['warehouse'] ?? null)) && !$this->warehouse($warehouse, $tenantId)) {
            $errors[] = 'Warehouse was not found for this company.';
        }

        return array_values(array_unique($errors));
    }

    public function duplicate(array $row, int $tenantId): bool
    {
        return SalesQuotation::where('created_by', $tenantId)
            ->whereRaw('LOWER(quotation_number) = ?', [$this->identity($row)])
            ->exists();
    }

    public function import(array $row, string $strategy, int $tenantId, int $actorId): string
    {
        return DB::transaction(function () use ($row, $strategy, $tenantId, $actorId) {
            $quotation = SalesQuotation::where('created_by', $tenantId)
                ->whereRaw('LOWER(quotation_number) = ?', [$this->identity($row)])
                ->first();
            $preexisting = (bool) ($row['_preexisting_duplicate'] ?? false);

            if ($quotation && $preexisting && $strategy === 'skip') {
                return 'skipped';
            }

            if ($quotation && $preexisting && $strategy === 'update' && !isset($this->resetQuotations[$quotation->id])) {
                $quotation->items()->delete();
                $this->resetQuotations[$quotation->id] = true;
            }

            if (!$quotation) {
                $quotation = SalesQuotation::create([
                    'quotation_number' => $this->text($row['quotation_number']),
                    'quotation_date' => $this->dateValue($row['quotation_date']),
                    'due_date' => $this->dateValue($row['due_date']),
                    'customer_id' => $this->customerUser($row, $tenantId)->id,
                    'warehouse_id' => $this->warehouse($row['warehouse'] ?? null, $tenantId)?->id,
                    'subtotal' => 0,
                    'tax_amount' => 0,
                    'discount_amount' => 0,
                    'total_amount' => 0,
                    'status' => $row['status'],
                    'payment_terms' => $this->nullableText($row['payment_terms'] ?? null),
                    'notes' => $this->nullableText($row['notes'] ?? null),
                    'document_template_id' => app(DocumentTemplateService::class)
                        ->resolveForDocument(DocumentTemplate::TYPE_QUOTATION, $tenantId, null)
                        ->id,
                    'creator_id' => $actorId,
                    'created_by' => $tenantId,
                ]);
            }

            [$taxNames, $taxRate] = $this->taxNamesAndRate($row['tax_names'] ?? null, $row['tax_percentage'] ?? 0, $tenantId);
            $item = SalesQuotationItem::create([
                'quotation_id' => $quotation->id,
                'product_id' => $this->product($row, $tenantId)->id,
                'quantity' => $this->decimal($row['quantity'] ?? 1),
                'unit_price' => $this->decimal($row['unit_price'] ?? 0),
                'discount_percentage' => $this->decimal($row['discount_percentage'] ?? 0),
                'tax_percentage' => $taxRate,
            ]);

            foreach ($taxNames as $taxName) {
                SalesQuotationItemTax::create([
                    'item_id' => $item->id,
                    'tax_name' => $taxName,
                    'tax_rate' => $taxRate,
                ]);
            }

            $this->refreshTotals($quotation);

            return $preexisting ? 'updated' : 'imported';
        });
    }

    private function refreshTotals(SalesQuotation $quotation): void
    {
        $items = $quotation->items()->get();
        $quotation->update([
            'subtotal' => $items->sum(fn ($item) => (float) $item->quantity * (float) $item->unit_price),
            'discount_amount' => $items->sum('discount_amount'),
            'tax_amount' => $items->sum('tax_amount'),
            'total_amount' => $items->sum('total_amount'),
        ]);
    }

    private function warehouse(mixed $name, int $tenantId): ?Warehouse
    {
        $name = strtolower($this->text($name));
        if ($name === '') {
            return null;
        }

        return Warehouse::where('created_by', $tenantId)
            ->where('is_active', true)
            ->whereRaw('LOWER(name) = ?', [$name])
            ->first();
    }
}
