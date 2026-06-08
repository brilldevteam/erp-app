<?php

use Illuminate\Support\Facades\Route;
use Workdo\Workflow\Http\Controllers\WorkflowController;

Route::middleware(['web', 'auth', 'verified', 'PlanModuleCheck:Workflow'])->group(function () {
    Route::prefix('workflow')->name('workflow.')->group(function () {
        Route::get('/', [WorkflowController::class, 'index'])->name('index');
        Route::get('/create', [WorkflowController::class, 'create'])->name('create');
        Route::post('/', [WorkflowController::class, 'store'])->name('store');
        Route::get('/{workflow}/edit', [WorkflowController::class, 'edit'])->name('edit');
        Route::put('/{workflow}', [WorkflowController::class, 'update'])->name('update');
        Route::delete('/{workflow}', [WorkflowController::class, 'destroy'])->name('destroy');
        Route::get('/field-values', [WorkflowController::class, 'getFieldValues'])->name('field-values');
        Route::get('/staff-list', [WorkflowController::class, 'getStaffList'])->name('staff-list');
    });
});
