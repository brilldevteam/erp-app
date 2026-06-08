<?php

namespace Workdo\RecurringInvoiceBill\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RecurringSalesPurchaseInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'invoice_type',
        'recurring_duration',
        'cycles',
        'day_type',
        'count',
        'pending_cycle',
        'modify_date',
        'modify_due_date',
        'duplicate_invoices',
        'creator_id',
        'created_by',
    ];

    protected $casts = [
        'modify_date' => 'date',
        'modify_due_date' => 'date',
    ];

    public static $recurring_types = [
        'no'        => 'No',
        '1 day'     => 'Every 1 Day',
        '2 day'     => 'Every 2 Day',
        '3 day'     => 'Every 3 Day',
        '1 week'    => 'Every 1 Week',
        '2 week'    => 'Every 2 Week',
        '1 month'   => 'Every 1 Month',
        '2 month'   => 'Every 2 Month',
        '3 month'   => 'Every 3 Month',
        '6 month'   => 'Every 6 Month',
        '1 year'    => 'Every 1 Year',
        'custom'    => 'Custom',
    ];

    public static $day_types = [
        'day'   => 'Day(s)',
        'week'  => 'Week(s)',
        'month' => 'Month(s)',
        'year'  => 'Year(s)',
    ];
}
