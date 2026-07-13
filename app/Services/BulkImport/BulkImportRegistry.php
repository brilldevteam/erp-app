<?php

namespace App\Services\BulkImport;

use App\Services\BulkImport\Definitions\AccountTypeDefinition;
use App\Services\BulkImport\Definitions\BankAccountDefinition;
use App\Services\BulkImport\Definitions\ChartOfAccountDefinition;
use App\Services\BulkImport\Definitions\CustomerDefinition;
use App\Services\BulkImport\Definitions\CustomerPaymentDefinition;
use App\Services\BulkImport\Definitions\ExpenseCategoryDefinition;
use App\Services\BulkImport\Definitions\ExpenseDefinition;
use App\Services\BulkImport\Definitions\ProductServiceCategoryDefinition;
use App\Services\BulkImport\Definitions\ProductServiceDefinition;
use App\Services\BulkImport\Definitions\ProductServiceTaxDefinition;
use App\Services\BulkImport\Definitions\ProductServiceUnitDefinition;
use App\Services\BulkImport\Definitions\PurchaseInvoiceDefinition;
use App\Services\BulkImport\Definitions\RevenueCategoryDefinition;
use App\Services\BulkImport\Definitions\RevenueDefinition;
use App\Services\BulkImport\Definitions\SalesInvoiceDefinition;
use App\Services\BulkImport\Definitions\VendorDefinition;
use App\Services\BulkImport\Definitions\VendorPaymentDefinition;
use App\Services\BulkImport\Definitions\WarehouseDefinition;
use InvalidArgumentException;

class BulkImportRegistry
{
    public function all(): array
    {
        return [
            'customers' => new CustomerDefinition(),
            'vendors' => new VendorDefinition(),
            'product-service-categories' => new ProductServiceCategoryDefinition(),
            'product-service-units' => new ProductServiceUnitDefinition(),
            'product-service-taxes' => new ProductServiceTaxDefinition(),
            'product-service-items' => new ProductServiceDefinition(),
            'warehouses' => new WarehouseDefinition(),
            'account-types' => new AccountTypeDefinition(),
            'chart-of-accounts' => new ChartOfAccountDefinition(),
            'revenue-categories' => new RevenueCategoryDefinition(),
            'expense-categories' => new ExpenseCategoryDefinition(),
            'bank-accounts' => new BankAccountDefinition(),
            'sales-invoices' => new SalesInvoiceDefinition(),
            'purchase-invoices' => new PurchaseInvoiceDefinition(),
            'customer-payments' => new CustomerPaymentDefinition(),
            'vendor-payments' => new VendorPaymentDefinition(),
            'revenues' => new RevenueDefinition(),
            'expenses' => new ExpenseDefinition(),
        ];
    }

    public function get(string $entity): EntityDefinition
    {
        return $this->all()[$entity] ?? throw new InvalidArgumentException('Unsupported import entity.');
    }
}
