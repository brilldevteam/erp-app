<?php

use Illuminate\Support\Facades\Route;
use Workdo\RecurringInvoiceBill\Http\Controllers\CompanySettingsController;
use Workdo\RecurringInvoiceBill\Http\Controllers\SuperAdminSettingsController;

// Backend Routes (Authenticated)
Route::middleware(['web', 'auth', 'verified', 'PlanModuleCheck:RecurringInvoiceBill'])->group(function () {

    // Settings Routes
    Route::post('/recurring-invoice-bill/settings', [CompanySettingsController::class, 'store'])->name('recurring-invoice-bill.settings.store');
    Route::post('/recurring-invoice-bill/superadmin/settings', [SuperAdminSettingsController::class, 'store'])->name('recurring-invoice-bill.superadmin.settings.store');

});
