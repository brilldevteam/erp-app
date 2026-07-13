<?php

namespace App\Services\BulkImport\Definitions;

use App\Services\BulkImport\Concerns\NormalizesImportValues;
use App\Services\BulkImport\Definitions\Concerns\ResolvesImportReferences;
use App\Services\BulkImport\EntityDefinition;
use Workdo\Account\Events\CreateRevenue;
use Workdo\Account\Models\Revenue;

class RevenueDefinition implements EntityDefinition
{
    use NormalizesImportValues;
    use ResolvesImportReferences;

    public function key(): string { return 'revenues'; }
    public function permission(): string { return 'import-revenues'; }
    public function createPermission(): string { return 'create-revenues'; }
    public function headers(): array { return ['revenue_number', 'revenue_date', 'category', 'bank_account', 'account_number', 'chart_of_account', 'amount', 'description', 'reference_number', 'status']; }
    public function requiredFields(): array { return ['revenue_number', 'revenue_date', 'category', 'bank_account', 'amount']; }
    public function aliases(): array { return ['revenue_number' => ['income no', 'revenue no'], 'revenue_date' => ['date'], 'category' => ['income category'], 'amount' => ['total']]; }
    public function example(): array { return ['REV-ZOHO-1001', date('Y-m-d'), 'Sales', 'Main Bank', '100200300', '4000', '250', 'Other income', 'REF-1', 'draft']; }
    public function instructions(): array { return ['revenue_number is the duplicate key.', 'Category and bank account must already exist.']; }
    public function prepare(array $row): array { $row['revenue_number'] = $this->text($row['revenue_number'] ?? ''); $row['status'] = strtolower($this->text($row['status'] ?? 'draft')) ?: 'draft'; return $row; }
    public function identity(array $row): string { return strtolower($this->text($row['revenue_number'] ?? '')); }
    public function validate(array $row, int $tenantId): array
    {
        $errors = [];
        foreach ($this->requiredFields() as $field) if ($this->text($row[$field] ?? '') === '') $errors[] = ucfirst(str_replace('_', ' ', $field)).' is required.';
        if (!$this->dateValue($row['revenue_date'] ?? null)) $errors[] = 'Revenue date is invalid.';
        if (!$this->revenueCategory($row, $tenantId)) $errors[] = 'Revenue category was not found.';
        if (!$this->bankAccount($row, $tenantId)) $errors[] = 'Bank account was not found.';
        if ($this->nullableText($row['chart_of_account'] ?? null) && !$this->chartAccount($row['chart_of_account'], $tenantId)) $errors[] = 'Chart of account was not found.';
        if ($this->decimal($row['amount'] ?? 0) <= 0) $errors[] = 'Amount must be greater than zero.';
        if (!in_array($row['status'], ['draft', 'approved', 'posted'], true)) $errors[] = 'Status is invalid.';
        return $errors;
    }
    public function duplicate(array $row, int $tenantId): bool { return Revenue::where('created_by', $tenantId)->whereRaw('LOWER(revenue_number) = ?', [$this->identity($row)])->exists(); }
    public function import(array $row, string $strategy, int $tenantId, int $actorId): string
    {
        $revenue = Revenue::where('created_by', $tenantId)->whereRaw('LOWER(revenue_number) = ?', [$this->identity($row)])->first();
        if ($revenue && $strategy === 'skip') return 'skipped';
        $values = ['revenue_number' => $this->text($row['revenue_number']), 'revenue_date' => $this->dateValue($row['revenue_date']), 'category_id' => $this->revenueCategory($row, $tenantId)->id, 'bank_account_id' => $this->bankAccount($row, $tenantId)->id, 'chart_of_account_id' => $this->chartAccount($row['chart_of_account'] ?? null, $tenantId)?->id, 'amount' => $this->decimal($row['amount']), 'description' => $this->text($row['description'] ?? ''), 'reference_number' => $this->nullableText($row['reference_number'] ?? null), 'status' => $row['status']];
        if ($revenue) { $revenue->update($values); return 'updated'; }
        $revenue = Revenue::create($values + ['creator_id' => $actorId, 'created_by' => $tenantId]);
        CreateRevenue::dispatch(request(), $revenue);
        return 'imported';
    }
}
