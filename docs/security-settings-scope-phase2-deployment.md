# Settings Scope Phase 2 Deployment

This release centralizes AI credentials, separates platform and company settings, and adds central SMTP fallback for companies without a complete custom SMTP configuration. Do not include credential values in deployment logs.

## Behaviour Changes To Expect

- AI features (AI Agent and AI Assistant buttons) are unavailable for every company from deployment until the superadmin enters the central provider, model and key on the AI Settings page. Plan to enter the rotated keys immediately after deployment.
- Company SMTP is now optional for every company email, not only template emails: `SetConfigEmail()` falls back to the central superadmin SMTP when a company has no complete SMTP (host and sender address), keeping the company name as sender name and the company email as reply-to. Emails that previously failed silently for companies without SMTP (invoices, reminders, workflows, portal invitations, password changes and similar) will start to be delivered through the central mailbox. Confirm the central SMTP account's sending limits before deploying.
- When an email sent through the central mailer fails, company users see a generic message; the exception class is logged without credentials.

## Before Deployment

1. Back up the production database and application configuration, then verify the backup can be opened.
2. Record the company AI credential rows that will be cleared:

```sql
SELECT COUNT(*) AS affected_rows
FROM settings s
INNER JOIN users u ON u.id = s.created_by
WHERE u.type = 'company'
  AND s.`key` IN ('ai_api_key', 'ai_agent_api_key')
  AND s.value IS NOT NULL
  AND s.value <> '';
```

Do not select or export the credential values into console output or deployment logs.

## Deploy Application And Permissions

```bash
php artisan migrate --force
php artisan permission:cache-reset
```

Confirm the superadmin role has `manage-platform-ai-settings` and `edit-platform-ai-settings`. Company roles must not have either permission.

## Clear Legacy Company AI Keys

MySQL commits `CREATE TABLE ... AS SELECT` immediately. Create the rollback table before starting the update transaction:

```sql
CREATE TABLE settings_scope_phase2_ai_backup_20261116 AS
SELECT s.*
FROM settings s
INNER JOIN users u ON u.id = s.created_by
WHERE u.type = 'company'
  AND s.`key` IN ('ai_api_key', 'ai_agent_api_key');

START TRANSACTION;

UPDATE settings s
INNER JOIN users u ON u.id = s.created_by
SET s.value = NULL,
    s.is_public = 0
WHERE u.type = 'company'
  AND s.`key` IN ('ai_api_key', 'ai_agent_api_key');

COMMIT;
```

The user must configure and rotate the central AI keys from the superadmin AI Settings page after deployment.

## Clear Caches And Verify

```bash
php artisan optimize:clear
php artisan permission:cache-reset
```

Verify:

1. Superadmin sees AI Settings and can preserve, replace, or explicitly clear both masked keys.
2. A company sees only AI Agent and AI Assistant enable switches and receives no AI credential values.
3. Company Brand excludes title, footer and favicon; Company System excludes registration, verification, landing-page and terms controls.
4. A company with complete SMTP sends through its own server.
5. A company without complete SMTP sends through central SMTP with its company email as reply-to.
6. Helpdesk email continues to use central SMTP.

## Rollback

Restore the cleared company rows by setting ID:

```sql
UPDATE settings s
INNER JOIN settings_scope_phase2_ai_backup_20261116 b ON b.id = s.id
SET s.value = b.value,
    s.is_public = b.is_public;
```

Roll back the application release and migration if required, clear application and permission caches, then verify the previous settings behavior. Remove the rollback table only after the release is accepted and the retention window has closed. The rollback table holds copies of the old company AI keys, which are being rotated, so drop it as soon as the release is accepted rather than keeping it for the full retention period.
