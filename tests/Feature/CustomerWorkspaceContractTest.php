<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class CustomerWorkspaceContractTest extends TestCase
{
    private function source(string $path): string
    {
        return file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
    }

    public function test_workspace_route_and_legacy_report_routes_remain_available(): void
    {
        $routes = $this->source('packages/workdo/Account/src/Routes/web.php');

        $this->assertStringContainsString("name('account.customers.workspace')", $routes);
        $this->assertStringContainsString("name('customer-detail')", $routes);
        $this->assertStringContainsString("name('customer-detail.print')", $routes);
    }

    public function test_workspace_enforces_tenant_and_customer_scope(): void
    {
        $controller = $this->source('packages/workdo/Account/src/Http/Controllers/CustomerWorkspaceController.php');

        $this->assertStringContainsString('$customer->created_by === (int) creatorId()', $controller);
        $this->assertStringContainsString("can('manage-any-customers')", $controller);
        $this->assertStringContainsString("can('manage-own-customers')", $controller);
        $this->assertStringContainsString('$customer->creator_id === (int) auth()->id()', $controller);
    }

    public function test_normalized_feed_covers_supported_accounting_documents_and_base_values(): void
    {
        $service = $this->source('packages/workdo/Account/src/Services/CustomerWorkspaceService.php');

        foreach (['sales_invoices', 'sales_quotations', 'customer_payments', 'credit_notes', 'sales_invoice_returns', 'sales_retainers'] as $table) {
            $this->assertStringContainsString($table, $service);
        }

        $this->assertStringContainsString('unionAll', $service);
        $this->assertStringContainsString('base_total', $service);
        $this->assertStringContainsString('opening_balance', $service);
    }

    public function test_activity_metadata_uses_an_allow_list_and_excludes_credentials(): void
    {
        $listener = $this->source('packages/workdo/Account/src/Listeners/LogCustomerActivity.php');

        $this->assertStringContainsString('$allowedFields', $listener);
        $this->assertStringNotContainsString("'password'", $listener);
        $this->assertStringNotContainsString("'password_confirmation'", $listener);
        $this->assertStringContainsString("'changed_fields'", $listener);
    }

    public function test_workspace_has_mobile_navigation_semantic_tabs_and_compact_transactions(): void
    {
        $workspace = $this->source('packages/workdo/Account/src/Resources/js/Pages/Customers/Workspace.tsx');

        $this->assertStringContainsString('lg:w-[340px]', $workspace);
        $this->assertStringContainsString('role="tablist"', $workspace);
        $this->assertStringContainsString('role="tab"', $workspace);
        $this->assertStringContainsString('md:hidden', $workspace);
        $this->assertStringContainsString('focus-visible:ring-2', $workspace);
        $this->assertStringContainsString('title={customer.company_name}', $workspace);
        $this->assertStringContainsString('min-w-0 truncate text-xl', $workspace);
        $this->assertStringContainsString('shrink-0 flex-wrap gap-2 xl:flex-nowrap', $workspace);
    }

    public function test_directory_exposes_pagination_and_uses_company_currency_settings(): void
    {
        $workspace = $this->source('packages/workdo/Account/src/Resources/js/Pages/Customers/Workspace.tsx');
        $index = $this->source('packages/workdo/Account/src/Resources/js/Pages/Customers/Index.tsx');

        $this->assertStringContainsString('items.current_page', $workspace);
        $this->assertStringContainsString('items.last_page', $workspace);
        $this->assertStringContainsString("navigate({ directory_page:", $workspace);
        $this->assertStringContainsString('normalizedSearch ? 300 : 0', $workspace);
        $this->assertStringContainsString("only: ['directory', 'filters']", $workspace);
        $this->assertStringContainsString("onClick={() => setSearch('')}", $workspace);
        $this->assertStringContainsString("import { formatCurrency } from '@/utils/helpers'", $workspace);
        $this->assertStringContainsString("import { formatCurrency } from '@/utils/helpers'", $index);
        $this->assertStringNotContainsString("preferred_currency_code || 'USD'", $workspace);
        $this->assertStringNotContainsString("preferred_currency_code || 'USD'", $index);
        $this->assertStringContainsString('currencies.map(currency =>', $index);
        $this->assertStringContainsString('<SelectContent searchable>', $index);
        $this->assertStringNotContainsString("placeholder={t('e.g. QAR')}", $index);
        $controller = $this->source('packages/workdo/Account/src/Http/Controllers/CustomerController.php');
        $this->assertStringContainsString("'currency', 'portal', 'activity_from'", $controller);
        $service = $this->source('packages/workdo/Account/src/Services/CustomerWorkspaceService.php');
        $this->assertStringContainsString('baseCurrency($customer)', $service);
        $this->assertStringNotContainsString("COALESCE(currency_code, 'USD')", $service);
        $this->assertStringContainsString('baseCurrency($tenantId)', $service);
        $this->assertStringContainsString("orWhereNull('preferred_currency_code')", $service);
        $this->assertStringContainsString("orWhere('preferred_currency_code', '')", $service);
        $this->assertStringContainsString('$currency === $baseCurrency', $service);
    }

    public function test_customer_create_and_edit_share_the_guided_responsive_form(): void
    {
        $create = $this->source('packages/workdo/Account/src/Resources/js/Pages/Customers/Create.tsx');
        $edit = $this->source('packages/workdo/Account/src/Resources/js/Pages/Customers/Edit.tsx');
        $form = $this->source('packages/workdo/Account/src/Resources/js/Pages/Customers/CustomerForm.tsx');

        $this->assertStringContainsString("<CustomerForm mode=\"create\"", $create);
        $this->assertStringContainsString("<CustomerForm mode=\"edit\"", $edit);
        $this->assertStringContainsString("[t('Contact'), t('Billing'), t('Review & access')]", $form);
        $this->assertStringContainsString("[t('Profile'), t('Billing'), t('Access & files')]", $form);
        $this->assertStringContainsString("t('Tax and registration details')", $form);
        $this->assertStringContainsString("t('Billing address')", $form);
        $this->assertStringContainsString("t('Custom terms')", $form);
        $this->assertStringContainsString('h-[100dvh]', $form);
        $this->assertStringContainsString("t('Ready to save')", $form);
        $this->assertStringContainsString('<Switch id="portal_access_enabled"', $form);
        $this->assertStringContainsString("onError: result => setStep(errorStep(result))", $form);
    }
}
