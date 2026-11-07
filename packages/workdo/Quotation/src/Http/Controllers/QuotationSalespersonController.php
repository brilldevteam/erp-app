<?php

namespace Workdo\Quotation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Workdo\Quotation\Models\QuotationSalesperson;
use Workdo\Quotation\Models\SalesQuotation;

class QuotationSalespersonController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('manage-quotations'), 403);

        return Inertia::render('Quotation/Quotations/Salespersons/Index', [
            'salespersons' => QuotationSalesperson::query()
                ->where('created_by', creatorId())
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'is_active']),
        ]);
    }

    public function show(Request $request, QuotationSalesperson $salesperson): Response
    {
        abort_unless($request->user()->can('manage-quotations'), 403);
        abort_unless($salesperson->created_by === creatorId(), 404);

        $quotations = SalesQuotation::query()
            ->where('created_by', creatorId())
            ->where('salesperson_id', $salesperson->id)
            ->with([
                'customer:id,name,email',
                'customerDetails:user_id,company_name',
                'invoice:id,quotation_id,invoice_number,total_amount,paid_amount,balance_amount,exchange_rate,base_amount,status',
                'invoice.paymentAllocations:id,payment_id,invoice_id,allocated_amount,carrying_base_amount,settlement_base_amount',
                'invoice.paymentAllocations.payment:id,payment_number,payment_date,status',
            ])
            ->latest('quotation_date')
            ->get();

        $baseAmount = static fn ($amount, $rate = 1) => round((float) $amount * (float) ($rate ?: 1), 2);
        $quotationRows = $quotations->map(function (SalesQuotation $quotation) use ($baseAmount) {
            $invoice = $quotation->invoice;

            return [
                'id' => $quotation->id,
                'quotation_number' => $quotation->quotation_number,
                'quotation_date' => optional($quotation->quotation_date)->format('Y-m-d'),
                'due_date' => optional($quotation->due_date)->format('Y-m-d'),
                'customer_name' => $quotation->customerDetails?->company_name ?: $quotation->customer?->name ?: __('Unknown customer'),
                'subject' => $quotation->subject,
                'status' => $quotation->converted_to_invoice ? 'converted' : $quotation->status,
                'base_amount' => (float) ($quotation->base_amount ?: $baseAmount($quotation->total_amount, $quotation->exchange_rate)),
                'invoice' => $invoice ? [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'status' => $invoice->status,
                    'base_amount' => (float) ($invoice->base_amount ?: $baseAmount($invoice->total_amount, $invoice->exchange_rate)),
                    'paid_base' => $baseAmount($invoice->paid_amount, $invoice->exchange_rate),
                    'balance_base' => $baseAmount($invoice->balance_amount, $invoice->exchange_rate),
                ] : null,
            ];
        })->values();

        $payments = $quotations
            ->flatMap(fn (SalesQuotation $quotation) => $quotation->invoice?->paymentAllocations?->map(fn ($allocation) => [
                'id' => $allocation->payment?->id,
                'payment_number' => $allocation->payment?->payment_number,
                'payment_date' => optional($allocation->payment?->payment_date)->format('Y-m-d'),
                'status' => $allocation->payment?->status,
                'quotation_id' => $quotation->id,
                'quotation_number' => $quotation->quotation_number,
                'invoice_id' => $quotation->invoice?->id,
                'invoice_number' => $quotation->invoice?->invoice_number,
                'allocated_base' => (float) ($allocation->settlement_base_amount ?: $allocation->carrying_base_amount ?: $baseAmount($allocation->allocated_amount, $quotation->invoice?->exchange_rate)),
            ]) ?? collect())
            ->filter(fn ($payment) => $payment['id'])
            ->sortByDesc('payment_date')
            ->values();

        return Inertia::render('Quotation/Quotations/Salespersons/Show', [
            'salesperson' => $salesperson->only(['id', 'name', 'email', 'is_active']),
            'quotations' => $quotationRows,
            'payments' => $payments,
            'summary' => [
                'quotation_count' => $quotations->count(),
                'quoted_base' => round($quotationRows->sum('base_amount'), 2),
                'converted_base' => round($quotationRows->sum(fn ($quotation) => $quotation['invoice']['base_amount'] ?? 0), 2),
                'paid_base' => round($quotationRows->sum(fn ($quotation) => $quotation['invoice']['paid_base'] ?? 0), 2),
                'outstanding_base' => round($quotationRows->sum(fn ($quotation) => $quotation['invoice']['balance_base'] ?? 0), 2),
                'status_counts' => $quotationRows->countBy('status'),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('manage-quotations') || $request->user()->can('create-quotations'), 403);
        $data = $this->validated($request);
        $salesperson = QuotationSalesperson::create([...$data, 'is_active' => true, 'created_by' => creatorId()]);

        return response()->json($salesperson, 201);
    }

    public function update(Request $request, QuotationSalesperson $salesperson): JsonResponse
    {
        $this->authorizeCompany($request, $salesperson);
        $salesperson->update($this->validated($request, $salesperson));

        return response()->json($salesperson);
    }

    public function toggle(Request $request, QuotationSalesperson $salesperson): JsonResponse
    {
        $this->authorizeCompany($request, $salesperson);
        $salesperson->update(['is_active' => ! $salesperson->is_active]);

        return response()->json($salesperson);
    }

    public function destroy(Request $request, QuotationSalesperson $salesperson): JsonResponse
    {
        $this->authorizeCompany($request, $salesperson);
        $salesperson->delete();

        return response()->json(['deleted' => true]);
    }

    private function validated(Request $request, ?QuotationSalesperson $salesperson = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('quotation_salespersons')->where('created_by', creatorId())->ignore($salesperson?->id),
            ],
            'email' => ['nullable', 'email', 'max:255'],
        ]);
    }

    private function authorizeCompany(Request $request, QuotationSalesperson $salesperson): void
    {
        abort_unless(
            ($request->user()->can('manage-quotations') || $request->user()->can('edit-quotations'))
            && $salesperson->created_by === creatorId(),
            403
        );
    }
}
