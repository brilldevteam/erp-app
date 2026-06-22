<?php

namespace App\Http\Controllers;

use App\Models\DocumentActivity;
use App\Models\DocumentDelivery;
use App\Services\Documents\DocumentActivityService;
use App\Services\Documents\DocumentDataService;
use App\Services\Documents\DocumentDeliveryService;
use App\Services\Documents\DocumentRenderer;
use App\Services\Documents\DocumentShareService;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    public function __construct(
        private readonly DocumentDataService $documents,
        private readonly DocumentRenderer $renderer,
        private readonly DocumentShareService $shares,
        private readonly DocumentDeliveryService $deliveries,
        private readonly DocumentActivityService $activities,
    ) {
    }

    public function preview(string $type, int $id)
    {
        $document = $this->owned($type, $id);

        return response($this->renderer->html($type, $document))
            ->header('Content-Type', 'text/html');
    }

    public function pdf(string $type, int $id)
    {
        $document = $this->owned($type, $id);
        $this->activities->record($type, $document, 'downloaded');
        $number = $type === 'quotation' ? $document->quotation_number : $document->invoice_number;

        return response($this->renderer->pdf($type, $document))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "attachment; filename=\"{$type}-{$number}.pdf\"");
    }

    public function share(Request $request, string $type, int $id)
    {
        $document = $this->owned($type, $id);
        $data = $request->validate(['expires_in_days' => ['nullable', 'integer', 'min:1', 'max:365']]);
        $share = $this->shares->create($type, $document, creatorId(), (int) ($data['expires_in_days'] ?? 30));

        return response()->json(['url' => $share['url'], 'expires_at' => $share['link']->expires_at]);
    }

    public function revoke(string $type, int $id)
    {
        $document = $this->owned($type, $id);
        $this->shares->revoke($type, $document);

        return back()->with('success', __('Public document links have been revoked.'));
    }

    public function send(Request $request, string $type, int $id)
    {
        $document = $this->owned($type, $id);
        $data = $request->validate([
            'recipient' => ['required', 'email'],
            'cc' => ['nullable', 'string', 'max:1000'],
            'bcc' => ['nullable', 'string', 'max:1000'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:10000'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);
        $delivery = $this->deliveries->send($type, $document, $data);

        if ($delivery->status === 'failed') {
            return back()->with('error', __('Document delivery failed: ') . $delivery->failure_reason);
        }

        return back()->with('success', __('Document sent successfully.'));
    }

    public function remind(Request $request, int $id)
    {
        $document = $this->owned('invoice', $id);
        abort_if($document->balance_amount <= 0 || $document->status === 'paid', 422, __('This invoice has no outstanding balance.'));
        $data = $request->validate([
            'recipient' => ['required', 'email'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:10000'],
        ]);
        $delivery = $this->deliveries->send('invoice', $document, $data, true);

        return $delivery->status === 'sent'
            ? back()->with('success', __('Reminder sent successfully.'))
            : back()->with('error', __('Reminder failed: ') . $delivery->failure_reason);
    }

    public function history(string $type, int $id)
    {
        $document = $this->owned($type, $id);

        return response()->json([
            'activities' => DocumentActivity::where('document_type', $type)
                ->where('document_id', $document->getKey())->latest('created_at')->get(),
            'deliveries' => DocumentDelivery::where('document_type', $type)
                ->where('document_id', $document->getKey())->latest()->get(),
        ]);
    }

    private function owned(string $type, int $id)
    {
        abort_unless(in_array($type, ['invoice', 'quotation'], true), 404);
        $document = $this->documents->find($type, $id);
        $this->documents->assertTenant($document, creatorId());

        return $document;
    }
}
