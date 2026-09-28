<?php

namespace Workdo\PurchaseOrder\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Workdo\ProductService\Models\ProductServiceItem;
use Workdo\ProductService\Models\ProductServiceTax;
use Workdo\PurchaseOrder\Http\Requests\StorePurchaseOrderRequest;
use Workdo\PurchaseOrder\Http\Requests\UpdatePurchaseOrderRequest;
use Workdo\PurchaseOrder\Models\PurchaseOrder;
use Workdo\PurchaseOrder\Models\PurchaseOrderAttachment;
use Workdo\PurchaseOrder\Services\PurchaseOrderService;
use Workdo\PurchaseOrder\Services\PurchaseOrderToInvoiceService;
use Workdo\PurchaseOrder\Services\PurchaseOrderWorkflowService;

class PurchaseOrderController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(Auth::user()->can('manage-purchase-orders'),403);
        $query=PurchaseOrder::with(['vendor:id,name','warehouse:id,name','creator:id,name'])->where('created_by',creatorId());
        if(!Auth::user()->can('manage-any-purchase-orders'))$query->where('creator_id',Auth::id());
        $query->when($request->search,fn($q,$v)=>$q->where('purchase_order_number','like',"%{$v}%"))
            ->when($request->vendor_id,fn($q,$v)=>$q->where('vendor_id',$v))->when($request->warehouse_id,fn($q,$v)=>$q->where('warehouse_id',$v))
            ->when($request->order_status,fn($q,$v)=>$q->where('order_status',$v))->when($request->billing_status,fn($q,$v)=>$q->where('billing_status',$v));
        if ($request->date_range) {
            $dates = explode(' - ', $request->date_range);
            if (count($dates) === 2) $query->whereBetween('order_date', $dates);
        }
        $sort=$request->get('sort','created_at'); $direction=$request->get('direction','desc');
        if(!in_array($sort,['purchase_order_number','order_date','expected_delivery_date','total_amount','order_status','billing_status','created_at'],true))$sort='created_at';
        if(!in_array($direction,['asc','desc'],true))$direction='desc';
        return Inertia::render('PurchaseOrder/PurchaseOrders/Index',[
            'orders'=>$query->orderBy($sort,$direction)->paginate($request->integer('per_page',10))->withQueryString(),
            'vendors'=>User::where('type','vendor')->where('created_by',creatorId())->select('id','name')->orderBy('name')->get(),
            'warehouses'=>Warehouse::where('created_by',creatorId())->where('is_active',true)->select('id','name')->orderBy('name')->get(),
            'filters'=>$request->only(['search','vendor_id','warehouse_id','order_status','billing_status','date_range'])
        ]);
    }

    public function create(){abort_unless(Auth::user()->can('create-purchase-orders'),403);return Inertia::render('PurchaseOrder/PurchaseOrders/Create',$this->options());}
    public function store(StorePurchaseOrderRequest $request,PurchaseOrderService $service){abort_unless(Auth::user()->can('create-purchase-orders'),403);$order=$service->create($request->validated(),Auth::id(),creatorId());$this->attachments($order,$request);return redirect()->route('purchase-orders.show',$order)->with('success',__('Purchase order created successfully.'));}
    public function show(PurchaseOrder $purchaseOrder){$this->authorizeOrder($purchaseOrder,'view-purchase-orders');$purchaseOrder->load(['vendor','vendorDetails','warehouse','creator','items.product','items.taxes','items.allocations','approvals','histories.user','attachments.uploader','invoiceLinks.invoice']);return Inertia::render('PurchaseOrder/PurchaseOrders/Show',['order'=>$purchaseOrder]);}
    public function edit(PurchaseOrder $purchaseOrder){$this->authorizeOrder($purchaseOrder,'edit-purchase-orders');abort_unless($purchaseOrder->isEditable(),422,__('Only draft purchase orders can be edited.'));$purchaseOrder->load('items.taxes');return Inertia::render('PurchaseOrder/PurchaseOrders/Edit',['order'=>$purchaseOrder,...$this->options()]);}
    public function update(UpdatePurchaseOrderRequest $request,PurchaseOrder $purchaseOrder,PurchaseOrderService $service){$this->authorizeOrder($purchaseOrder,'edit-purchase-orders');$service->update($purchaseOrder,$request->validated());$this->attachments($purchaseOrder,$request);return redirect()->route('purchase-orders.show',$purchaseOrder)->with('success',__('Purchase order updated successfully.'));}
    public function destroy(PurchaseOrder $purchaseOrder){$this->authorizeOrder($purchaseOrder,'delete-purchase-orders');abort_unless($purchaseOrder->isEditable(),422,__('Only draft purchase orders can be deleted.'));Storage::disk('public')->deleteDirectory("purchase-orders/{$purchaseOrder->id}");$purchaseOrder->delete();return redirect()->route('purchase-orders.index')->with('success',__('Purchase order deleted successfully.'));}

    public function workflow(Request $request,PurchaseOrder $purchaseOrder,string $action,PurchaseOrderWorkflowService $service){$permissions=['submit'=>'submit-purchase-orders','approve'=>'approve-purchase-orders','reject'=>'reject-purchase-orders','issue'=>'issue-purchase-orders','cancel'=>'cancel-purchase-orders','close'=>'close-purchase-orders'];abort_unless(isset($permissions[$action]),404);$this->authorizeOrder($purchaseOrder,$permissions[$action]);$request->validate(['comment'=>'nullable|string|max:2000','early_close'=>'nullable|boolean']);if($request->boolean('early_close'))abort_unless(Auth::user()->can('close-purchase-orders-early'),403);$service->transition($purchaseOrder,$action,Auth::id(),$request->comment,$request->boolean('early_close'));return back()->with('success',__('Purchase order status updated successfully.'));}

    public function conversion(PurchaseOrder $purchaseOrder){$this->authorizeOrder($purchaseOrder,'convert-purchase-orders');abort_unless(Auth::user()->can('create-purchase-invoices'),403);$purchaseOrder->load(['vendor','items.product','items.allocations']);return Inertia::render('PurchaseOrder/PurchaseOrders/Convert',['order'=>$purchaseOrder]);}
    public function convert(Request $request,PurchaseOrder $purchaseOrder,PurchaseOrderToInvoiceService $service){$this->authorizeOrder($purchaseOrder,'convert-purchase-orders');abort_unless(Auth::user()->can('create-purchase-invoices'),403);$data=$request->validate(['quantities'=>'required|array','quantities.*'=>'nullable|numeric|min:0']);$invoice=$service->convert($purchaseOrder,$data['quantities'],Auth::id());return redirect()->route('purchase-invoices.show',$invoice)->with('success',__('Draft purchase invoice created successfully.'));}

    public function downloadAttachment(PurchaseOrder $purchaseOrder,PurchaseOrderAttachment $attachment){$this->authorizeOrder($purchaseOrder,'view-purchase-orders');abort_unless($attachment->purchase_order_id===$purchaseOrder->id,404);return Storage::disk('public')->download($attachment->path,$attachment->name);}
    public function destroyAttachment(PurchaseOrder $purchaseOrder,PurchaseOrderAttachment $attachment){$this->authorizeOrder($purchaseOrder,'edit-purchase-orders');abort_unless($purchaseOrder->isEditable()&&$attachment->purchase_order_id===$purchaseOrder->id,403);Storage::disk('public')->delete($attachment->path);$attachment->delete();return back()->with('success',__('Attachment deleted.'));}

    private function authorizeOrder(PurchaseOrder $order,string $permission):void{abort_unless(Auth::user()->can($permission)&&(int)$order->created_by===(int)creatorId(),403);if(!Auth::user()->can('manage-any-purchase-orders'))abort_unless((int)$order->creator_id===(int)Auth::id(),403);}
    private function options():array{return ['vendors'=>User::where('type','vendor')->where('created_by',creatorId())->select('id','name','email')->orderBy('name')->get(),'warehouses'=>Warehouse::where('created_by',creatorId())->where('is_active',true)->select('id','name','address')->get(),'products'=>ProductServiceItem::where('created_by',creatorId())->where('is_active',true)->select('id','name','sku','description','purchase_price','unit','tax_ids','type')->orderBy('name')->get(),'taxes'=>ProductServiceTax::where('created_by',creatorId())->select('id','tax_name','rate')->orderBy('tax_name')->get(),'defaultCurrency'=>company_setting('defaultCurrency',creatorId())?:'USD'];}
    private function attachments(PurchaseOrder $order,Request $request):void{foreach($request->file('attachments',[]) as $file){$path=$file->store("purchase-orders/{$order->id}/attachments",'public');$order->attachments()->create(['name'=>$file->getClientOriginalName(),'path'=>$path,'mime_type'=>$file->getMimeType(),'size'=>$file->getSize(),'uploaded_by'=>Auth::id()]);}}
}
