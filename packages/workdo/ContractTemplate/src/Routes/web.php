<?php

use Illuminate\Support\Facades\Route;
use Workdo\ContractTemplate\Http\Controllers\ContractTemplateController;
use Workdo\ContractTemplate\Http\Controllers\ContractTemplateCommentController;
use Workdo\ContractTemplate\Http\Controllers\ContractTemplateNoteController;
use Workdo\ContractTemplate\Http\Controllers\ContractTemplateAttachmentController;

Route::middleware(['web', 'auth', 'verified', 'PlanModuleCheck:ContractTemplate'])->group(function () {

    Route::resource('contract-templates', ContractTemplateController::class)->names('contract-templates');

    Route::post('contract-templates/{contractTemplate}/convert-to-contract', [ContractTemplateController::class, 'convertToContract'])->name('contract-templates.convert-to-contract');
    Route::post('contracts/{contract}/convert-to-template', [ContractTemplateController::class, 'convertToTemplate'])->name('contracts.convert-to-template');
    Route::post('contracts/{contract}/convert-to-template', [ContractTemplateController::class, 'convertToTemplate'])->name('contract-templates.convert-to-template');
    Route::post('contract-templates/{contractTemplate}/duplicate', [ContractTemplateController::class, 'duplicate'])->name('contract-templates.duplicate');
    Route::patch('contract-templates/{contractTemplate}/status', [ContractTemplateController::class, 'updateStatus'])->name('contract-templates.update-status');
    Route::get('contract-templates/{contractTemplate}/preview', [ContractTemplateController::class, 'preview'])->name('contract-templates.preview');
    Route::get('contract-templates/{contractTemplate}/download', [ContractTemplateController::class, 'download'])->name('contract-templates.download');
    
    // Comments routes
    Route::post('contract-templates/{contractTemplate}/comments', [ContractTemplateCommentController::class, 'store'])->name('contract-template-comments.store');
    Route::put('contract-template-comments/{comment}', [ContractTemplateCommentController::class, 'update'])->name('contract-template-comments.update');
    Route::delete('contract-template-comments/{comment}', [ContractTemplateCommentController::class, 'destroy'])->name('contract-template-comments.destroy');
    
    // Notes routes
    Route::post('contract-templates/{contractTemplate}/notes', [ContractTemplateNoteController::class, 'store'])->name('contract-template-notes.store');
    Route::put('contract-template-notes/{note}', [ContractTemplateNoteController::class, 'update'])->name('contract-template-notes.update');
    Route::delete('contract-template-notes/{note}', [ContractTemplateNoteController::class, 'destroy'])->name('contract-template-notes.destroy');
    
    // Attachments routes
    Route::post('contract-templates/{contractTemplate}/attachments', [ContractTemplateAttachmentController::class, 'store'])->name('contract-template-attachments.store');
    Route::delete('contract-template-attachments/{attachment}', [ContractTemplateAttachmentController::class, 'destroy'])->name('contract-template-attachments.destroy');
});
