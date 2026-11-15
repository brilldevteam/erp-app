<?php

namespace App\Services;

use App\Models\User;

/**
 * Platform-level Helpdesk email settings, owned by the super admin.
 *
 * Each event has its own on/off switch stored as a private super admin setting
 * (helpdesk_mail_<event>). Until a switch is saved, the events that existed before keep
 * following the legacy "Helpdesk Ticket" / "Helpdesk Ticket Reply" switches, so upgrading
 * sends exactly the emails that were sent before. New optional events start off.
 */
class HelpdeskNotificationSettings
{
    public const SUPPORT_EMAIL_KEY = 'helpdesk_support_email';
    public const MAX_SUPPORT_ADDRESSES = 5;

    /** event => legacy switch it inherits until saved (null = new event, off by default) */
    public const EVENTS = [
        'ticket_created_support' => 'Helpdesk Ticket',
        'ticket_created_confirmation' => null,
        'ticket_created_company' => null,
        'ticket_assigned' => 'Helpdesk Ticket Reply',
        'reply_to_customer' => 'Helpdesk Ticket Reply',
        'reply_to_support' => 'Helpdesk Ticket Reply',
        'status_changed' => 'Helpdesk Ticket Reply',
    ];

    /** The legacy notification switches this section replaces in Email Notification Settings. */
    public const LEGACY_SWITCHES = ['Helpdesk Ticket', 'Helpdesk Ticket Reply'];

    public static function switchKey(string $event): string
    {
        return "helpdesk_mail_{$event}";
    }

    public function enabled(string $event): bool
    {
        if (!array_key_exists($event, self::EVENTS)) {
            return false;
        }

        $value = admin_setting(self::switchKey($event));
        if ($value !== null && $value !== '') {
            return $value === 'on';
        }

        $legacy = self::EVENTS[$event];

        return $legacy !== null && admin_setting($legacy) === 'on';
    }

    /** @return string[] valid support mailbox addresses (may be empty) */
    public function supportMailbox(): array
    {
        return self::parseAddresses((string) admin_setting(self::SUPPORT_EMAIL_KEY));
    }

    /** Support mailbox, or every super admin login email when no mailbox is set. */
    public function supportRecipients(): array
    {
        return $this->supportMailbox() ?: User::where('type', 'superadmin')->pluck('email')->all();
    }

    /** Values for the super admin settings page: effective switches and the mailbox text. */
    public function pageValues(): array
    {
        return [
            'support_email' => implode(', ', $this->supportMailbox()),
            'events' => collect(array_keys(self::EVENTS))
                ->mapWithKeys(fn (string $event) => [$event => $this->enabled($event) ? 'on' : 'off'])
                ->all(),
        ];
    }

    /** @return string[] */
    public static function parseAddresses(string $value): array
    {
        return collect(preg_split('/[\s,;]+/', $value) ?: [])
            ->map(fn ($address) => strtolower(trim((string) $address)))
            ->filter(fn ($address) => $address !== '' && filter_var($address, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
    }
}
