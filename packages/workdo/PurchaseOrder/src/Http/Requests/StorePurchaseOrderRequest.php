<?php

namespace Workdo\PurchaseOrder\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseOrderRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'currency_code' => company_setting('defaultCurrency', creatorId()) ?: 'USD',
            'exchange_rate' => 1,
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vendor_id' => ['required', Rule::exists('users', 'id')->where(fn ($query) => $query->where('type', 'vendor')->where('created_by', creatorId()))],
            'warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where(fn ($query) => $query->where('created_by', creatorId()))],
            'order_date' => 'required|date',
            'expected_delivery_date' => 'nullable|date|after_or_equal:order_date',
            'currency_code' => 'required|string|max:10',
            'exchange_rate' => 'required|numeric|gt:0',
            'vendor_reference' => 'nullable|string|max:255',
            'vendor_quotation_number' => 'nullable|string|max:255',
            'billing_address' => 'nullable|array',
            'delivery_address' => 'nullable|array',
            'document_discount_type' => 'required|in:percentage,fixed',
            'document_discount_value' => 'nullable|numeric|min:0',
            'shipping_amount' => 'nullable|numeric|min:0',
            'adjustment_amount' => 'nullable|numeric',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,csv,txt|max:10240',
            'items' => 'required|array|min:1',
            'items.*.product_id' => ['nullable', Rule::exists('product_service_items', 'id')->where(fn ($query) => $query->where('created_by', creatorId()))],
            'items.*.item_name' => 'required|string|max:255',
            'items.*.description' => 'nullable|string',
            'items.*.unit' => 'nullable|string|max:50',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_type' => 'required|in:percentage,fixed',
            'items.*.discount_value' => 'nullable|numeric|min:0',
            'items.*.taxes' => 'nullable|array',
            'items.*.taxes.*.tax_id' => 'nullable|integer',
            'items.*.taxes.*.tax_name' => 'required_with:items.*.taxes|string',
            'items.*.taxes.*.tax_rate' => 'required_with:items.*.taxes|numeric|min:0',
        ];
    }
}
