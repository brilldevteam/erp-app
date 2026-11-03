<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ProductServiceCatalogSplitTest extends TestCase
{
    public function test_navigation_has_separate_permission_controlled_product_and_service_entries(): void
    {
        $source = $this->source('packages/workdo/ProductService/src/Resources/js/menus/company-menu.ts');

        $this->assertStringContainsString("title: t('Products')", $source);
        $this->assertStringContainsString("href: route('product-service.products.index')", $source);
        $this->assertStringContainsString("permission: 'manage-products'", $source);
        $this->assertStringContainsString("title: t('Services')", $source);
        $this->assertStringContainsString("href: route('product-service.services.index')", $source);
        $this->assertStringContainsString("permission: 'manage-services'", $source);
        $this->assertStringNotContainsString("title: t('Items')", $source);
    }

    public function test_catalog_routes_lock_the_type_on_every_crud_action(): void
    {
        $source = $this->source('packages/workdo/ProductService/src/Routes/web.php');

        $this->assertStringContainsString("['products' => 'product', 'services' => 'service']", $source);
        $this->assertSame(7, substr_count($source, "->defaults('catalogType', \$catalogType)"));
    }

    public function test_create_and_edit_forms_do_not_render_an_item_type_dropdown(): void
    {
        foreach (['Create.tsx', 'Edit.tsx'] as $file) {
            $source = $this->source("packages/workdo/ProductService/src/Resources/js/Pages/Items/{$file}");

            $this->assertStringNotContainsString('Item Type (Product / Service / Part)', $source, $file);
            $this->assertStringNotContainsString('<SelectItem value="product">', $source, $file);
            $this->assertStringContainsString('catalogType', $source, $file);
            $this->assertStringContainsString('catalogRoute', $source, $file);
        }
    }

    public function test_legacy_parts_remain_in_products_without_being_retyped_on_edit(): void
    {
        $controller = $this->source('packages/workdo/ProductService/src/Http/Controllers/ProductServiceItemController.php');

        $this->assertStringContainsString("where('type', '!=', 'service')", $controller);
        $this->assertStringContainsString("\$item->type === 'part' && \$catalogType === 'product'", $controller);
    }

    public function test_permission_migration_and_seeder_define_both_permission_families(): void
    {
        $migration = $this->source('database/migrations/2026_11_03_000001_split_product_service_permissions.php');
        $seeder = $this->source('packages/workdo/ProductService/src/Database/Seeders/PermissionTableSeeder.php');

        foreach (['manage', 'manage-any', 'manage-own', 'view', 'create', 'edit', 'delete'] as $action) {
            $this->assertStringContainsString("'{$action}'", $migration);
            $this->assertStringContainsString("'name' => '{$action}-products'", $seeder);
            $this->assertStringContainsString("'name' => '{$action}-services'", $seeder);
        }
        $this->assertStringContainsString('Role::permission($legacy)', $migration);
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2).DIRECTORY_SEPARATOR.$path);
        $this->assertIsString($source);

        return $source;
    }
}
