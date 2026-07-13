<?php

namespace App\Services\BulkImport\Definitions;

use App\Services\BulkImport\Concerns\NormalizesImportValues;
use App\Services\BulkImport\Definitions\Concerns\ResolvesImportReferences;
use App\Services\BulkImport\EntityDefinition;
use Workdo\Account\Events\CreateExpense;
use Workdo\Account\Models\Expense;

class ExpenseDefinition implements EntityDefinition
{
    use NormalizesImportValues;
    use ResolvesImportReferences;

    public function key(): string { return 'expenses'; }
    public function permission(): string { return 'import-expenses'; }
    public function createPermission(): string { return 'create-expenses'; }
    public function headers(): array { return ['expense_number', 'expense_date', 'category', 'bank_account', 'account_number', 'chart_of_account', 'amount', 'description', 'reference_number', 'status']; }
    public function requiredFields(): array { return ['expense_number', 'expense_date', 'category', 'bank_account', 'amount']; }
    public function aliases(): array { return ['expense_number' => ['expense no'], 'expense_date' => ['date'], 'category' => ['expense category'], 'amount' => ['total']]; }
    public function example(): array { return ['EXP-ZOHO-1001', date('Y-m-d'), 'Office Supplies', 'Main Bank', '100200300', '5000', '75', 'Stationery', 'REF-1', 'draft']; }
    public function instructions(): array { return ['expense_number is the duplicate key.', 'Category and bank account must already exist.']; }
    public function prepare(array $row): array { $row['expense_number'] = $this->text($row['expense_number'] ?? ''); $row['status'] = strtolower($this->text($row['status'] ?? 'draft')) ?: 'draft'; return $row; }
    public function identity(array $row): string { return strtolower($this->text($row['expense_number'] ?? '')); }
    public function validate(array $row, int $tenantId): array
    {
        $errors = [];
        foreach ($this->requiredFields() as $field) if ($this->text($row[$field] ?? '') === '') $errors[] = ucfirst(str_replace('_', ' ', $field)).' is required.';
        if (!$this->dateValue($row['expense_date'] ?? null)) $errors[] = 'Expense date is invalid.';
        if (!$this->expenseCategory($row, $tenantId)) $errors[] = 'Expense category was not found.';
        if (!$this->bankAccount($row, $tenantId)) $errors[] = 'Bank account was not found.';
        if ($this->nullableText($row['chart_of_account'] ?? null) && !$this->chartAccount($row['chart_of_account'], $tenantId)) $errors[] = 'Chart of account was not found.';
        if ($this->decimal($row['amount'] ?? 0) <= 0) $errors[] = 'Amount must be greater than zero.';
        if (!in_array($row['status'], ['draft', 'approved', 'posted'], true)) $errors[] = 'Status is invalid.';
        return $errors;
    }
    public function duplicate(array $row, int $tenantId): bool { return Expense::where('created_by', $tenantId)->whereRaw('LOWER(expense_number) = ?', [$this->identity($row)])->exists(); }
    public function import(array $row, string $strategy, int $tenantId, int $actorId): string
    {
        $expense = Expense::where('created_by', $tenantId)->whereRaw('LOWER(expense_number) = ?', [$this->identity($row)])->first();
        if ($expense && $strategy === 'skip') return 'skipped';
        $values = ['expense_number' => $this->text($row['expense_number']), 'expense_date' => $this->dateValue($row['expense_date']), 'category_id' => $this->expenseCategory($row, $tenantId)->id, 'bank_account_id' => $this->bankAccount($row, $tenantId)->id, 'chart_of_account_id' => $this->chartAccount($row['chart_of_account'] ?? null, $tenantId)?->id, 'amount' => $this->decimal($row['amount']), 'description' => $this->text($row['description'] ?? ''), 'reference_number' => $this->nullableText($row['reference_number'] ?? null), 'status' => $row['status']];
        if ($expense) { $expense->update($values); return 'updated'; }
        $expense = Expense::create($values + ['creator_id' => $actorId, 'created_by' => $tenantId]);
        CreateExpense::dispatch(request(), $expense);
        return 'imported';
    }
}
