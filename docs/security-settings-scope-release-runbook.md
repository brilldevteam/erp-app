# Release Runbook - Settings Scope Phases 1-3

One ordered checklist for deploying the settings and Helpdesk security work to live. It combines `security-settings-scope-deployment.md` (Phase 1), `security-settings-scope-phase2-deployment.md` (Phase 2) and `security-settings-scope-phase3-deployment.md` (Phase 3). Never paste credential values into the terminal history, logs or chat.

- Release branch: `security/helpdesk-notifications-phase3-2026-11-16` (contains Phases 1, 2 and 3 and everything before them).
- Rehearsed on 2026-11-15 against the local copy of live data (`erp_live_copy`): all migrations already applied there; both data steps and both rollbacks rehearsed inside a rolled-back transaction with the settings table verified unchanged afterwards.

## 0. Before the deployment window

1. **Confirm what live runs.** On the server: `git rev-parse --short HEAD` and `git branch --show-current`. The release branch is 129 commits ahead of `main`, so the deploy also brings every UI, Helpdesk, salesperson and Zoho-import change made since the commit live is on. Agree this with the team before deploying.
2. **Prepare new credentials** at each provider (do not revoke the old ones yet): the AI provider keys (AI Agent and AI Assistant), the super admin SMTP password, the company SMTP password(s) that were configured, and the Google login client secret.
3. **Confirm the central SMTP account can carry company email.** Companies without their own SMTP will start sending through it.
4. Pick a quiet window. Allow about 30-45 minutes.

## 1. Back up

1. Database: full dump, then open it to confirm it is readable.
2. `.env` and `storage/app` (including `storage/app/private`, which holds Helpdesk attachments).
3. Record the current commit hash for rollback.

## 2. Maintenance mode and code

```bash
php artisan down
git fetch origin
git checkout security/helpdesk-notifications-phase3-2026-11-16
git pull --ff-only
# composer.json and composer.lock are unchanged: skip composer unless your normal deploy always runs it.
npm ci                                             # package.json/lock changed (fonts)
npm run build                                      # public/build is not in Git
```

## 3. Migrations

```bash
php artisan migrate:status | grep -i pending      # review the list before running
php artisan migrate --force
```

From the 2026-11-02 release (`release/zoho-and-security`) these 16 migrations are expected; more appear if live is older:

```
2026_11_03_000001_allow_manual_document_line_items
2026_11_03_000001_split_product_service_permissions
2026_11_03_000001_create_customer_activity_logs_table      (Account module)
2026_11_04_000001_add_quotation_salespersons
2026_11_04_000002_add_invoice_revert_to_draft
2026_11_05_000001_add_terms_to_sales_quotations_table      (Quotation module)
2026_11_07_000001_add_type_to_sales_quotations_table       (Quotation module)
2026_11_09_000001_create_helpdesk_attachments_table
2026_11_09_000002_add_helpdesk_lifecycle_and_history
2026_11_09_000003_grant_helpdesk_access_to_client_roles    (grants Helpdesk to all client roles)
2026_11_09_000004_create_helpdesk_support_roles
2026_11_10_000005_create_helpdesk_popup_notifications_table
2026_11_10_000006_create_helpdesk_email_deliveries_table
2026_11_10_000007_add_salesperson_to_sales_invoices
2026_11_14_000008_grant_helpdesk_records_permission
2026_11_16_000001_add_platform_ai_settings_permissions
```

## 4. Data step - Phase 1 (company brand rows private)

Count first. Local rehearsal: 110 rows; live will differ.

```sql
SELECT COUNT(*) AS affected_rows
FROM settings s INNER JOIN users u ON u.id = s.created_by
WHERE u.type = 'company' AND s.is_public = 1
  AND s.`key` IN ('logo_dark','logo_light','favicon','titleText','footerText','sidebarVariant','sidebarStyle','layoutDirection','themeMode','themeColor','customColor');
```

Backup table first (MySQL commits this immediately), then the update:

```sql
CREATE TABLE settings_scope_20261115_backup AS
SELECT s.* FROM settings s INNER JOIN users u ON u.id = s.created_by
WHERE u.type = 'company' AND s.is_public = 1
  AND s.`key` IN ('logo_dark','logo_light','favicon','titleText','footerText','sidebarVariant','sidebarStyle','layoutDirection','themeMode','themeColor','customColor');

START TRANSACTION;
UPDATE settings s INNER JOIN users u ON u.id = s.created_by
SET s.is_public = 0
WHERE u.type = 'company' AND s.is_public = 1
  AND s.`key` IN ('logo_dark','logo_light','favicon','titleText','footerText','sidebarVariant','sidebarStyle','layoutDirection','themeMode','themeColor','customColor');
-- the affected-row count must equal the count above
COMMIT;
```

## 5. Data step - Phase 2 (clear old company AI keys)

Count first (never select the values). Local rehearsal: 4 rows, all with a value.

```sql
SELECT COUNT(*) AS affected_rows
FROM settings s INNER JOIN users u ON u.id = s.created_by
WHERE u.type = 'company' AND s.`key` IN ('ai_api_key','ai_agent_api_key');
```

```sql
CREATE TABLE settings_scope_phase2_ai_backup_20261116 AS
SELECT s.* FROM settings s INNER JOIN users u ON u.id = s.created_by
WHERE u.type = 'company' AND s.`key` IN ('ai_api_key','ai_agent_api_key');

START TRANSACTION;
UPDATE settings s INNER JOIN users u ON u.id = s.created_by
SET s.value = NULL, s.is_public = 0
WHERE u.type = 'company' AND s.`key` IN ('ai_api_key','ai_agent_api_key');
COMMIT;
```

Phase 3 has no data step.

## 6. Caches, queue and permissions

```bash
php artisan optimize:clear
php artisan permission:cache-reset
php artisan queue:restart
```

- The queue worker must process the `emails` queue, for example `php artisan queue:work --queue=emails,default` under the existing supervisor.
- `storage/app/private` must be writable by the web user.

```bash
php artisan up
```

## 7. Configure right after going live

1. Super admin → Settings → **AI Settings**: enter the new provider, model and keys for AI Agent and AI Assistant. AI is off for every company until this is done.
2. Super admin → Settings → **Email Settings**: enter the new SMTP password.
3. Super admin → Settings → **Helpdesk Notifications**: set the support mailbox and choose the switches.
4. Companies that had their own SMTP: enter their new password in their Email Settings.
5. Super admin → Settings → Social Login: enter the new Google client secret.

## 8. Verify on live

```bash
php artisan security:check   # must report: All checks passed
```

1. Logged out: the login page shows the Wazely logo, title and footer, never a company's.
2. Super admin: Brand, System, Currency, Cookie, Pusher, SEO, Storage, Email, AI Settings and Helpdesk Notifications each save without an error.
3. A company: Company, Brand (logos and theme only), System (four fields), Currency, Email (optional), AI Agent and AI Assistant switches each save without an error. The company sees no AI key fields.
4. A company without its own SMTP sends one invoice email: it arrives from the central mailbox with the company name and replies go to the company email.
5. An AI Assistant button appears on a form and generates text.
6. Helpdesk: a customer opens a test ticket; the support mailbox receives "New Helpdesk ticket …"; a support reply reaches the customer; an internal note sends nothing.
7. Check `storage/logs/laravel.log` for new errors.

When everything passes, **revoke the old credentials** at each provider.

## 9. Rollback

- Data steps only:

```sql
UPDATE settings s INNER JOIN settings_scope_20261115_backup b ON b.id = s.id SET s.is_public = b.is_public;
UPDATE settings s INNER JOIN settings_scope_phase2_ai_backup_20261116 b ON b.id = s.id SET s.value = b.value, s.is_public = b.is_public;
```

- Whole release: `php artisan down`, check out the recorded commit, restore the database from the step 1 backup (the migrations add tables, columns and permissions), rebuild assets, `php artisan optimize:clear`, `php artisan permission:cache-reset`, `php artisan queue:restart`, `php artisan up`.

## 10. Clean up after acceptance

- Drop `settings_scope_phase2_ai_backup_20261116` as soon as the release is accepted: it holds copies of the old, rotated AI keys.
- Drop `settings_scope_20261115_backup` after the normal retention period.
