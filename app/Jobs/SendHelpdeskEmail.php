<?php

namespace App\Jobs;

use App\Models\EmailTemplate;
use App\Models\HelpdeskEmailDelivery;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

class SendHelpdeskEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $deliveryId)
    {
        $this->onQueue('emails');
    }

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(): void
    {
        $claimed = HelpdeskEmailDelivery::whereKey($this->deliveryId)
            ->where('status', 'pending')
            ->update(['status' => 'sending', 'attempts' => \DB::raw('attempts + 1'), 'last_error' => null]);

        if (!$claimed) {
            return;
        }

        $delivery = HelpdeskEmailDelivery::findOrFail($this->deliveryId);
        $ownerId = User::where('type', 'superadmin')->orderBy('id')->value('id');

        try {
            SetConfigEmail($ownerId);
            $result = EmailTemplate::sendEmailTemplate(
                $delivery->template,
                $delivery->recipients,
                $delivery->payload,
                $ownerId
            );

            if (!($result['is_success'] ?? false)) {
                throw new RuntimeException((string) ($result['error'] ?? 'Helpdesk email delivery failed.'));
            }

            $delivery->update(['status' => 'sent', 'sent_at' => now(), 'last_error' => null]);
        } catch (Throwable $exception) {
            $delivery->update(['status' => 'pending', 'last_error' => $exception->getMessage()]);
            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        HelpdeskEmailDelivery::whereKey($this->deliveryId)->update([
            'status' => 'failed',
            'last_error' => $exception?->getMessage(),
        ]);
    }
}
