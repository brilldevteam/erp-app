<?php

namespace App\Services\ZohoMigration;

use Workdo\ProductService\Models\ProductServiceCategory;
use Workdo\ProductService\Models\ProductServiceItem;

/**
 * The single service that imported Zoho document lines (quotations, sales invoices) are placed on.
 * The original Zoho item name and description stay on each line; this product only satisfies the ERP's
 * one-product-per-line rule. Created once per company, in the "General Services" category when present.
 */
class ZohoFreeTextLineProduct
{
    public const SKU = 'ZOHO-FREE-TEXT';
    public const NAME = 'Zoho free-text line';

    public function for(int $tenantId, int $actorId): ProductServiceItem
    {
        return ProductServiceItem::firstOrCreate(
            ['created_by' => $tenantId, 'sku' => self::SKU],
            [
                'name' => self::NAME,
                'type' => 'service',
                'category_id' => ProductServiceCategory::where('created_by', $tenantId)->whereRaw('LOWER(name) = ?', ['general services'])->value('id'),
                'description' => 'Line imported from a Zoho document. The original Zoho item name and description are kept on each line.',
                'sale_price' => 0,
                'purchase_price' => 0,
                'is_active' => true,
                'creator_id' => $actorId,
            ]
        );
    }
}
