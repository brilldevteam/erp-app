<?php

namespace Workdo\Paypal\Http\Controllers;

use App\Http\Controllers\Controller;
use Workdo\Paypal\Http\Requests\UpdatePaypalSettingsRequest;
use Illuminate\Support\Facades\Auth;
use App\Services\SensitiveSettingAuditService;

class PaypalSettingsController extends Controller
{
    public function update(UpdatePaypalSettingsRequest $request, SensitiveSettingAuditService $audit)
    {
        if (Auth::user()->can('edit-paypal-settings')) {
            $validated = $request->validated();

            $settings = $validated['settings'];
            $replacement = $settings['paypal_secret_key'] ?? null;
            $clearSecret = (bool) ($settings['clear_paypal_secret_key'] ?? false);
            $wasConfigured = filled(company_setting('paypal_secret_key'));

            if ($clearSecret && filled($replacement)) {
                return back()->withErrors([
                    'settings.paypal_secret_key' => __('Enter a replacement or clear the stored credential, not both.'),
                ]);
            }

            if (($settings['paypal_enabled'] ?? 'off') === 'on' && ($clearSecret || (blank($replacement) && !$wasConfigured))) {
                return back()->withErrors([
                    'settings.paypal_secret_key' => __('PayPal secret is required before enabling PayPal.'),
                ]);
            }

            unset($settings['clear_paypal_secret_key']);
            if ($clearSecret) {
                $settings['paypal_secret_key'] = null;
            } elseif (blank($replacement)) {
                unset($settings['paypal_secret_key']);
            }

            try {
                foreach ($settings as $key => $value) {
                    setSetting($key, $value, creatorId(), $key == "paypal_enabled");
                }

                $audit->recordSubmittedSecret('payment', 'paypal_secret_key', $wasConfigured, $replacement, $clearSecret);

                return redirect()->back()->with('success', __('PayPal settings save successfully.'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', __('Failed to update PayPal settings: ') . $e->getMessage());
            }           
        }
        return back()->with('error', __('Permission denied'));
    }
}
