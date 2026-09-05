<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;
use Workdo\ProductService\Models\ProductServiceItem;

class SalesLineAmounts
{
    public static function description(?string $html): string
    {
        $text = preg_replace('/<br\\s*\\/?\\s*>|<\\/(?:p|div|li|h[1-6])>/i', "\n", $html ?? '');
        return trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    public static function syncProductDescription(int $productId, ?string $description, int $companyId): void
    {
        $description = trim((string) $description);
        $longDescription = $description === '' ? null : collect(preg_split('/\R/', $description))
            ->map(fn (string $line) => '<p>' . e($line) . '</p>')
            ->implode('');

        ProductServiceItem::query()
            ->whereKey($productId)
            ->where('created_by', $companyId)
            ->update([
                'description' => $description === '' ? null : $description,
                'long_description' => $longDescription,
            ]);
    }

    public static function calculate(array $item, string $key = 'items'): array
    {
        $subtotal = round((float) $item['quantity'] * (float) $item['unit_price'], 2);
        $fixed = ($item['discount_type'] ?? 'percentage') === 'fixed';
        $value = (float) ($fixed ? ($item['discount_value'] ?? 0) : ($item['discount_percentage'] ?? 0));
        if ($value < 0 || $value > ($fixed ? $subtotal : 100)) {
            throw ValidationException::withMessages([
                $key . ($fixed ? '.discount_value' : '.discount_percentage') =>
                    $fixed ? __('Discount cannot exceed the line subtotal.') : __('Discount must be between 0 and 100 percent.'),
            ]);
        }
        $discount = round($fixed ? $value : $subtotal * $value / 100, 2);
        $tax = round(($subtotal - $discount) * (float) ($item['tax_percentage'] ?? 0) / 100, 2);

        return ['subtotal' => $subtotal, 'discount_amount' => $discount,
            'tax_amount' => $tax, 'total_amount' => round($subtotal - $discount + $tax, 2)];
    }

    public static function returnDiscount(?\App\Models\SalesInvoiceItem $original, float $subtotal): float
    {
        if ($original?->discount_type === 'fixed') {
            $originalSubtotal = (float) $original->quantity * (float) $original->unit_price;
            return $originalSubtotal > 0 ? round($subtotal * (float) $original->discount_amount / $originalSubtotal, 2) : 0;
        }
        return round($subtotal * (float) ($original?->discount_percentage ?? 0) / 100, 2);
    }

    public static function totals(array $items): array
    {
        $totals = ['subtotal' => 0, 'discount_amount' => 0, 'tax_amount' => 0, 'total_amount' => 0];
        foreach ($items as $index => $item) {
            foreach (self::calculate($item, "items.$index") as $field => $amount) {
                $totals[$field] = round($totals[$field] + $amount, 2);
            }
        }
        return $totals;
    }
}
