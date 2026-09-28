<?php
use Illuminate\Support\Facades\Route;
use Workdo\PurchaseOrder\Http\Controllers\PurchaseOrderController;
Route::middleware(['web','auth','verified','PlanModuleCheck:PurchaseOrder'])->group(function(){
 Route::resource('purchase-orders',PurchaseOrderController::class)->parameters(['purchase-orders'=>'purchaseOrder']);
 Route::post('purchase-orders/{purchaseOrder}/workflow/{action}',[PurchaseOrderController::class,'workflow'])->name('purchase-orders.workflow');
 Route::get('purchase-orders/{purchaseOrder}/convert',[PurchaseOrderController::class,'conversion'])->name('purchase-orders.conversion');
 Route::post('purchase-orders/{purchaseOrder}/convert',[PurchaseOrderController::class,'convert'])->name('purchase-orders.convert');
 Route::get('purchase-orders/{purchaseOrder}/attachments/{attachment}',[PurchaseOrderController::class,'downloadAttachment'])->name('purchase-orders.attachments.download');
 Route::delete('purchase-orders/{purchaseOrder}/attachments/{attachment}',[PurchaseOrderController::class,'destroyAttachment'])->name('purchase-orders.attachments.destroy');
});
