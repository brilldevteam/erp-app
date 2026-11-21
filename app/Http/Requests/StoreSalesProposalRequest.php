<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\User;
use App\Models\Warehouse;
use Workdo\ProductService\Models\ProductServiceItem;

class StoreSalesProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->referencesBelongToTenant();
    }

    private function referencesBelongToTenant(): bool
    {
        $tenantId = creatorId();
        $productIds = collect($this->input('items', []))->pluck('product_id')
            ->filter(fn ($id) => filter_var($id, FILTER_VALIDATE_INT) !== false)
            ->map(fn ($id) => (int) $id)->unique();

        if ($productIds->isNotEmpty()
            && ProductServiceItem::where('created_by', $tenantId)->whereIn('id', $productIds)->count() !== $productIds->count()) {
            return false;
        }

        if ($this->filled('warehouse_id')
            && !Warehouse::where('created_by', $tenantId)->whereKey($this->input('warehouse_id'))->exists()) {
            return false;
        }

        return !$this->filled('customer_id')
            || User::query()->accountingCustomers($tenantId)->whereKey($this->input('customer_id'))->exists();
    }

    public function rules(): array
    {
        return [
            'invoice_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'customer_id' => 'required|integer|exists:users,id',
            'warehouse_id' => 'nullable|integer|exists:warehouses,id',
            'payment_terms' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|min:1',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_percentage' => 'nullable|numeric|min:0|max:100',
            'items.*.tax_percentage' => 'nullable|numeric|min:0|max:100',
            'items.*.taxes' => 'nullable|array',
            'items.*.taxes.*.tax_name' => 'required_with:items.*.taxes|string',
            'items.*.taxes.*.tax_rate' => 'required_with:items.*.taxes|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.exists' => __('Selected customer does not exist.'),
            'items.required' => __('At least one item is required.'),
            'items.*.product_id.min' => __('Please select a product for each item.'),
            'items.*.quantity.min' => __('Quantity must be at least 1.'),
            'items.*.unit_price.min' => __('Unit price must be 0 or greater.')
        ];
    }
}
