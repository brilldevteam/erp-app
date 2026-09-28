<?php

namespace Workdo\PurchaseOrder\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Workdo\PurchaseOrder\Models\PurchaseOrder;

class PurchaseOrderWorkflowService
{
    private const TRANSITIONS=['submit'=>['draft','pending_approval'],'approve'=>['pending_approval','approved'],'reject'=>['pending_approval','rejected'],'issue'=>['approved','issued']];
    public function transition(PurchaseOrder $order,string $action,int $userId,?string $comment=null,bool $earlyClose=false): PurchaseOrder
    {
        return DB::transaction(function()use($order,$action,$userId,$comment,$earlyClose){$order=PurchaseOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if($action==='cancel'){$allowed=in_array($order->order_status,['draft','pending_approval','approved'],true)||($order->order_status==='issued'&&!$order->invoiceLinks()->exists());$to='cancelled';}
            elseif($action==='close'){$allowed=$order->order_status==='issued'&&($order->billing_status==='billed'||$earlyClose);$to='closed';}
            else{[$from,$to]=self::TRANSITIONS[$action]??[null,null];$allowed=$order->order_status===$from;}
            if(!$allowed)throw ValidationException::withMessages(['status'=>__('This purchase order action is not available in its current state.')]);
            $from=$order->order_status;$changes=['order_status'=>$to];
            if($action==='approve')$changes+=['approved_by'=>$userId,'approved_at'=>now()];
            if($action==='issue')$changes+=['issued_by'=>$userId,'issued_at'=>now()];
            if($action==='close')$changes+=['closed_at'=>now()];
            $order->update($changes);
            if($action==='submit')$order->approvals()->create(['level'=>1,'status'=>'pending']);
            if(in_array($action,['approve','reject'],true))$order->approvals()->where('status','pending')->update(['approver_user_id'=>$userId,'status'=>$action==='approve'?'approved':'rejected','comment'=>$comment,'acted_at'=>now()]);
            $order->histories()->create(['from_status'=>$from,'to_status'=>$to,'action'=>$action,'comment'=>$comment,'user_id'=>$userId]);return $order;});
    }
}
