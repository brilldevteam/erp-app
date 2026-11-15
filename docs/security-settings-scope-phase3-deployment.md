# Settings Scope Phase 3 Deployment - Helpdesk Notifications

This release adds a Helpdesk support mailbox, separate on/off switches for each Helpdesk email, and event-specific email wording. It has no migrations and no data changes.

Deploy after Phase 1 and Phase 2.

## What Changes

- New super admin section: **Settings → Helpdesk Notifications**. It holds the support mailbox (up to 5 addresses) and seven switches:

| Switch | Recipient | Starts as |
| --- | --- | --- |
| New ticket | Support mailbox, or every super admin login email when the mailbox is empty | Value of the old "Helpdesk Ticket" switch |
| Customer reply | Assigned agent, or the support mailbox when unassigned | Value of the old "Helpdesk Ticket Reply" switch |
| Ticket assigned | The assigned agent | Value of the old "Helpdesk Ticket Reply" switch |
| Ticket received confirmation | The customer who opened the ticket | Off |
| Support reply | The customer | Value of the old "Helpdesk Ticket Reply" switch |
| Status change | The customer | Value of the old "Helpdesk Ticket Reply" switch |
| Ticket opened by a client or staff member | The customer's company email (company setting, else the company login) | Off |

- Until a switch is saved, it keeps the value of the old switch, so deploying sends exactly the emails that were sent before. Nothing new is emailed until the super admin turns it on.
- The two old Helpdesk switches no longer appear in Email Notification Settings.
- Customer-facing emails use the first support mailbox address as reply-to.
- All Helpdesk email still goes through the central super admin SMTP on the `emails` queue.
- Deliveries queued before the release keep the original template and still send.

## Deploy

1. Back up the database.
2. Deploy the code, then clear caches:

```bash
php artisan optimize:clear
php artisan permission:cache-reset
```

3. Make sure a queue worker is processing the `emails` queue.
4. Rebuild frontend assets.

## Verify

1. As super admin, open Settings → Helpdesk Notifications. The switches match the old two switches; the two new ones are off.
2. Enter the support mailbox, turn on the events you want and save. Reload: the values persist.
3. Open a test ticket as a customer and confirm the support mailbox receives "New Helpdesk ticket …".
4. Reply as support and confirm the customer receives "New reply on your support ticket …" with reply-to set to the support mailbox.
5. Add an internal note and confirm no email is sent.

## Rollback

Redeploy the previous release and clear caches. The new `helpdesk_mail_*` and `helpdesk_support_email` settings rows are ignored by the previous release, and the old two switches were never changed.
