<?php

namespace App\Services\BulkImport\Definitions\Concerns;

use App\Models\SalesInvoice;
use App\Models\PurchaseInvoice;
use App\Models\User;
use Workdo\Account\Models\BankAccount;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\Account\Models\Customer;
use Workdo\Account\Models\ExpenseCategories;
use Workdo\Account\Models\RevenueCategories;
use Workdo\Account\Models\Vendor;
use Workdo\ProductService\Models\ProductServiceItem;
use Workdo\ProductService\Models\ProductServiceTax;

trait ResolvesImportReferences
{
    protected function decimal(mixed $value, float $default = 0): float
    {
        $value = str_replace([',', ' '], '', (string) ($value ?? ''));

        return is_numeric($value) ? (float) $value : $default;
    }

    protected function integer(mixed $value, int $default = 0): int
    {
        return (int) round($this->decimal($value, $default));
    }

    protected function dateValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        try {
            return \Carbon\Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    protected function customerUser(array $row, int $tenantId): ?User
    {
        $value = strtolower($this->text($row['customer_email'] ?? $row['user_email'] ?? $row['customer'] ?? ''));

        return User::where('created_by', $tenantId)
            ->where('type', 'client')
            ->where(fn ($query) => $query
                ->whereRaw('LOWER(email) = ?', [$value])
                ->orWhereRaw('LOWER(name) = ?', [$value]))
            ->first()
            ?? Customer::where('created_by', $tenantId)
                ->whereRaw('LOWER(company_name) = ?', [$value])
                ->first()?->user;
    }

    protected function vendorUser(array $row, int $tenantId): ?User
    {
        $value = strtolower($this->text($row['vendor_email'] ?? $row['vendor'] ?? ''));

        return User::where('created_by', $tenantId)
            ->where('type', 'vendor')
            ->where(fn ($query) => $query
                ->whereRaw('LOWER(email) = ?', [$value])
                ->orWhereRaw('LOWER(name) = ?', [$value]))
            ->first()
            ?? Vendor::where('created_by', $tenantId)
                ->whereRaw('LOWER(company_name) = ?', [$value])
                ->first()?->user;
    }

    protected function product(array $row, int $tenantId): ?ProductServiceItem
    {
        $value = strtolower($this->text($row['item_sku'] ?? $row['sku'] ?? $row['item_name'] ?? ''));

        return ProductServiceItem::where('created_by', $tenantId)
            ->where(fn ($query) => $query
                ->whereRaw('LOWER(sku) = ?', [$value])
                ->orWhereRaw('LOWER(name) = ?', [$value]))
            ->first();
    }

    protected function bankAccount(array $row, int $tenantId): ?BankAccount
    {
        $value = strtolower($this->text($row['bank_account'] ?? $row['account_name'] ?? ''));
        $number = strtolower($this->text($row['account_number'] ?? ''));

        return BankAccount::where('created_by', $tenantId)
            ->where(fn ($query) => $query
                ->whereRaw('LOWER(account_name) = ?', [$value])
                ->orWhereRaw('LOWER(account_number) = ?', [$number ?: $value]))
            ->first();
    }

    protected function revenueCategory(array $row, int $tenantId): ?RevenueCategories
    {
        return RevenueCategories::where('created_by', $tenantId)
            ->whereRaw('LOWER(category_name) = ?', [strtolower($this->text($row['category'] ?? ''))])
            ->first();
    }

    protected function expenseCategory(array $row, int $tenantId): ?ExpenseCategories
    {
        return ExpenseCategories::where('created_by', $tenantId)
            ->whereRaw('LOWER(category_name) = ?', [strtolower($this->text($row['category'] ?? ''))])
            ->first();
    }

    protected function chartAccount(?string $nameOrCode, int $tenantId): ?ChartOfAccount
    {
        $value = strtolower($this->text($nameOrCode));
        if ($value === '') {
            return null;
        }

        return ChartOfAccount::where('created_by', $tenantId)
            ->where(fn ($query) => $query
                ->whereRaw('LOWER(account_name) = ?', [$value])
                ->orWhereRaw('LOWER(account_code) = ?', [$value]))
            ->first();
    }

    protected function salesInvoice(array $row, int $tenantId): ?SalesInvoice
    {
        return SalesInvoice::where('created_by', $tenantId)
            ->whereRaw('LOWER(invoice_number) = ?', [strtolower($this->text($row['invoice_number'] ?? ''))])
            ->first();
    }

    protected function purchaseInvoice(array $row, int $tenantId): ?PurchaseInvoice
    {
        return PurchaseInvoice::where('created_by', $tenantId)
            ->whereRaw('LOWER(invoice_number) = ?', [strtolower($this->text($row['invoice_number'] ?? ''))])
            ->first();
    }

    protected function taxNamesAndRate(mixed $taxNames, mixed $taxRate, int $tenantId): array
    {
        $names = $this->split($taxNames);
        $rate = $this->decimal($taxRate);

        if (!$names && $rate > 0) {
            $tax = ProductServiceTax::where('created_by', $tenantId)
                ->where('tax_rate', $rate)
                ->first();
            $names = [$tax?->tax_name ?: "Tax {$rate}%"];
        }

        return [$names, $rate];
    }
}
