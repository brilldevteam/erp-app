<?php

use Illuminate\Support\Facades\Route;
use Workdo\Quotation\Http\Controllers\QuotationController;
use Workdo\Quotation\Http\Controllers\QuotationSalespersonController;

Route::middleware(['web', 'auth', 'verified', 'PlanModuleCheck:Quotation'])->group(function () {
    Route::resource('quotations', QuotationController::class);
    Route::get('quotations/{quotation}/print', [QuotationController::class, 'print'])->name('quotations.print');
    Route::post('quotations/{quotation}/sent', [QuotationController::class, 'sent'])->name('quotations.sent');
    Route::post('quotations/{quotation}/approve', [QuotationController::class, 'approve'])->name('quotations.approve');
    Route::post('quotations/{quotation}/reject', [QuotationController::class, 'reject'])->name('quotations.reject');
    Route::get('quotations/{quotation}/convert-to-invoice', [QuotationController::class, 'convertToInvoice'])->name('quotations.convert-to-invoice');
    Route::post('quotations/{quotation}/create-revision', [QuotationController::class, 'createRevision'])->name('quotations.create-revision');
    Route::post('quotations/{quotation}/duplicate', [QuotationController::class, 'duplicate'])->name('quotations.duplicate');
    Route::get('sales-quotations/warehouse/products', [QuotationController::class, 'getWarehouseProducts'])->name('quotations.warehouse.products');
    Route::post('quotation-salespersons', [QuotationSalespersonController::class, 'store'])->name('quotation-salespersons.store');
    Route::put('quotation-salespersons/{salesperson}', [QuotationSalespersonController::class, 'update'])->name('quotation-salespersons.update');
    Route::patch('quotation-salespersons/{salesperson}/toggle', [QuotationSalespersonController::class, 'toggle'])->name('quotation-salespersons.toggle');
    Route::delete('quotation-salespersons/{salesperson}', [QuotationSalespersonController::class, 'destroy'])->name('quotation-salespersons.destroy');

});
