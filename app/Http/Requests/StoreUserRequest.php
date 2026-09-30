<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Companies may only assign their own roles; the platform-wide superadmin and company roles are never assignable here.
        $typeRule = auth()->user()->type === 'superadmin' ? 'nullable' : ['required', Rule::exists('roles', 'id')
            ->where('created_by', creatorId())
            ->whereNotIn('name', ['superadmin', 'company'])];
        
        return [
            'name' => 'required|string|max:255',
            'email' => 'required_if:is_enable_login,1|nullable|email|unique:users,email',
            'mobile_no' => 'nullable|string|regex:/^\+\d{1,3}\d{9,13}$/',
            'password' => 'required_if:is_enable_login,1|nullable|confirmed|min:6',
            'type' => $typeRule,
            'is_enable_login' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => __('Role is required.'),
            'email.required_if' => __('Email is required when login status is enabled.'),
            'password.required_if' => __('Password is required when login status is enabled.'),
        ];
    }
}
