<?php

use Illuminate\Support\Facades\Route;
use Workdo\Retainer\Http\Controllers\RetainerController;
use Workdo\Retainer\Http\Controllers\RetainerPaymentController;

Route::middleware(['web', 'auth', 'verified', 'PlanModuleCheck:Retainer'])->group(function () {
    Route::resource('retainers', RetainerController::class);
    Route::get('retainers/{retainer}/print', [RetainerController::class, 'print'])->name('retainers.print');
    Route::post('retainers/{retainer}/sent', [RetainerController::class, 'sent'])->name('retainers.sent');
    Route::post('retainers/{retainer}/accept', [RetainerController::class, 'accept'])->name('retainers.accept');
    Route::post('retainers/{retainer}/reject', [RetainerController::class, 'reject'])->name('retainers.reject');
    Route::post('retainers/{retainer}/duplicate', [RetainerController::class, 'duplicate'])->name('retainers.duplicate');
    Route::post('retainers/{retainer}/convert-to-invoice', [RetainerController::class, 'convertToInvoice'])->name('retainers.convert-to-invoice');
    Route::get('sales-retainers/warehouse/products', [RetainerController::class, 'getWarehouseProducts'])->name('retainers.warehouse.products');
    
    // Retainer Payments Routes
    Route::prefix('retainer-payments')->name('retainer-payments.')->group(function () {
        Route::get('/', [RetainerPaymentController::class, 'index'])->name('index');
        Route::post('/', [RetainerPaymentController::class, 'store'])->name('store');
        Route::delete('/{retainerPayment}', [RetainerPaymentController::class, 'destroy'])->name('destroy');
        Route::patch('/{retainerPayment}/status', [RetainerPaymentController::class, 'updateStatus'])->name('update-status');
        Route::get('/outstanding-retainers/{customer}', [RetainerPaymentController::class, 'getOutstandingRetainers'])->name('outstanding-retainers');
    });

});