# Quotation and Invoice Workflow

## Deployment

Run these commands from the Laravel project directory on Hostinger:

```bash
git pull origin main
rm -f public/hot
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

The document PDF engine requires `dompdf/dompdf`, which is declared in `composer.json`.

## Scheduler

Create one Hostinger cron job that runs every minute. Replace the paths with the values from the hosting account:

```cron
* * * * * /usr/bin/php /home/USER/domains/DOMAIN/public_html/artisan schedule:run >> /dev/null 2>&1
```

Laravel runs `documents:send-reminders` daily at 08:00 in the application timezone. Reminder offsets are configured under Document Templates. `-3` means three days before the due date, `0` means on the due date, and `7` means seven days overdue.

## Public Links and Payments

- Public links use random tokens; only SHA-256 token hashes are stored.
- Links expire and can be revoked from the invoice or quotation detail page.
- Configure Stripe or PayPal in their existing module settings before enabling Pay Now.
- Set `stripe_webhook_secret` in company settings and point Stripe to `/document-payments/webhook/stripe`.
- At least one active bank account is required so an online payment can create the existing customer payment and allocation records.

## Document Lifecycle

- Quotation: draft, sent, viewed, accepted or rejected, converted.
- Invoice: draft, posted, sent/viewed, partial, paid or overdue.
- Sending creates a branding/customer snapshot, PDF attachment, secure link, delivery record, and activity entry.
- Historical PDFs use their saved snapshot so later branding changes do not rewrite old documents.
