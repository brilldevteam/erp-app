<?php

namespace Workdo\Pos\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\User;
use App\Models\Warehouse;
use Workdo\ProductService\Models\ProductServiceItem;

class StorePosRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenantId = creatorId();
        $productIds = collect($this->input('items', []))
            ->pluck('id')
            ->filter(fn ($id) => filter_var($id, FILTER_VALIDATE_INT) !== false)
            ->map(fn ($id) => (int) $id)
            ->unique();

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
            'customer_id' => 'nullable|exists:users,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'pos_date' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|exists:product_service_items,id',
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'items.*.tax_amount' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
        ];
    }
}
