<?php

namespace Workdo\Quotation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuotationRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'invoice_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'customer_id' => ['required', Rule::exists('users', 'id')->where(fn ($query) => $query->where('created_by', creatorId())->where('type', 'client'))],
            'document_template_id' => ['nullable', 'integer', Rule::exists('document_templates', 'id')->where(fn ($query) => $query->where('company_id', creatorId())->where('type', 'quotation'))],
            'warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where(fn ($query) => $query->where('created_by', creatorId()))],
            'payment_terms' => 'nullable|string|max:255',
            'subject' => 'nullable|string|max:500',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => ['required', 'numeric', 'min:1', Rule::exists('product_service_items', 'id')->where(fn ($query) => $query->where('created_by', creatorId())->where('is_active', true))],
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.description' => 'nullable|string|max:20000',
            'items.*.discount_type' => 'nullable|in:percentage,fixed',
            'items.*.discount_value' => 'nullable|numeric|min:0',
            'items.*.discount_percentage' => 'nullable|numeric|min:0|max:100',
            'items.*.tax_percentage' => 'nullable|numeric|min:0',
            'items.*.taxes' => 'nullable|array',
            'items.*.taxes.*.tax_name' => 'required_with:items.*.taxes|string',
            'items.*.taxes.*.tax_rate' => 'required_with:items.*.taxes|numeric|min:0'
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
