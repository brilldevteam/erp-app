<?php

namespace App\Services\Documents;

use App\Mail\DocumentMail;
use App\Models\DocumentDelivery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Throwable;

class DocumentDeliveryService
{
    public function __construct(
        private readonly DocumentRenderer $renderer,
        private readonly DocumentDataService $documents,
        private readonly DocumentShareService $shares,
        private readonly DocumentActivityService $activities,
    ) {
    }

    public function send(string $type, Model $document, array $data, bool $reminder = false): DocumentDelivery
    {
        $tenantId = (int) $document->created_by;
        $this->documents->persistSnapshot($type, $document);
        $share = $this->shares->create($type, $document, $tenantId, (int) ($data['expires_in_days'] ?? 30));
        $message = str_replace('{document_link}', $share['url'], $data['message']);

        $delivery = DocumentDelivery::create([
            'document_type' => $type,
            'document_id' => $document->getKey(),
            'delivery_type' => $reminder ? 'reminder' : 'email',
            'recipient' => $data['recipient'],
            'cc' => $data['cc'] ?? null,
            'bcc' => $data['bcc'] ?? null,
            'subject' => $data['subject'],
            'message' => $message,
            'status' => 'pending',
            'created_by' => $tenantId,
        ]);

        try {
            $mail = Mail::to($data['recipient']);
            if (!empty($data['cc'])) {
                $mail->cc(array_filter(array_map('trim', explode(',', $data['cc']))));
            }
            if (!empty($data['bcc'])) {
                $mail->bcc(array_filter(array_map('trim', explode(',', $data['bcc']))));
            }

            $number = $type === 'quotation' ? $document->quotation_number : $document->invoice_number;
            $mail->send(new DocumentMail(
                $data['subject'],
                $message,
                $this->renderer->pdf($type, $document),
                "{$type}-{$number}.pdf",
                $tenantId,
            ));

            $delivery->update(['status' => 'sent', 'sent_at' => now()]);
            $updates = ['sent_at' => $document->sent_at ?: now()];
            if ($type === 'quotation' && $document->status === 'draft') {
                $updates['status'] = 'sent';
            }
            if ($reminder && $type === 'invoice') {
                $updates['last_reminded_at'] = now();
            }
            $document->forceFill($updates)->save();
            $this->activities->record($type, $document, $reminder ? 'reminder_sent' : 'sent', [
                'recipient' => $data['recipient'],
                'delivery_id' => $delivery->id,
            ]);
        } catch (Throwable $error) {
            $delivery->update([
                'status' => 'failed',
                'failure_reason' => $error->getMessage(),
            ]);
            $this->activities->record($type, $document, 'delivery_failed', [
                'recipient' => $data['recipient'],
                'error' => $error->getMessage(),
            ]);
        }

        return $delivery->refresh();
    }
}
