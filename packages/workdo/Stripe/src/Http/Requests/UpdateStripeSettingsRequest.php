<?php

namespace Workdo\Stripe\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateStripeSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->can('edit-stripe-settings');
    }

    public function rules(): array
    {
        return [
            'settings.stripe_enabled' => 'required|string|in:on,off',
            'settings.stripe_key' => 'required_if:settings.stripe_enabled,on|nullable|string',
            'settings.stripe_secret' => 'nullable|string',
            'settings.clear_stripe_secret' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
         return [
            'settings.stripe_key.required_if' => __('Stripe key is required.'),
            'settings.stripe_secret.required_if' => __('Stripe secret is required.'),
            'settings.stripe_enabled.in' => __('Stripe enabled must be either on or off.'),
        ];
    }
}
