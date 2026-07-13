<?php

namespace App\Services\BulkImport\Definitions;

use App\Services\BulkImport\Concerns\NormalizesImportValues;
use App\Services\BulkImport\Definitions\Concerns\ResolvesImportReferences;
use App\Services\BulkImport\EntityDefinition;
use Workdo\Account\Events\CreateVendorPayment;
use Workdo\Account\Models\VendorPayment;
use Workdo\Account\Models\VendorPaymentAllocation;

class VendorPaymentDefinition implements EntityDefinition
{
    use NormalizesImportValues;
    use ResolvesImportReferences;

    public function key(): string { return 'vendor-payments'; }
    public function permission(): string { return 'import-vendor-payments'; }
    public function createPermission(): string { return 'create-vendor-payments'; }
    public function headers(): array { return ['payment_number', 'payment_date', 'vendor_email', 'vendor', 'bank_account', 'account_number', 'reference_number', 'payment_amount', 'invoice_number', 'allocated_amount', 'status', 'notes']; }
    public function requiredFields(): array { return ['payment_number', 'payment_date', 'vendor_email', 'bank_account', 'payment_amount']; }
    public function aliases(): array { return ['payment_number' => ['payment no', 'payment number'], 'vendor_email' => ['vendor email', 'supplier email', 'email'], 'bank_account' => ['paid through', 'bank'], 'invoice_number' => ['bill no', 'bill number']]; }
    public function example(): array { return ['VP-ZOHO-1001', date('Y-m-d'), 'vendor@example.com', 'ABC Supplies', 'Main Bank', '100200300', 'REF-1', '70', 'BILL-1001', '70', 'cleared', 'Imported from Zoho Books']; }
    public function instructions(): array { return ['payment_number is the duplicate key.', 'invoice_number and allocated_amount are optional; when provided, the payment is allocated to that purchase invoice.']; }
    public function prepare(array $row): array { $row['payment_number'] = $this->text($row['payment_number'] ?? ''); $row['status'] = strtolower($this->text($row['status'] ?? 'pending')) ?: 'pending'; return $row; }
    public function identity(array $row): string { return strtolower($this->text($row['payment_number'] ?? '')); }

    public function validate(array $row, int $tenantId): array
    {
        $errors = [];
        foreach ($this->requiredFields() as $field) {
            if ($this->text($row[$field] ?? '') === '') $errors[] = ucfirst(str_replace('_', ' ', $field)).' is required.';
        }
        if (!$this->dateValue($row['payment_date'] ?? null)) $errors[] = 'Payment date is invalid.';
        if (!$this->vendorUser($row, $tenantId)) $errors[] = 'Vendor was not found.';
        if (!$this->bankAccount($row, $tenantId)) $errors[] = 'Bank account was not found.';
        if ($this->decimal($row['payment_amount'] ?? 0) <= 0) $errors[] = 'Payment amount must be greater than zero.';
        if ($this->nullableText($row['invoice_number'] ?? null) && !$this->purchaseInvoice($row, $tenantId)) $errors[] = 'Purchase invoice was not found.';
        if (!in_array($row['status'], ['pending', 'cleared', 'cancelled'], true)) $errors[] = 'Status is invalid.';
        return $errors;
    }

    public function duplicate(array $row, int $tenantId): bool
    {
        return VendorPayment::where('created_by', $tenantId)->whereRaw('LOWER(payment_number) = ?', [$this->identity($row)])->exists();
    }

    public function import(array $row, string $strategy, int $tenantId, int $actorId): string
    {
        $payment = VendorPayment::where('created_by', $tenantId)->whereRaw('LOWER(payment_number) = ?', [$this->identity($row)])->first();
        if ($payment && $strategy === 'skip') return 'skipped';
        $values = [
            'payment_number' => $this->text($row['payment_number']),
            'payment_date' => $this->dateValue($row['payment_date']),
            'vendor_id' => $this->vendorUser($row, $tenantId)->id,
            'bank_account_id' => $this->bankAccount($row, $tenantId)->id,
            'reference_number' => $this->nullableText($row['reference_number'] ?? null),
            'payment_amount' => $this->decimal($row['payment_amount'] ?? 0),
            'status' => $row['status'],
            'notes' => $this->nullableText($row['notes'] ?? null),
        ];
        if ($payment) {
            $payment->update($values);
            $payment->allocations()->delete();
            $result = 'updated';
        } else {
            $payment = VendorPayment::create($values + ['creator_id' => $actorId, 'created_by' => $tenantId]);
            $result = 'imported';
        }
        if ($invoice = $this->purchaseInvoice($row, $tenantId)) {
            VendorPaymentAllocation::create(['payment_id' => $payment->id, 'invoice_id' => $invoice->id, 'allocated_amount' => $this->decimal($row['allocated_amount'] ?? $row['payment_amount'] ?? 0)]);
        }
        CreateVendorPayment::dispatch(request(), $payment);
        return $result;
    }
}
