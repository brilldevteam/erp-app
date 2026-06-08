<?php

namespace Workdo\RecurringInvoiceBill\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class CompanySettingsController extends Controller
{
    public function store(Request $request)
    {
        if (Auth::user()->can('manage-recurring-invoice-bill')) {
            $request->validate([
                'recurring_sales_purchase_invoices' => 'required|in:on,off'
            ]);

            try {
                setSetting('recurring_sales_purchase_invoices', $request->recurring_sales_purchase_invoices, creatorId(),false);

                return redirect()->back()->with('success', __('Recurring invoice bill settings save successfully.'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', __('Failed to update settings: ') . $e->getMessage());
            }
        } else {
            return redirect()->back()->with('error', __('Permission denied'));
        }
    }
}
