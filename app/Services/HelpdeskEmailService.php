<?php

namespace App\Services;

use App\Jobs\SendHelpdeskEmail;
use App\Models\HelpdeskEmailDelivery;
use App\Models\HelpdeskReply;
use App\Models\HelpdeskStatusHistory;
use App\Models\HelpdeskTicket;
use App\Models\User;

/**
 * Queues Helpdesk emails. Every event has its own super admin switch
 * (see HelpdeskNotificationSettings); all mail is sent through the central SMTP by SendHelpdeskEmail.
 */
class HelpdeskEmailService
{
    /** Stored in helpdesk_email_deliveries.template for event-rendered emails. */
    public const TEMPLATE_PREFIX = 'helpdesk:';

    public function __construct(private readonly HelpdeskNotificationSettings $settings)
    {
    }

    public function ticketCreated(HelpdeskTicket $ticket): void
    {
        $payload = $this->payload($ticket, $ticket->description);

        $this->queue("ticket-created:{$ticket->id}", $ticket, 'ticket_created_support', $this->settings->supportRecipients(), $payload);

        $customerEmail = $this->customerEmail($ticket);
        $this->queue("ticket-confirmation:{$ticket->id}", $ticket, 'ticket_created_confirmation', [$customerEmail], $payload, $this->replyTo());

        // The customer's company hears about tickets raised by its own clients or staff, not about its own tickets.
        $companyEmail = $this->companyEmail($ticket);
        if ($companyEmail && strcasecmp($companyEmail, (string) $customerEmail) !== 0) {
            $this->queue("ticket-company:{$ticket->id}", $ticket, 'ticket_created_company', [$companyEmail], $payload);
        }
    }

    public function assigned(HelpdeskTicket $ticket, User $agent, int $historyId): void
    {
        $this->queue("ticket-assigned:{$historyId}", $ticket, 'ticket_assigned', [$agent->email], $this->payload($ticket, $ticket->description));
    }

    public function replied(HelpdeskTicket $ticket, HelpdeskReply $reply, User $actor): void
    {
        if ($reply->is_internal) {
            return;
        }

        $payload = $this->payload($ticket, $reply->message);

        if ($actor->isSupportSide()) {
            $this->queue("ticket-reply:{$reply->id}", $ticket, 'reply_to_customer', [$this->customerEmail($ticket)], $payload, $this->replyTo());

            return;
        }

        $recipients = $ticket->assignedAgent?->email ? [$ticket->assignedAgent->email] : $this->settings->supportRecipients();
        $this->queue("ticket-reply:{$reply->id}", $ticket, 'reply_to_support', $recipients, $payload);
    }

    public function statusChanged(HelpdeskTicket $ticket, HelpdeskStatusHistory $history): void
    {
        $payload = $this->payload($ticket, '') + [
            'status' => HelpdeskTicket::emailText(str_replace('_', ' ', (string) $history->to_status)),
        ];

        $this->queue("ticket-status:{$history->id}", $ticket, 'status_changed', [$ticket->requester?->email], $payload, $this->replyTo());
    }

    private function customerEmail(HelpdeskTicket $ticket): ?string
    {
        return $ticket->requester?->email ?: $ticket->creator?->email;
    }

    /** Company contact for the tenant that owns the ticket: its company email, else the company login. */
    private function companyEmail(HelpdeskTicket $ticket): ?string
    {
        $company = $ticket->creator;
        $requester = $ticket->requester;
        if (!$company || $company->type !== 'company' || !$requester || $requester->id === $company->id) {
            return null;
        }

        $email = company_setting('company_email', $company->id) ?: $company->email;

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /** Customer-facing emails reply to the support mailbox when one is set. */
    private function replyTo(): ?string
    {
        return $this->settings->supportMailbox()[0] ?? null;
    }

    private function payload(HelpdeskTicket $ticket, ?string $description): array
    {
        $requester = $ticket->requester ?: $ticket->creator;

        return [
            'company_name' => HelpdeskTicket::emailText($ticket->creator?->name),
            'requester_name' => HelpdeskTicket::emailText($requester?->name),
            'ticket_name' => HelpdeskTicket::emailText($ticket->title),
            'ticket_id' => $ticket->ticket_id,
            'ticket_url' => route('helpdesk-tickets.show', $ticket->id),
            'ticket_description' => HelpdeskTicket::emailText($description),
            'ticket_category' => HelpdeskTicket::emailText($ticket->category?->name),
            'ticket_priority' => HelpdeskTicket::emailText($ticket->priority),
        ];
    }

    private function queue(string $key, HelpdeskTicket $ticket, string $event, array $recipients, array $payload, ?string $replyTo = null): void
    {
        if (!$this->settings->enabled($event)) {
            return;
        }

        $recipients = collect($recipients)
            ->filter(fn ($email) => is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) && !str_ends_with(strtolower($email), '@import.local'))
            ->unique()->values()->all();

        if ($recipients === []) {
            return;
        }

        if ($replyTo) {
            $payload['reply_to'] = $replyTo;
        }

        $delivery = HelpdeskEmailDelivery::firstOrCreate(
            ['delivery_key' => $key],
            ['ticket_id' => $ticket->id, 'event' => $event, 'template' => self::TEMPLATE_PREFIX.$event, 'recipients' => $recipients, 'payload' => $payload]
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
