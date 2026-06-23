<?php

namespace App\Http\Requests;

use App\Models\SalesInvoice;
use App\Models\SalesInvoiceReturnItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreSalesReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'return_date' => 'required|date',
            'customer_id' => 'required|exists:users,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'original_invoice_id' => 'required|exists:sales_invoices,id',
            'reason' => 'required|in:defective,wrong_item,damaged,excess_quantity,other',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:product_service_items,id',
            'items.*.original_invoice_item_id' => 'required|exists:sales_invoice_items,id',
            'items.*.return_quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.reason' => 'nullable|string'
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $invoice = SalesInvoice::query()
                ->whereKey($this->input('original_invoice_id'))
                ->where('created_by', creatorId())
                ->where('customer_id', $this->input('customer_id'))
                ->where('status', '!=', 'draft')
                ->with('items')
                ->first();

            if (!$invoice) {
                $validator->errors()->add('original_invoice_id', __('The selected invoice is invalid.'));
                return;
            }

            $returnItems = $this->input('items', []);
            if (!is_array($returnItems)) {
                return;
            }

            $requestedByOriginalItem = [];

            foreach ($returnItems as $index => $returnItem) {
                if (!is_array($returnItem)) {
                    continue;
                }

                $originalItem = $invoice->items->firstWhere(
                    'id',
                    (int) ($returnItem['original_invoice_item_id'] ?? 0)
                );

                if (!$originalItem || (int) $originalItem->product_id !== (int) ($returnItem['product_id'] ?? 0)) {
                    $validator->errors()->add("items.{$index}.original_invoice_item_id", __('The selected invoice item is invalid.'));
                    continue;
                }

                $alreadyReturned = (float) SalesInvoiceReturnItem::query()
                    ->where('original_invoice_item_id', $originalItem->id)
                    ->whereHas('salesReturn', fn ($query) => $query->where('status', '!=', 'cancelled'))
                    ->sum('return_quantity');
                $availableQuantity = max(0, (float) $originalItem->quantity - $alreadyReturned);
                $requestedQuantity = (float) ($returnItem['return_quantity'] ?? 0);
                $requestedByOriginalItem[$originalItem->id] =
                    ($requestedByOriginalItem[$originalItem->id] ?? 0) + $requestedQuantity;

                if ($requestedByOriginalItem[$originalItem->id] > $availableQuantity + 0.00001) {
                    $validator->errors()->add(
                        "items.{$index}.return_quantity",
                        __('Return quantity cannot exceed the remaining quantity of :quantity.', [
                            'quantity' => number_format($availableQuantity, 2, '.', ''),
                        ])
                    );
                }
            }
        });
    }
}
