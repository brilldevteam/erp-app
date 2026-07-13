<?php

namespace App\Services\BulkImport\Definitions;

use App\Services\BulkImport\Concerns\NormalizesImportValues;
use App\Services\BulkImport\Definitions\Concerns\ResolvesImportReferences;
use App\Services\BulkImport\EntityDefinition;
use Workdo\ProductService\Models\ProductServiceTax;

class ProductServiceTaxDefinition implements EntityDefinition
{
    use NormalizesImportValues;
    use ResolvesImportReferences;

    public function key(): string { return 'product-service-taxes'; }
    public function permission(): string { return 'import-product-service-taxes'; }
    public function createPermission(): string { return 'create-product-service-taxes'; }
    public function headers(): array { return ['tax_name', 'rate']; }
    public function requiredFields(): array { return ['tax_name', 'rate']; }
    public function aliases(): array { return ['tax_name' => ['tax', 'name'], 'rate' => ['tax_rate', 'percentage', 'tax_percentage']]; }
    public function example(): array { return ['VAT 15%', '15']; }
    public function instructions(): array { return ['tax_name is the duplicate key.', 'rate must be between 0 and 100.']; }
    public function prepare(array $row): array { $row['tax_name'] = $this->text($row['tax_name'] ?? ''); return $row; }
    public function identity(array $row): string { return strtolower($this->text($row['tax_name'] ?? '')); }

    public function validate(array $row, int $tenantId): array
    {
        $errors = [];
        if ($this->identity($row) === '') $errors[] = 'Tax name is required.';
        if (!is_numeric($row['rate'] ?? null) || $this->decimal($row['rate']) < 0 || $this->decimal($row['rate']) > 100) {
            $errors[] = 'Rate must be between 0 and 100.';
        }
        return $errors;
    }

    public function duplicate(array $row, int $tenantId): bool
    {
        return ProductServiceTax::where('created_by', $tenantId)
            ->whereRaw('LOWER(tax_name) = ?', [$this->identity($row)])
            ->exists();
    }

    public function import(array $row, string $strategy, int $tenantId, int $actorId): string
    {
        $tax = ProductServiceTax::where('created_by', $tenantId)
            ->whereRaw('LOWER(tax_name) = ?', [$this->identity($row)])
            ->first();

        if ($tax && $strategy === 'skip') return 'skipped';

        $values = ['tax_name' => $this->text($row['tax_name']), 'rate' => $this->decimal($row['rate'])];
        if ($tax) {
            $tax->update($values);
            return 'updated';
        }

        ProductServiceTax::create($values + ['creator_id' => $actorId, 'created_by' => $tenantId]);
        return 'imported';
    }
}
