<?php

namespace Workdo\Stripe\Http\Controllers;

use App\Http\Controllers\Controller;
use Workdo\Stripe\Http\Requests\UpdateStripeSettingsRequest;
use Illuminate\Support\Facades\Auth;
use App\Services\SensitiveSettingAuditService;


class StripeSettingsController extends Controller
{
    public function update(UpdateStripeSettingsRequest $request, SensitiveSettingAuditService $audit)
    {
        if (Auth::user()->can('edit-stripe-settings')) {
            $validated = $request->validated();

            $settings = $validated['settings'];
            $replacement = $settings['stripe_secret'] ?? null;
            $clearSecret = (bool) ($settings['clear_stripe_secret'] ?? false);
            $wasConfigured = filled(company_setting('stripe_secret'));

            if ($clearSecret && filled($replacement)) {
                return back()->withErrors([
                    'settings.stripe_secret' => __('Enter a replacement or clear the stored credential, not both.'),
                ]);
            }

            if (($settings['stripe_enabled'] ?? 'off') === 'on' && ($clearSecret || (blank($replacement) && !$wasConfigured))) {
                return back()->withErrors([
                    'settings.stripe_secret' => __('Stripe secret is required before enabling Stripe.'),
                ]);
            }

            unset($settings['clear_stripe_secret']);
            if ($clearSecret) {
                $settings['stripe_secret'] = null;
            } elseif (blank($replacement)) {
                unset($settings['stripe_secret']);
            }

            try {
                foreach ($settings as $key => $value) {
                    setSetting($key, $value, creatorId(), $key == "stripe_enabled");
                }

                $audit->recordSubmittedSecret('payment', 'stripe_secret', $wasConfigured, $replacement, $clearSecret);

                return redirect()->back()->with('success', __('Stripe settings save successfully.'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', __('Failed to update stripe settings: ') . $e->getMessage());
            }           
        }
        return back()->with('error', __('Permission denied'));
    }
}
