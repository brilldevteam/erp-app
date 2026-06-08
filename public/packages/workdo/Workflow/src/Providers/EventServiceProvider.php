<?php

namespace Workdo\Workflow\Providers;

use App\Events\CreatePurchaseInvoice;
use App\Events\CreatePurchaseReturn;
use App\Events\CreateSalesInvoice;
use App\Events\CreateSalesReturn;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Workdo\Account\Events\CreateCustomerPayment;
use Workdo\Account\Events\CreateExpense;
use Workdo\Account\Events\CreateRevenue;
use Workdo\Account\Events\CreateVendorPayment;
use Workdo\Hrm\Events\CreateAward;
use Workdo\Hrm\Events\CreateLeaveApplication;
use Workdo\Hrm\Events\CreateTermination;
use Workdo\Lead\Events\CreateDeal;
use Workdo\Lead\Events\CreateLead;
use Workdo\Pos\Events\CreatePos;
use Workdo\Taskly\Events\CreateProject;
use Workdo\Workflow\Listeners\CreateAwardLis;
use Workdo\Workflow\Listeners\CreateSalesInvoiceLis;
use Workdo\Workflow\Listeners\CreateSalesReturnLis;
use Workdo\Workflow\Listeners\CreatePurchaseInvoiceLis;
use Workdo\Workflow\Listeners\CreatePurchaseReturnLis;
use Workdo\Workflow\Listeners\CreateProjectLis;
use Workdo\Workflow\Listeners\CreateCustomerPaymentLis;
use Workdo\Workflow\Listeners\CreateVendorPaymentLis;
use Workdo\Workflow\Listeners\CreateRevenueLis;
use Workdo\Workflow\Listeners\CreateExpenseLis;
use Workdo\Workflow\Listeners\CreateLeadLis;
use Workdo\Workflow\Listeners\CreateDealLis;
use Workdo\Workflow\Listeners\CreateLeaveApplicationLis;
use Workdo\Workflow\Listeners\CreateTerminationLis;
use Workdo\Workflow\Listeners\CreatePosLis;
use Workdo\Contract\Events\CreateContract;
use Workdo\Workflow\Listeners\CreateContractLis;
use Workdo\Training\Events\CreateTrainer;
use Workdo\Workflow\Listeners\CreateTrainerLis;
use Workdo\Holidayz\Events\CreateHolidayzRoomBooking;
use Workdo\Workflow\Listeners\CreateHolidayzRoomBookingLis;
use Workdo\Sales\Events\CreateSalesOrder;
use Workdo\Workflow\Listeners\CreateSalesOrderLis;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        CreateSalesInvoice::class => [
            CreateSalesInvoiceLis::class,
        ],
        CreateSalesReturn::class => [
            CreateSalesReturnLis::class,
        ],
        CreatePurchaseInvoice::class => [
            CreatePurchaseInvoiceLis::class,
        ],
        CreatePurchaseReturn::class => [
            CreatePurchaseReturnLis::class,
        ],
        CreateProject::class => [
            CreateProjectLis::class,
        ],
        CreateCustomerPayment::class => [
            CreateCustomerPaymentLis::class,
        ],
        CreateVendorPayment::class => [
            CreateVendorPaymentLis::class,
        ],
        CreateRevenue::class => [
            CreateRevenueLis::class,
        ],
        CreateExpense::class => [
            CreateExpenseLis::class,
        ],
        CreateLead::class => [
            CreateLeadLis::class,
        ],
        CreateDeal::class => [
            CreateDealLis::class,
        ],
        CreateAward::class => [
            CreateAwardLis::class,
        ],
        CreateTermination::class => [
            CreateTerminationLis::class,
        ],
        CreateLeaveApplication::class => [
            CreateLeaveApplicationLis::class,
        ],
        CreatePos::class => [
            CreatePosLis::class,
        ],
        CreateContract::class => [
            CreateContractLis::class,
        ],
        CreateTrainer::class => [
            CreateTrainerLis::class,
        ],
        CreateHolidayzRoomBooking::class => [
            CreateHolidayzRoomBookingLis::class,
        ],
        CreateSalesOrder::class => [
            CreateSalesOrderLis::class,
        ],
    ];
}
