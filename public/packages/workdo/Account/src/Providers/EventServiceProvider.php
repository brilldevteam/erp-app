<?php

namespace Workdo\Account\Providers;

use App\Events\ApprovePurchaseReturn;
use App\Events\ApproveSalesReturn;
use App\Events\CreateTransfer;
use App\Events\DefaultData;
use App\Events\DestroyTransfer;
use App\Events\GivePermissionToRole;
use App\Events\PostPurchaseInvoice;
use App\Events\PostSalesInvoice;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Workdo\Account\Listeners\ApproveHolidayzRoomBookingListener;
use Workdo\Account\Listeners\ApproveMedicalOrderPaymentListener;
use Workdo\Account\Listeners\ApprovePettyCashListener;
use Workdo\Account\Listeners\BankAccountFieldUpdate;
use Workdo\Account\Listeners\CreateDebitNoteFromReturn;
use Workdo\Account\Listeners\CreateCreditNoteFromReturn;
use Workdo\Account\Listeners\UpdateMobileServicePaymentStatusLis;
use Workdo\Account\Listeners\DataDefault;
use Workdo\Account\Listeners\PostPurchaseInvoiceListener;
use Workdo\Account\Listeners\CreateTransferListener;
use Workdo\Account\Listeners\DestroyTransferListener;
use Workdo\Account\Listeners\GiveRoleToPermission;
use Workdo\Account\Listeners\PostSalesInvoiceListener;
use Workdo\Account\Listeners\UpdateRetainerPaymentStatusListener;
use Workdo\Retainer\Events\UpdateRetainerPaymentStatus;
use Workdo\Account\Listeners\UpdateCommissionPaymentStatusListener;
use Workdo\Commission\Events\UpdateCommissionPaymentStatus;
use Workdo\Account\Listeners\PaySalaryListener;
use Workdo\Hrm\Events\PaySalary;
use Workdo\Account\Listeners\CreatePosListener;
use Workdo\Fleet\Events\MarkFleetBookingPaymentPaid;
use Workdo\MobileServiceManagement\Events\UpdateMobileServicePaymentStatus;
use Workdo\Pos\Events\CreatePos;
use Workdo\Account\Listeners\MarkFleetBookingPaymentPaidListener;
use Workdo\Fleet\Events\CraeteFleetBookingPayment;
use Workdo\MobileServiceManagement\Events\CreateMobileServicePayment;
use Workdo\Account\Listeners\BeautyBookingPaymentListener;
use Workdo\DairyCattleManagement\Events\CreateDairyCattlePayment;
use Workdo\DairyCattleManagement\Events\UpdateDairyCattlePaymentStatus;
use Workdo\Account\Listeners\UpdateDairyCattlePaymentStatusListener;
use Workdo\CateringManagement\Events\CreateCateringOrderPayment;
use Workdo\CateringManagement\Events\UpdateCateringOrderPaymentStatus;
use Workdo\Account\Listeners\UpdateCateringOrderPaymentStatusListener;
use Workdo\Account\Listeners\UpdateSalesAgentCommissionPaymentStatusLis;
use Workdo\Account\Listeners\ApproveSalesAgentCommissionAdjustmentLis;
use Workdo\Account\Listeners\CompleteWarrantyClaimListener;
use Workdo\Account\Listeners\ConvertSalesRetainerListener;
use Workdo\Account\Listeners\MarkBeautyBookingPaymentPaidListener;
use Workdo\Account\Listeners\UpdateDairyCattleExpenseTrackingStatusListener;
use Workdo\BeautySpaManagement\Events\BeautyBookingPayments;
use Workdo\BeautySpaManagement\Events\CreateBeautyBookingPayment;
use Workdo\BeautySpaManagement\Events\MarkBeautyBookingPaymentPaid;
use Workdo\CateringManagement\Events\CreateCateringExpenseTracking;
use Workdo\CateringManagement\Events\MarkCateringExpenseTrackingAsPaid;
use Workdo\CateringManagement\Events\UpdateCateringExpenseTracking;
use Workdo\Commission\Events\CreateCommissionPayment;
use Workdo\CourierManagement\Events\CreateCourierPayment;
use Workdo\CourierManagement\Events\UpdateCourierPayment;
use Workdo\DairyCattleManagement\Events\CreateDairyCattleExpenseTracking;
use Workdo\DairyCattleManagement\Events\UpdateDairyCattleExpenseTracking;
use Workdo\DairyCattleManagement\Events\UpdateDairyCattleExpenseTrackingStatus;
use Workdo\EventsManagement\Events\CreateEventBookingPayment;
use Workdo\Fleet\Events\CreateFleetExpense;
use Workdo\Fleet\Events\UpdateFleetExpense;
use Workdo\GymManagement\Events\CreateMembershipPlanPayment;
use Workdo\PropertyManagement\Events\CreatePropertyPayment;
use Workdo\Hrm\Events\CreatePayroll;
use Workdo\Hrm\Events\UpdatePayroll;
use Workdo\LaundryManagement\Events\CreateLaundryExpense;
use Workdo\LaundryManagement\Events\CreateLaundryPayment;
use Workdo\LaundryManagement\Events\UpdateLaundryExpense;
use Workdo\LegalCaseManagement\Events\CreateCaseExpense;
use Workdo\LegalCaseManagement\Events\CreateFeeReceive;
use Workdo\LegalCaseManagement\Events\UpdateCaseExpense;
use Workdo\LegalCaseManagement\Events\UpdateFeeReceive;
use Workdo\MedicalLabManagement\Events\CreateMedicalOrderPayment;
use Workdo\ParkingManagement\Events\CreateParkingPayment;
use Workdo\RentalManagement\Events\CreateRentalMaintenance;
use Workdo\RentalManagement\Events\UpdateRentalMaintenance;
use Workdo\Retainer\Events\ConvertSalesRetainer;
use Workdo\SalesAgent\Events\CreateSalesAgentCommissionPayment;
use Workdo\SalesAgent\Events\UpdateSalesAgentCommissionPaymentStatus;
use Workdo\SalesAgent\Events\ApproveSalesAgentCommissionAdjustment;
use Workdo\SocietyManagement\Events\CreateSocietyMaintenanceBillPayment;
use Workdo\VehicleBookingManagement\Events\CreateVehicleBookingPayment;
use Workdo\Warranty\Events\CreateWarrantyClaim;
use Workdo\Account\Listeners\MarkCateringExpenseTrackingAsPaidListener;
use Workdo\Holidayz\Events\CreateHolidayzRoomBooking;
use Workdo\Holidayz\Events\HolidayzBookingPayments;
use Workdo\Holidayz\Events\UpdateHolidayzRoomBooking;
use Workdo\Account\Listeners\HolidayzBookingPaymentsListener;
use Workdo\Fleet\Events\PostFleetExpense;
use Workdo\Holidayz\Events\ApproveHolidayzRoomBooking;
use Workdo\Account\Listeners\PostFleetExpenseListener;
use Workdo\EventsManagement\Events\UpdateEventBookingPaymentStatus;
use Workdo\Account\Listeners\UpdateEventBookingPaymentStatusListener;
use Workdo\EventsManagement\Events\EventBookingPayments;
use Workdo\Account\Listeners\EventBookingPaymentsListener;
use Workdo\Account\Listeners\LaundryBookingPaymentsListener;
use Workdo\Account\Listeners\MarkCaseExpenseAsPaidListner;
use Workdo\Account\Listeners\MarkFeeReceiveAsClearedListener;
use Workdo\Account\Listeners\UpdateVehicleBookingPaymentStatusListener;
use Workdo\Account\Listeners\VehicleBookingPaymentsListener;
use Workdo\GymManagement\Events\MembershipPlanAssigned;
use Workdo\LegalCaseManagement\Events\MarkCaseExpenseAsPaid;
use Workdo\LegalCaseManagement\Events\MarkFeeReceiveAsCleared;
use Workdo\VehicleBookingManagement\Events\UpdateVehicleBookingPaymentStatus;
use Workdo\VehicleBookingManagement\Events\VehicleBookingPayments;
use Workdo\Account\Listeners\MembershipPlanAssignedListener;
use Workdo\Account\Listeners\ParkingBookingPaymentsListener;
use Workdo\Account\Listeners\UpdateLaundryExpenseStatusListener;
use Workdo\Account\Listeners\UpdateLaundryPaymentStatusListener;
use Workdo\Account\Listeners\UpdateParkingPaymentStatusListener;
use Workdo\LaundryManagement\Events\LaundryBookingPayments;
use Workdo\LaundryManagement\Events\UpdateLaundryExpenseStatus;
use Workdo\LaundryManagement\Events\UpdateLaundryPaymentStatus;
use Workdo\MedicalLabManagement\Events\ApproveMedicalOrderPayment;
use Workdo\ParkingManagement\Events\ParkingBookingPayments;
use Workdo\ParkingManagement\Events\UpdateParkingPaymentStatus;
use Workdo\PettyCashManagement\Events\ApprovePettyCash;
use Workdo\PettyCashManagement\Events\CreatePettyCash;
use Workdo\PettyCashManagement\Events\UpdatePettyCash;
use Workdo\Warranty\Events\CompleteWarrantyClaim;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        // Add your event listeners here
        DefaultData::class => [
            DataDefault::class,
        ],
        GivePermissionToRole::class => [
            GiveRoleToPermission::class,
        ],
        PostPurchaseInvoice::class => [
            PostPurchaseInvoiceListener::class,
        ],
        PostSalesInvoice::class => [
            PostSalesInvoiceListener::class,
        ],
        CreateTransfer::class => [
            CreateTransferListener::class,
        ],
        DestroyTransfer::class => [
            DestroyTransferListener::class,
        ],
        ApprovePurchaseReturn::class => [
            CreateDebitNoteFromReturn::class,
        ],
        ApproveSalesReturn::class => [
            CreateCreditNoteFromReturn::class,
        ],
        UpdateRetainerPaymentStatus::class => [
            UpdateRetainerPaymentStatusListener::class,
        ],
        ConvertSalesRetainer::class => [
            ConvertSalesRetainerListener::class,
        ],
        CreateCommissionPayment::class => [
            BankAccountFieldUpdate::class,
        ],
        UpdateCommissionPaymentStatus::class => [
            UpdateCommissionPaymentStatusListener::class,
        ],
        PaySalary::class => [
            PaySalaryListener::class,
        ],
        CreatePos::class => [
            BankAccountFieldUpdate::class,
            CreatePosListener::class,
        ],
        CreateMobileServicePayment::class => [
            BankAccountFieldUpdate::class,
        ],
        UpdateMobileServicePaymentStatus::class => [
            UpdateMobileServicePaymentStatusLis::class,
        ],
        CraeteFleetBookingPayment::class => [
            BankAccountFieldUpdate::class,
        ],
        MarkFleetBookingPaymentPaid::class => [
            MarkFleetBookingPaymentPaidListener::class,
        ],
        BeautyBookingPayments::class => [
            BeautyBookingPaymentListener::class,
        ],
        MarkBeautyBookingPaymentPaid::class => [
            MarkBeautyBookingPaymentPaidListener::class,
        ],
        CreateDairyCattlePayment::class => [
            BankAccountFieldUpdate::class,
        ],
        CreateDairyCattleExpenseTracking::class => [
            BankAccountFieldUpdate::class,
        ],
        UpdateDairyCattleExpenseTracking::class => [
            BankAccountFieldUpdate::class,
        ],
        UpdateDairyCattlePaymentStatus::class => [
            UpdateDairyCattlePaymentStatusListener::class,
        ],
        UpdateDairyCattleExpenseTrackingStatus::class => [
            UpdateDairyCattleExpenseTrackingStatusListener::class,
        ],
        CreateCateringOrderPayment::class => [
            BankAccountFieldUpdate::class,
        ],
        UpdateCateringOrderPaymentStatus::class => [
            UpdateCateringOrderPaymentStatusListener::class,
        ],
        MarkCateringExpenseTrackingAsPaid::class => [
            MarkCateringExpenseTrackingAsPaidListener::class,
        ],
        CreatePropertyPayment::class => [
            BankAccountFieldUpdate::class,
        ],
        CreatePayroll::class => [
            BankAccountFieldUpdate::class,
        ],
        UpdatePayroll::class => [
            BankAccountFieldUpdate::class,
        ],
        CreateSalesAgentCommissionPayment::class => [
            BankAccountFieldUpdate::class,
        ],
        UpdateSalesAgentCommissionPaymentStatus::class => [
            UpdateSalesAgentCommissionPaymentStatusLis::class,
        ],
        ApproveSalesAgentCommissionAdjustment::class => [
            ApproveSalesAgentCommissionAdjustmentLis::class,
        ],
        CreateFleetExpense::class => [
            BankAccountFieldUpdate::class,
        ],
        UpdateFleetExpense::class => [
            BankAccountFieldUpdate::class,
        ],
        PostFleetExpense::class => [
            PostFleetExpenseListener::class,
        ],
        CreateCourierPayment::class => [
            BankAccountFieldUpdate::class,
        ],
        UpdateCourierPayment::class => [
            BankAccountFieldUpdate::class,
        ],
        CreateMembershipPlanPayment::class => [
            BankAccountFieldUpdate::class,
        ],
        MembershipPlanAssigned::class => [
            MembershipPlanAssignedListener::class,
        ],
        CreateCaseExpense::class => [
            BankAccountFieldUpdate::class,
        ],
        UpdateCaseExpense::class => [
            BankAccountFieldUpdate::class,
        ],
        CreateParkingPayment::class => [
            BankAccountFieldUpdate::class,
        ],
        ParkingBookingPayments::class => [
            ParkingBookingPaymentsListener::class,
        ],
        UpdateParkingPaymentStatus::class => [
            UpdateParkingPaymentStatusListener::class,
        ],
        CreateLaundryPayment::class => [
            BankAccountFieldUpdate::class,
        ],
        CreateLaundryExpense::class => [
            BankAccountFieldUpdate::class,
        ],
        UpdateLaundryExpense::class => [
            BankAccountFieldUpdate::class,
            ],
        UpdateLaundryExpenseStatus::class => [
            UpdateLaundryExpenseStatusListener::class,
        ],
        UpdateLaundryPaymentStatus::class => [
            UpdateLaundryPaymentStatusListener::class,
        ],
        LaundryBookingPayments::class => [
            LaundryBookingPaymentsListener::class,
        ],
        CreateSocietyMaintenanceBillPayment::class => [
            BankAccountFieldUpdate::class,
        ],
        CreateEventBookingPayment::class => [
            BankAccountFieldUpdate::class,
        ],
        EventBookingPayments::class => [
            EventBookingPaymentsListener::class,
        ],
        UpdateEventBookingPaymentStatus::class => [
            UpdateEventBookingPaymentStatusListener::class,
        ],
        CreateMedicalOrderPayment::class => [
            BankAccountFieldUpdate::class,
        ],
        ApproveMedicalOrderPayment::class => [
            ApproveMedicalOrderPaymentListener::class,
        ],
        CreateBeautyBookingPayment::class => [
            BankAccountFieldUpdate::class,
        ],
        CreateFeeReceive::class => [
            BankAccountFieldUpdate::class,
        ],
        UpdateFeeReceive::class => [
            BankAccountFieldUpdate::class,
        ],
        MarkFeeReceiveAsCleared::class => [
            MarkFeeReceiveAsClearedListener::class,
        ],
        MarkCaseExpenseAsPaid::class => [
            MarkCaseExpenseAsPaidListner::class,
        ],
        CreateCaseExpense::class => [
            BankAccountFieldUpdate::class,
        ],
        UpdateCaseExpense::class => [
            BankAccountFieldUpdate::class,
        ],
        CreateRentalMaintenance::class => [
            BankAccountFieldUpdate::class,
        ],
        UpdateRentalMaintenance::class => [
            BankAccountFieldUpdate::class,
        ],
        CreateWarrantyClaim::class => [
            BankAccountFieldUpdate::class,
        ],
        CompleteWarrantyClaim::class => [
            CompleteWarrantyClaimListener::class,
        ],
        CreateVehicleBookingPayment::class => [
            BankAccountFieldUpdate::class,
        ],
        VehicleBookingPayments::class => [
            VehicleBookingPaymentsListener::class,
        ],
        UpdateVehicleBookingPaymentStatus::class => [
            UpdateVehicleBookingPaymentStatusListener::class,
        ],
        CreateCateringExpenseTracking::class => [
            BankAccountFieldUpdate::class,
        ],
        UpdateCateringExpenseTracking::class => [
            BankAccountFieldUpdate::class,
        ],
        CreateHolidayzRoomBooking::class => [
            BankAccountFieldUpdate::class,
        ],
        UpdateHolidayzRoomBooking::class => [
            BankAccountFieldUpdate::class,
        ],
        HolidayzBookingPayments::class => [
            HolidayzBookingPaymentsListener::class,
        ],
        ApproveHolidayzRoomBooking::class => [
            ApproveHolidayzRoomBookingListener::class,
        ],
        CreatePettyCash::class => [
            BankAccountFieldUpdate::class,
        ],
        UpdatePettyCash::class => [
            BankAccountFieldUpdate::class,
        ],
        ApprovePettyCash::class => [
            ApprovePettyCashListener::class,
        ],
    ];
}
