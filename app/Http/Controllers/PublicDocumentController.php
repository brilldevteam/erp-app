<?php

namespace App\Http\Controllers;

use App\Services\Documents\DocumentActivityService;
use App\Services\Documents\DocumentDataService;
use App\Services\Documents\DocumentRenderer;
use App\Services\Documents\DocumentShareService;
use Illuminate\Http\Request;

class PublicDocumentController extends Controller
{
    public function __construct(
        private readonly DocumentDataService $documents,
        private readonly DocumentRenderer $renderer,
        private readonly DocumentShareService $shares,
        private readonly DocumentActivityService $activities,
    ) {
    }

    public function show(Request $request, string $token)
    {
        [$link, $document] = $this->resolve($token);
        $this->shares->recordView($link, $document, $request);

        return view('documents.show', [
            'document' => $this->documents->normalize($link->document_type, $document),
            'shareToken' => $token,
            'paymentProviders' => $link->document_type === 'invoice' ? $this->paymentProviders($link->created_by) : [],
        ]);
    }

    public function pdf(string $token)
    {
        [$link, $document] = $this->resolve($token);
        $this->activities->record($link->document_type, $document, 'downloaded', [], 'customer');
        $number = $link->document_type === 'quotation' ? $document->quotation_number : $document->invoice_number;

        return response($this->renderer->pdf($link->document_type, $document))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "attachment; filename=\"{$link->document_type}-{$number}.pdf\"");
    }

    public function accept(Request $request, string $token)
    {
        [$link, $quotation] = $this->resolve($token, 'quotation');
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'comment' => ['nullable', 'string', 'max:2000']]);
        abort_unless($quotation->status === 'sent' && !$quotation->converted_to_invoice, 422, __('This quotation can no longer be accepted.'));
        abort_if($quotation->due_date && $quotation->due_date->isPast(), 422, __('This quotation has expired.'));

        $quotation->update([
            'status' => 'accepted',
            'accepted_at' => now(),
            'customer_action_name' => $data['name'],
            'customer_action_comment' => $data['comment'] ?? null,
        ]);
        $this->activities->record('quotation', $quotation, 'accepted', ['comment' => $data['comment'] ?? null], 'customer', null, $data['name']);

        return redirect()->route('documents.public.show', $token)->with('success', __('Quotation accepted successfully.'));
    }

    public function reject(Request $request, string $token)
    {
        [$link, $quotation] = $this->resolve($token, 'quotation');
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'comment' => ['nullable', 'string', 'max:2000']]);
        abort_unless($quotation->status === 'sent' && !$quotation->converted_to_invoice, 422, __('This quotation can no longer be rejected.'));

        $quotation->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'customer_action_name' => $data['name'],
            'customer_action_comment' => $data['comment'] ?? null,
        ]);
        $this->activities->record('quotation', $quotation, 'rejected', ['comment' => $data['comment'] ?? null], 'customer', null, $data['name']);

        return redirect()->route('documents.public.show', $token)->with('success', __('Quotation rejected.'));
    }

    private function resolve(string $token, ?string $expectedType = null): array
    {
        $link = $this->shares->resolve($token);
        abort_if($expectedType && $link->document_type !== $expectedType, 404);
        $document = $this->documents->find($link->document_type, $link->document_id);
        abort_unless((int) $document->created_by === (int) $link->created_by, 404);

        return [$link, $document];
    }

    private function paymentProviders(int $tenantId): array
    {
        return [
            'stripe' => Module_is_active('Stripe', $tenantId) && company_setting('stripe_enabled', $tenantId) === 'on',
            'paypal' => Module_is_active('Paypal', $tenantId) && company_setting('paypal_enabled', $tenantId) === 'on',
        ];
    }
}
