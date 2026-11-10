<?php

namespace App\Services;

use App\Models\HelpdeskPopupNotification;
use App\Models\HelpdeskTicket;
use App\Models\User;
use Illuminate\Support\Collection;

class HelpdeskNotificationService
{
    public function ticketCreated(HelpdeskTicket $ticket): void
    {
        $ticket->loadMissing(['requester', 'creator']);
        $requester = $ticket->requester;
        if (!$requester || !in_array($requester->type, ['client', 'company'], true)) {
            return;
        }

        $message = $requester->type === 'client'
            ? "New support ticket from {$requester->name} under {$ticket->creator?->name}"
            : "New support ticket from {$ticket->creator?->name}";

        $this->notify(User::where('type', 'superadmin')->get(), $ticket, 'ticket_created', $message);
    }

    public function assigned(HelpdeskTicket $ticket, User $agent): void
    {
        $ticket->loadMissing(['requester', 'creator']);
        $requester = $ticket->requester;
        $message = $requester?->type === 'client'
            ? "Support ticket from {$requester->name} under {$ticket->creator?->name} assigned to you"
            : "Support ticket from {$ticket->creator?->name} assigned to you";

        $this->notify(collect([$agent]), $ticket, 'ticket_assigned', $message);
    }

    public function replied(HelpdeskTicket $ticket, User $actor): void
    {
        if ($actor->isSupportSide()) {
            if ($ticket->requester) {
                $this->notify(collect([$ticket->requester]), $ticket, 'agent_reply', "A support agent replied to your ticket {$ticket->ticket_id}");
            }
            return;
        }

        if ($ticket->assignedAgent) {
            $this->notify(collect([$ticket->assignedAgent]), $ticket, 'requester_reply', "The requester replied to support ticket {$ticket->ticket_id}");
            return;
        }

        $this->notify(User::where('type', 'superadmin')->get(), $ticket, 'requester_reply', "The requester replied to unassigned support ticket {$ticket->ticket_id}");
    }

    public function resolved(HelpdeskTicket $ticket): void
    {
        if ($ticket->requester) {
            $this->notify(collect([$ticket->requester]), $ticket, 'ticket_resolved', "Your support ticket {$ticket->ticket_id} has been resolved");
        }
    }

    private function notify(Collection $recipients, HelpdeskTicket $ticket, string $event, string $message): void
    {
        $recipients->unique('id')->each(function (User $recipient) use ($ticket, $event, $message) {
            HelpdeskPopupNotification::create([
                'user_id' => $recipient->id,
                'ticket_id' => $ticket->id,
                'event' => $event,
                'message' => $message,
            ]);
        });
    }
}
