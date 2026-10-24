<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\ProductService\Models\ProductServiceItem;

/**
 * Each bill line posts to its own account. Stock products default to Inventory;
 * service and free-text lines must name the account they are charged to.
 * A fixed-amount line discount cannot exceed the line subtotal.
 */
trait ValidatesBillLines
{
    protected function lineAccountRule(): array
    {
        return ['nullable', 'integer', Rule::in(ChartOfAccount::billAccountOptions(creatorId())->pluck('id')->all())];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $items = (array) $this->input('items', []);
            $productIds = collect($items)->pluck('product_id')->filter()->unique()->all();
            $stockProducts = ProductServiceItem::whereIn('id', $productIds)
                ->where('created_by', creatorId())
                ->where('type', 'product')
                ->pluck('id')
                ->all();

            foreach ($items as $index => $item) {
                $isStockProduct = !empty($item['product_id']) && in_array((int) $item['product_id'], $stockProducts, true);
                if (!$isStockProduct && empty($item['account_id'])) {
                    $validator->errors()->add("items.$index.account_id", __('Select the account this line is charged to.'));
                }
                $lineTotal = round((float) ($item['quantity'] ?? 0) * (float) ($item['unit_price'] ?? 0), 2);
                if (($item['discount_type'] ?? 'percentage') === 'fixed' && (float) ($item['discount_value'] ?? 0) > $lineTotal) {
                    $validator->errors()->add("items.$index.discount_value", __('Discount cannot exceed the line subtotal.'));
                }
            }
        });
    }
}
