<?php

namespace App\Services;

/**
 * Event-specific subject and body for Helpdesk emails.
 *
 * Payload text values are produced by HelpdeskTicket::emailText(), so they are already
 * HTML-escaped and are inserted into the body as-is. Subjects are plain text, so they are
 * decoded back. The ticket URL is escaped here.
 */
class HelpdeskEmailContent
{
    public static function render(string $event, array $payload): array
    {
        $ticketId = (string) ($payload['ticket_id'] ?? '');
        $title = (string) ($payload['ticket_name'] ?? '');
        $plainTitle = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $from = (string) ($payload['company_name'] ?? '');
        $requester = (string) ($payload['requester_name'] ?? $from);
        $status = (string) ($payload['status'] ?? '');

        [$subject, $intro, $messageLabel] = match ($event) {
            'ticket_created_support' => [
                __('New Helpdesk ticket :id: :title', ['id' => $ticketId, 'title' => $plainTitle]),
                __('A new support ticket was submitted by :from.', ['from' => $from]),
                __('Description'),
            ],
            'ticket_created_confirmation' => [
                __('We received your support ticket :id', ['id' => $ticketId]),
                __('Thank you. Your support ticket has been received and our support team will reply as soon as possible.'),
                __('Description'),
            ],
            'ticket_created_company' => [
                __('Support ticket :id opened by :requester', ['id' => $ticketId, 'requester' => html_entity_decode($requester, ENT_QUOTES | ENT_HTML5, 'UTF-8')]),
                __(':requester from your account opened a support ticket with the Wazely support team.', ['requester' => $requester]),
                __('Description'),
            ],
            'ticket_assigned' => [
                __('Ticket :id has been assigned to you', ['id' => $ticketId]),
                __('A support ticket has been assigned to you.'),
                __('Description'),
            ],
            'reply_to_customer' => [
                __('New reply on your support ticket :id', ['id' => $ticketId]),
                __('The support team has replied to your ticket.'),
                __('Reply'),
            ],
            'reply_to_support' => [
                __('Customer reply on ticket :id', ['id' => $ticketId]),
                __(':from replied to a support ticket.', ['from' => $from]),
                __('Reply'),
            ],
            'status_changed' => [
                __('Your support ticket :id is now :status', ['id' => $ticketId, 'status' => html_entity_decode($status, ENT_QUOTES | ENT_HTML5, 'UTF-8')]),
                __('The status of your support ticket has changed to :status.', ['status' => $status]),
                null,
            ],
            default => throw new \InvalidArgumentException("Unknown Helpdesk email event [{$event}]."),
        };

        $rows = [
            __('Ticket ID') => $ticketId,
            __('Title') => $title,
            __('Category') => (string) ($payload['ticket_category'] ?? ''),
            __('Priority') => (string) ($payload['ticket_priority'] ?? ''),
        ];

        $details = collect($rows)
            ->filter(fn ($value) => $value !== '')
            ->map(fn ($value, $label) => '<tr><td style="padding:4px 12px 4px 0;color:#6b7280;white-space:nowrap;">'.e($label).'</td><td style="padding:4px 0;">'.$value.'</td></tr>')
            ->implode('');

        $message = (string) ($payload['ticket_description'] ?? '');
        $messageBlock = $messageLabel !== null && $message !== ''
            ? '<p style="margin:16px 0 4px;font-weight:bold;">'.e($messageLabel).'</p><div style="padding:12px;background:#f9fafb;border-radius:6px;white-space:pre-line;">'.$message.'</div>'
            : '';

        $url = e((string) ($payload['ticket_url'] ?? ''));
        $button = $url !== ''
            ? '<p style="margin:24px 0;"><a href="'.$url.'" style="display:inline-block;padding:10px 20px;background:#10b981;color:#ffffff;text-decoration:none;border-radius:6px;">'.e(__('View ticket')).'</a></p>'
            : '';

        $html = '<p>'.$intro.'</p>'
            .'<table style="border-collapse:collapse;margin-top:8px;">'.$details.'</table>'
            .$messageBlock
            .$button;

        return ['subject' => $subject, 'html' => $html];
    }
}
