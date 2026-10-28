<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PartyPayloadHardeningTest extends TestCase
{
    #[DataProvider('paymentControllerProvider')]
    public function test_payment_selectors_are_minimal_and_outstanding_endpoints_are_protected(
        string $path,
        string $partyType,
        string $permission,
        string $documentFields
    ): void {
        $source = $this->source($path);

        $this->assertStringContainsString("->where('type', '{$partyType}')", $source);
        $this->assertStringContainsString("->where('created_by', creatorId())", $source);
        $this->assertStringContainsString("->get(['id', 'name'])", $source);
        $this->assertStringContainsString("abort_unless(Auth::user()->can('{$permission}'), 403)", $source);
        $this->assertStringContainsString($documentFields, $source);
        $this->assertStringNotContainsString("->where('type', '{$partyType}')->where('created_by', creatorId())->get()", $source);
        $this->assertStringContainsString("bankAccount:id,account_name,account_number", $source);
        $this->assertStringNotContainsString("with(['customer', 'customerDetails', 'bankAccount'", $source);
        $this->assertStringNotContainsString("with(['vendor', 'vendorDetails', 'bankAccount'", $source);
    }

    public static function paymentControllerProvider(): array
    {
        return [
            'customer payments' => [
                'packages/workdo/Account/src/Http/Controllers/CustomerPaymentController.php',
                'client',
                'create-customer-payments',
                "->get(['id', 'invoice_number', 'invoice_date', 'total_amount', 'balance_amount', 'status', 'currency_code', 'exchange_rate'])",
            ],
            'vendor payments' => [
                'packages/workdo/Account/src/Http/Controllers/VendorPaymentController.php',
                'vendor',
                'create-vendor-payments',
                "->get(['id', 'invoice_number', 'invoice_date', 'total_amount', 'balance_amount', 'status', 'currency_code', 'exchange_rate'])",
            ],
            'retainer payments' => [
                'packages/workdo/Retainer/src/Http/Controllers/RetainerPaymentController.php',
                'client',
                'create-retainer-payments',
                "->select('id', 'retainer_number', 'total_amount', 'paid_amount', 'balance_amount', 'status')",
            ],
        ];
    }

    #[DataProvider('tenantRequestProvider')]
    public function test_submitted_party_and_related_ids_are_tenant_scoped(string $path, array $requiredFragments): void
    {
        $source = $this->source($path);

        foreach ($requiredFragments as $fragment) {
            $this->assertStringContainsString($fragment, $source, "Missing tenant rule in {$path}: {$fragment}");
        }
    }

    public static function tenantRequestProvider(): array
    {
        $tenant = "->where('created_by', creatorId())";

        return [
            'customer payment' => [
                'packages/workdo/Account/src/Http/Requests/StoreCustomerPaymentRequest.php',
                ["Rule::exists('users', 'id')", "->where('type', 'client')", "Rule::exists('sales_invoices', 'id')", "Rule::exists('credit_notes', 'id')", $tenant],
            ],
            'vendor payment' => [
                'packages/workdo/Account/src/Http/Requests/StoreVendorPaymentRequest.php',
                ["Rule::exists('users', 'id')", "->where('type', 'vendor')", "Rule::exists('purchase_invoices', 'id')", "Rule::exists('debit_notes', 'id')", "Rule::exists('bank_accounts', 'id')", $tenant],
            ],
            'retainer create' => [
                'packages/workdo/Retainer/src/Http/Requests/StoreRetainerRequest.php',
                ["Rule::exists('users', 'id')", "->where('type', 'client')", "Rule::exists('warehouses', 'id')", "Rule::exists('product_service_items', 'id')", $tenant],
            ],
            'retainer update' => [
                'packages/workdo/Retainer/src/Http/Requests/UpdateRetainerRequest.php',
                ["Rule::exists('users', 'id')", "->where('type', 'client')", "Rule::exists('warehouses', 'id')", "Rule::exists('product_service_items', 'id')", $tenant],
            ],
            'retainer payment' => [
                'packages/workdo/Retainer/src/Http/Requests/StoreSalesRetainerPaymentRequest.php',
                ["Rule::exists('users', 'id')", "->where('type', 'client')", "Rule::exists('sales_retainers', 'id')", "Rule::exists('bank_accounts', 'id')", $tenant],
            ],
            'purchase invoice create' => [
                'app/Http/Requests/StorePurchaseInvoiceRequest.php',
                ["Rule::exists('users', 'id')", "->where('type', 'vendor')", "Rule::exists('warehouses', 'id')", "Rule::exists('product_service_items', 'id')", $tenant],
            ],
            'purchase invoice update' => [
                'app/Http/Requests/UpdatePurchaseInvoiceRequest.php',
                ["Rule::exists('users', 'id')", "->where('type', 'vendor')", "Rule::exists('warehouses', 'id')", "Rule::exists('product_service_items', 'id')", $tenant],
            ],
        ];
    }

    public function test_allocations_are_bound_to_the_selected_party(): void
    {
        $vendorPayments = $this->source('packages/workdo/Account/src/Http/Controllers/VendorPaymentController.php');
        $retainerPayments = $this->source('packages/workdo/Retainer/src/Http/Controllers/RetainerPaymentController.php');

        $this->assertStringContainsString("->where('vendor_id', \$request->vendor_id)", $vendorPayments);
        $this->assertStringContainsString("pluck('invoice_id')->unique()->count() !== \$allocationInvoices->count()", $vendorPayments);
        $this->assertStringContainsString("->where('customer_id', \$request->customer_id)", $retainerPayments);
        $this->assertStringContainsString("pluck('retainer_id')->unique()->count() !== \$retainers->count()", $retainerPayments);
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2).DIRECTORY_SEPARATOR.$path);
        $this->assertIsString($source);

        return $source;
    }
}
