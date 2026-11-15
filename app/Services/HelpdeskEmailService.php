<?php

namespace App\Services;

use App\Jobs\SendHelpdeskEmail;
use App\Models\HelpdeskEmailDelivery;
use App\Models\HelpdeskReply;
use App\Models\HelpdeskStatusHistory;
use App\Models\HelpdeskTicket;
use App\Models\User;

class HelpdeskEmailService
{
    public function ticketCreated(HelpdeskTicket $ticket): void
    {
        $recipients = User::where('type', 'superadmin')->pluck('email')->all();
        $this->queue("ticket-created:{$ticket->id}", $ticket, 'ticket_created', 'Helpdesk Ticket', $recipients, $this->payload($ticket, $ticket->description));
    }

    public function assigned(HelpdeskTicket $ticket, User $agent, int $historyId): void
    {
        $this->queue("ticket-assigned:{$historyId}", $ticket, 'ticket_assigned', 'Helpdesk Ticket Reply', [$agent->email], $this->payload($ticket, "Ticket {$ticket->ticket_id} was assigned to you."));
    }

    public function replied(HelpdeskTicket $ticket, HelpdeskReply $reply, User $actor): void
    {
        if ($reply->is_internal) {
            return;
        }

        $supportSide = $actor->isSupportSide();
        $recipients = $supportSide
            ? [$ticket->requester?->email ?: $ticket->creator?->email]
            : [$ticket->assignedAgent?->email ?: User::where('type', 'superadmin')->orderBy('id')->value('email')];

        $this->queue("ticket-reply:{$reply->id}", $ticket, 'ticket_reply', 'Helpdesk Ticket Reply', $recipients, $this->payload($ticket, $reply->message));
    }

    public function statusChanged(HelpdeskTicket $ticket, HelpdeskStatusHistory $history): void
    {
        $this->queue("ticket-status:{$history->id}", $ticket, 'status_changed', 'Helpdesk Ticket Reply', [$ticket->requester?->email], $this->payload($ticket, "Ticket {$ticket->ticket_id} status changed to ".str_replace('_', ' ', $history->to_status).'.'));
    }

    private function payload(HelpdeskTicket $ticket, string $description): array
    {
        return [
            'company_name' => HelpdeskTicket::emailText($ticket->creator?->name),
            'ticket_name' => HelpdeskTicket::emailText($ticket->title),
            'ticket_id' => $ticket->ticket_id,
            'ticket_url' => route('helpdesk-tickets.show', $ticket->id),
            'ticket_description' => HelpdeskTicket::emailText($description),
            'ticket_category' => HelpdeskTicket::emailText($ticket->category?->name),
            'ticket_priority' => HelpdeskTicket::emailText($ticket->priority),
        ];
    }

    private function queue(string $key, HelpdeskTicket $ticket, string $event, string $template, array $recipients, array $payload): void
    {
        if (admin_setting($template) !== 'on') {
            return;
        }

        $recipients = collect($recipients)
            ->filter(fn ($email) => is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) && !str_ends_with(strtolower($email), '@import.local'))
            ->unique()->values()->all();

        if ($recipients === []) {
            return;
        }

        $delivery = HelpdeskEmailDelivery::firstOrCreate(
            ['delivery_key' => $key],
            ['ticket_id' => $ticket->id, 'event' => $event, 'template' => $template, 'recipients' => $recipients, 'payload' => $payload]
        );

        if ($delivery->wasRecentlyCreated) {
            try {
                SendHelpdeskEmail::dispatch($delivery->id);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }
    }
}
