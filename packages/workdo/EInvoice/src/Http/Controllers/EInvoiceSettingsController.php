<?php

namespace Workdo\EInvoice\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Workdo\EInvoice\Http\Requests\EInvoiceSettingsRequest;

class EInvoiceSettingsController extends Controller
{
    public function store(EInvoiceSettingsRequest $request)
    {
        if (Auth::user()->can('edit-einvoice-settings')) {
            $settings = $request->input('settings', []);
            try {
                foreach ($settings as $key => $value) {
                    setSetting($key, $value, creatorId(),false);
                }

                return redirect()->back()->with('success', __('E-Invoice settings save successfully.'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', __('Failed to update einvoice settings: ') . $e->getMessage());
            }
        }
        else
        {
            return redirect()->back()->with('error', __('Permission denied'));
        }
    }
}
