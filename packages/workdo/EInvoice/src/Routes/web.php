<?php

use Illuminate\Support\Facades\Route;
use Workdo\EInvoice\Http\Controllers\DashboardController;
use Workdo\EInvoice\Http\Controllers\EInvoiceItemController;
use Workdo\EInvoice\Http\Controllers\EInvoiceSettingsController;
use Workdo\EInvoice\Http\Controllers\EInvoiceController;

Route::middleware(['web', 'auth', 'verified', 'PlanModuleCheck:EInvoice'])->group(function () {
    Route::post('/einvoice/settings', [EInvoiceSettingsController::class, 'store'])->name('einvoice.settings.store');
    Route::get('/invoice/download/{id}', [EInvoiceController::class, 'download'])->name('invoice.download');
});