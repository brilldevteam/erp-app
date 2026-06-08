<?php

use Workdo\PettyCashManagement\Http\Controllers\ReimbursementController;
use Workdo\PettyCashManagement\Http\Controllers\PettyCashExpenseController;
use Workdo\PettyCashManagement\Http\Controllers\PettyCashRequestController;
use Workdo\PettyCashManagement\Http\Controllers\PettyCashController;
use Workdo\PettyCashManagement\Http\Controllers\PettyCashCategoryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified', 'PlanModuleCheck:PettyCashManagement'])->group(function () {

    Route::prefix('petty-cash-management/petty-cash-categories')->name('petty-cash-management.petty-cash-categories.')->group(function () {
        Route::get('/', [PettyCashCategoryController::class, 'index'])->name('index');
        Route::post('/', [PettyCashCategoryController::class, 'store'])->name('store');
        Route::put('/{pettycashcategory}', [PettyCashCategoryController::class, 'update'])->name('update');
        Route::delete('/{pettycashcategory}', [PettyCashCategoryController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('petty-cash-management/petty-cashes')->name('petty-cash-management.petty-cashes.')->group(function () {
        Route::get('/', [PettyCashController::class, 'index'])->name('index');
        Route::post('/', [PettyCashController::class, 'store'])->name('store');
        Route::put('/{pettycash}', [PettyCashController::class, 'update'])->name('update');
        Route::post('/{pettycash}/approve', [PettyCashController::class, 'approve'])->name('approve');
        Route::delete('/{pettycash}', [PettyCashController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('petty-cash-management/petty-cash-requests')->name('petty-cash-management.petty-cash-requests.')->group(function () {
        Route::get('/', [PettyCashRequestController::class, 'index'])->name('index');
        Route::post('/', [PettyCashRequestController::class, 'store'])->name('store');
        Route::put('/{pettycashrequest}', [PettyCashRequestController::class, 'update'])->name('update');
        Route::put('/{pettycashrequest}/status', [PettyCashRequestController::class, 'updateStatus'])->name('update-status');
        Route::delete('/{pettycashrequest}', [PettyCashRequestController::class, 'destroy'])->name('destroy');
    });

    Route::get('petty-cash-management/users/{user}/categories', [PettyCashRequestController::class, 'getCategoriesByUser'])->name('pettycashmanagement.users.categories');

    Route::prefix('petty-cash-management/petty-cash-expenses')->name('petty-cash-management.petty-cash-expenses.')->group(function () {
        Route::get('/', [PettyCashExpenseController::class, 'index'])->name('index');
    });

    Route::prefix('petty-cash-management/reimbursements')->name('petty-cash-management.reimbursements.')->group(function () {
        Route::get('/', [ReimbursementController::class, 'index'])->name('index');
        Route::post('/', [ReimbursementController::class, 'store'])->name('store');
        Route::put('/{reimbursement}', [ReimbursementController::class, 'update'])->name('update');
        Route::put('/{reimbursement}/status', [ReimbursementController::class, 'updateStatus'])->name('update-status');
        Route::delete('/{reimbursement}', [ReimbursementController::class, 'destroy'])->name('destroy');
    });
});
