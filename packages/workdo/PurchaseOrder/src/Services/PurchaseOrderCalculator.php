<?php

namespace Workdo\PurchaseOrder\Services;

use Illuminate\Validation\ValidationException;

class PurchaseOrderCalculator
{
    public function calculate(array $data): array
    {
        $items = [];
        $subtotal = $lineDiscount = $taxTotal = 0.0;
        foreach ($data['items'] as $index => $line) {
            $quantity = round((float)$line['quantity'], 4);
            $price = round((float)$line['unit_price'], 4);
            $gross = round($quantity * $price, 2);
            $discountValue = max(0, (float)($line['discount_value'] ?? 0));
            $discount = ($line['discount_type'] ?? 'percentage') === 'fixed'
                ? min($gross, $discountValue) : round($gross * min(100, $discountValue) / 100, 2);
            $taxable = $gross - $discount;
            $taxes = [];
            $lineTax = 0.0;
            foreach (($line['taxes'] ?? []) as $tax) {
                $amount = round($taxable * (float)$tax['tax_rate'] / 100, 2);
                $lineTax += $amount;
                $taxes[] = [...$tax, 'tax_amount' => $amount];
            }
            $items[] = [...$line, 'line_order'=>$index, 'subtotal'=>$gross, 'discount_amount'=>$discount, 'tax_amount'=>$lineTax, 'total_amount'=>round($taxable+$lineTax,2), 'taxes'=>$taxes];
            $subtotal += $gross; $lineDiscount += $discount; $taxTotal += $lineTax;
        }
        $base = $subtotal - $lineDiscount;
        $docValue = max(0, (float)($data['document_discount_value'] ?? 0));
        $docDiscount = ($data['document_discount_type'] ?? 'fixed') === 'percentage'
            ? round($base * min(100, $docValue) / 100, 2) : min($base, $docValue);
        $shipping = (float)($data['shipping_amount'] ?? 0);
        $adjustment = (float)($data['adjustment_amount'] ?? 0);
        $total = round($base - $docDiscount + $taxTotal + $shipping + $adjustment, 2);
        if ($total < 0) throw ValidationException::withMessages(['adjustment_amount'=>__('The grand total cannot be negative.')]);
        return ['items'=>$items,'subtotal'=>round($subtotal,2),'line_discount_amount'=>round($lineDiscount,2),'document_discount_amount'=>round($docDiscount,2),'tax_amount'=>round($taxTotal,2),'total_amount'=>$total];
    }
}
