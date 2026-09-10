<?php

namespace Workdo\ProductService\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductServiceItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:255',
            'tax_ids' => 'nullable|array|max:1',
            'tax_ids.*' => ['integer', Rule::exists('product_service_taxes', 'id')->where(fn ($query) => $query->where('created_by', creatorId()))],
            'category_id' => ['required', Rule::exists('product_service_categories', 'id')->where(fn ($query) => $query->where('created_by', creatorId()))],
            'description' => 'nullable|string',
            'long_description' => 'nullable|string',
            'sale_price' => 'required|numeric|min:0',
            'purchase_price' => 'required|numeric|min:0',
            'unit' => ['nullable', 'required_unless:type,service', Rule::exists('product_service_units', 'id')->where(fn ($query) => $query->where('created_by', creatorId()))],
            'quantity' => 'nullable|integer|min:0|required_unless:type,service',
            'image' => 'nullable|string',
            'images' => 'nullable|array',
            'images.*' => 'string',
            'warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where(fn ($query) => $query->where('created_by', creatorId()))],
            'type' => 'nullable|in:product,service,part',
        ];
    }
}
